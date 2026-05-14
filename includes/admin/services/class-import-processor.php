<?php
namespace WA_ACF_PTM\Admin\Services;

use WA_ACF_PTM\Admin\Import_Plan_Store;
use WA_ACF_PTM\Admin\Page_Repository;
use WA_ACF_PTM\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Import_Processor {
	private Import_Plan_Store $plan_store;
	private Page_Repository $repository;
	private Field_Value_Service $values;
	private Special_Field_Service $special_fields;
	private Target_Permission_Service $target_permissions;

	public function __construct( Import_Plan_Store $plan_store, Page_Repository $repository, ?Field_Value_Service $values = null, ?Special_Field_Service $special_fields = null, ?Target_Permission_Service $target_permissions = null ) {
		$this->plan_store     = $plan_store;
		$this->repository     = $repository;
		$this->values         = $values ?? new Field_Value_Service();
		$this->special_fields = $special_fields ?? new Special_Field_Service( $this->values );
		$this->target_permissions = $target_permissions ?? new Target_Permission_Service();
	}

	public function process( string $token ): array {
		$plan = $this->plan_store->get( $token );
		if ( ! is_array( $plan ) ) {
			return array(
				'error'  => __( 'Het importplan is niet gevonden of is verlopen.', 'acf-page-text-manager' ),
				'status' => 404,
			);
		}

		if ( empty( $plan['user_id'] ) || get_current_user_id() !== (int) $plan['user_id'] ) {
			return array(
				'error'  => __( 'Dit importplan hoort niet bij de huidige gebruiker of is ongeldig.', 'acf-page-text-manager' ),
				'status' => 403,
			);
		}

		if ( array_key_exists( 'import_allowed', $plan ) && empty( $plan['import_allowed'] ) ) {
			return array(
				'error'  => __( 'Deze import is niet toegestaan door het importvoorbeeld. Maak opnieuw een importvoorbeeld en controleer de meldingen.', 'acf-page-text-manager' ),
				'status' => 403,
			);
		}

		$operations = isset( $plan['operations'] ) && is_array( $plan['operations'] ) ? $plan['operations'] : array();
		$operations = array_values(
			array_filter(
				$operations,
				static function ( $operation ): bool {
					return is_array( $operation );
				}
			)
		);
		$updatable_indexes = array_keys(
			array_filter(
				$operations,
				static function ( array $operation ): bool {
					return 'update' === (string) ( $operation['action'] ?? '' );
				}
			)
		);
		if ( $this->plan_contains_media_filename_update( $operations ) && empty( $plan['import_options']['confirm_media_rename'] ) ) {
			return array(
				'error'  => __( 'Deze import bevat media-bestandsnaamwijzigingen en vereist expliciete bevestiging in het importvoorbeeld.', 'acf-page-text-manager' ),
				'status' => 403,
			);
		}

		$batch_size        = max( 1, (int) Settings::get()['import_batch_size'] );
		$cursor            = isset( $plan['cursor'] ) ? absint( $plan['cursor'] ) : 0;
		$batch             = array_slice( $updatable_indexes, $cursor, $batch_size );
		$updated_count     = isset( $plan['updated_count'] ) ? absint( $plan['updated_count'] ) : 0;
		$error_messages    = isset( $plan['error_messages'] ) && is_array( $plan['error_messages'] ) ? $plan['error_messages'] : array();

		foreach ( $batch as $operation_index ) {
			$operation = isset( $operations[ $operation_index ] ) && is_array( $operations[ $operation_index ] ) ? $operations[ $operation_index ] : array();
			if ( ! $this->target_permissions->current_user_can_edit( $operation ) ) {
				$error_messages[] = sprintf(
					/* translators: %d: import row number. */
					__( 'Rij %d kon niet worden bijgewerkt: je hebt geen toestemming om dit item te bewerken.', 'acf-page-text-manager' ),
					(int) ( $operation['row_index'] ?? 0 )
				);
				continue;
			}

			$field_type = (string) ( $operation['field_type'] ?? 'text' );
			$operation_value = $operation['new_value'] ?? '';
			if ( ! is_scalar( $operation_value ) ) {
				$encoded_value = wp_json_encode( $operation_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				$operation_value = is_string( $encoded_value ) ? $encoded_value : '';
			}

			$new_value     = $this->values->sanitize_by_type( (string) $operation_value, $field_type );
			$updated       = false;
			$current_after = '';

			if ( ! empty( $operation['is_special'] ) ) {
				$updated = $this->special_fields->update_special_field_value( $operation, $new_value );
				$current_after = $this->values->stringify_value( $this->special_fields->get_special_field_value( $operation ) );
			} else {
				$field_key  = (string) ( $operation['field_key'] ?? '' );
				$field_name = (string) ( $operation['field_name'] ?? '' );
				$acf_post_id = $operation['acf_post_id'] ?? 0;

				if ( '' === $field_key && '' === $field_name ) {
					$error_messages[] = sprintf(
						/* translators: %d: import row number. */
						__( 'Rij %d kon niet worden bijgewerkt: veldinformatie ontbreekt.', 'acf-page-text-manager' ),
						(int) ( $operation['row_index'] ?? 0 )
					);
					continue;
				}

				if ( ! function_exists( 'update_field' ) ) {
					$error_messages[] = sprintf(
						/* translators: 1: import row number, 2: field label. */
						__( 'Rij %1$d kon veld %2$s niet bijwerken: ACF is niet actief.', 'acf-page-text-manager' ),
						(int) ( $operation['row_index'] ?? 0 ),
						(string) ( $operation['field_label'] ?? '' )
					);
					continue;
				}

				$updated = '' !== $field_key ? update_field( $field_key, $new_value, $acf_post_id ) : false;
				if ( false === $updated && '' !== $field_name ) {
					$updated = update_field( $field_name, $new_value, $acf_post_id );
				}
				$current_after = $this->values->get_current_field_value( $field_name, $acf_post_id, $field_key );
			}

			if ( false === $updated && $this->values->values_match_for_conflict_check( $current_after, $new_value, $field_type ) ) {
				$updated = true;
			}

			if ( false === $updated ) {
				$error_messages[] = sprintf(
					/* translators: 1: import row number, 2: field label. */
					__( 'Rij %1$d kon veld %2$s niet bijwerken.', 'acf-page-text-manager' ),
					(int) ( $operation['row_index'] ?? 0 ),
					(string) ( $operation['field_label'] ?? '' )
				);
				continue;
			}

			$updated_count++;
		}

		$cursor                  += count( $batch );
		$plan['cursor']           = $cursor;
		$plan['updated_count']    = $updated_count;
		$plan['error_messages']   = $error_messages;
		$total_updates            = count( $updatable_indexes );
		$done                     = $cursor >= $total_updates;

		if ( $done ) {
			$this->repository->clear_cache();
			$this->plan_store->delete( $token );

			return array(
				'done'          => true,
				'processed'     => $total_updates,
				'total'         => $total_updates,
				'percent'       => 100,
				'updated_count' => $updated_count,
				/* translators: 1: updated field count, 2: skipped row count. */
				'notice'        => sprintf( __( 'Import afgerond. %1$d veld(en) bijgewerkt, %2$d rij(en) overgeslagen.', 'acf-page-text-manager' ), $updated_count, (int) ( $plan['summary']['skipped_count'] ?? 0 ) ),
				'warnings'      => array_slice( array_merge( isset( $plan['summary']['warnings'] ) && is_array( $plan['summary']['warnings'] ) ? $plan['summary']['warnings'] : array(), $error_messages ), 0, 20 ),
				'redirect_url'  => $this->get_redirect_url( (string) ( $plan['redirect_target_reference'] ?? '' ) ),
			);
		}

		$this->plan_store->save( $token, $plan );
		$percent = 0 === $total_updates ? 100 : (int) floor( ( $cursor / $total_updates ) * 100 );

		return array(
			'done'          => false,
			'processed'     => $cursor,
			'total'         => $total_updates,
			'percent'       => $percent,
			'updated_count' => $updated_count,
		);
	}



	private function plan_contains_media_filename_update( array $operations ): bool {
		foreach ( $operations as $operation ) {
			if ( ! is_array( $operation ) || 'update' !== (string) ( $operation['action'] ?? '' ) ) {
				continue;
			}

			if ( 'image_meta' === (string) ( $operation['field_type'] ?? '' ) && str_ends_with( (string) ( $operation['field_name'] ?? '' ), '__file_name' ) ) {
				return true;
			}
		}

		return false;
	}


	private function get_redirect_url( string $target_reference ): string {
		$args = array( 'page' => 'acf-page-text-manager' );

		if ( '' !== $target_reference ) {
			$args['target'] = $target_reference;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
