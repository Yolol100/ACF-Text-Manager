<?php
namespace WA_ACF_PTM\Admin\Services;

use WA_ACF_PTM\Admin\CSV_Service;
use WA_ACF_PTM\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Import_Plan_Builder {
	private CSV_Service $csv_service;
	private Field_Value_Service $values;
	private Special_Field_Service $special_fields;

	public function __construct( CSV_Service $csv_service, ?Field_Value_Service $values = null, ?Special_Field_Service $special_fields = null ) {
		$this->csv_service    = $csv_service;
		$this->values         = $values ?? new Field_Value_Service();
		$this->special_fields = $special_fields ?? new Special_Field_Service( $this->values );
	}

	public function build( string $token, array $target, array $dataset, array $mapping, string $file_name ): array {
		$rows              = $this->csv_service->map_dataset_rows( $dataset, $mapping );
		$field_map_by_key = array();
		$field_map_by_name = array();
		$field_map_by_label = array();
		foreach ( $target['fields'] as $field ) {
			$field_key = (string) ( $field['key'] ?? '' );

			$field_map_by_key[ $field_key ] = $field;
			$field_map_by_name[ $field['name'] ] = $field;
			$field_map_by_label[ sanitize_title( (string) $field['label'] ) ] = $field;

			foreach ( $this->get_field_name_aliases( $field ) as $alias ) {
				$field_map_by_name[ $alias ] = $field;
			}
		}

		$settings = array_merge( Settings::get(), isset( $target['import_options'] ) && is_array( $target['import_options'] ) ? $target['import_options'] : array() );
		$has_value_mapping = in_array( 'value', $mapping, true );
		$operations   = array();
		$warnings     = array();
		$updates      = 0;
		$skips        = 0;

		foreach ( $rows as $row ) {
			$reason          = '';
			$action          = 'skip';
			$warning         = '';
			$field           = null;
			$target_mismatch = false;
			$ignore_target_mismatch = ! empty( $target['ignore_import_target_mismatch'] );

			$accepted_target_types = array_values(
				array_unique(
					array_filter(
						array(
							sanitize_key( (string) ( $target['target_type'] ?? '' ) ),
							sanitize_key( (string) ( $target['content_scope'] ?? '' ) ),
						)
					)
				)
			);
			if ( ! $ignore_target_mismatch && ! empty( $row['target_type'] ) && ! in_array( sanitize_key( (string) $row['target_type'] ), $accepted_target_types, true ) ) {
				$target_mismatch = true;
			}
			if ( ! $ignore_target_mismatch && ! empty( $row['target_id'] ) && (string) $row['target_id'] !== (string) $target['target_id'] ) {
				if ( ! ( 'page' === $target['target_type'] && (int) $row['page_id'] === (int) $target['target_id'] ) ) {
					$target_mismatch = true;
				}
			}
			if ( ! empty( $row['field_key'] ) && isset( $field_map_by_key[ $row['field_key'] ] ) ) {
				$field = $field_map_by_key[ $row['field_key'] ];
			} elseif ( ! empty( $row['field_name'] ) && isset( $field_map_by_name[ $row['field_name'] ] ) ) {
				$field = $field_map_by_name[ $row['field_name'] ];
			} elseif ( ! empty( $row['field_label'] ) ) {
				$field = $field_map_by_label[ sanitize_title( (string) $row['field_label'] ) ] ?? null;
			}

			if ( $target_mismatch ) {
				$reason = __( 'Het doel in het bestand komt niet overeen met het gekozen type of item.', 'acf-page-text-manager' );
			} elseif ( empty( $field ) ) {
				$reason = __( 'Er is geen passend ondersteund veld gevonden voor deze rij.', 'acf-page-text-manager' );
			} elseif ( ! empty( $field['read_only'] ) ) {
				$reason = ! empty( $field['readonly_reason'] ) ? (string) $field['readonly_reason'] : __( 'Dit veld is alleen-lezen en kan niet worden aangepast.', 'acf-page-text-manager' );
			} else {
				$raw_import_value = (string) $row['value'];
				$is_empty_import_value = '' === trim( $raw_import_value );
				if ( ! $has_value_mapping && $is_empty_import_value && empty( $settings['skip_empty_values'] ) && '' !== trim( (string) ( $row['original_value'] ?? '' ) ) ) {
					$raw_import_value = (string) $row['original_value'];
					$is_empty_import_value = '' === trim( $raw_import_value );
				}
				$new_value  = $this->values->sanitize_by_type( $raw_import_value, (string) $field['type'] );
				$is_special = 0 === strpos( (string) ( $field['key'] ?? '' ), 'special_' );
				$current_value = $is_special
					? $this->values->stringify_value(
						$this->special_fields->get_special_field_value(
							array(
								'acf_post_id'   => $target['acf_post_id'],
								'target_type'   => (string) $target['target_type'],
								'target_id'     => (string) $target['target_id'],
								'content_scope' => (string) ( $target['content_scope'] ?? '' ),
								'reference'     => (string) ( $target['reference'] ?? '' ),
								'field_key'     => (string) ( $field['key'] ?? '' ),
								'field_name'    => (string) $field['name'],
							)
						)
					)
					: $this->values->get_current_field_value( (string) $field['name'], $target['acf_post_id'], (string) $field['key'] );

				if ( ! empty( $row['language_code'] ) && ! empty( $target['language_code'] ) && strtolower( (string) $row['language_code'] ) !== strtolower( (string) $target['language_code'] ) ) {
					if ( 'strict' === $settings['language_mode'] ) {
						$reason = __( 'De taalcode in deze rij komt niet overeen met het geselecteerde item.', 'acf-page-text-manager' );
					} elseif ( 'warn' === $settings['language_mode'] ) {
						$warning = __( 'De taalcode wijkt af van het geselecteerde item.', 'acf-page-text-manager' );
					}
				}

				$has_value_change = ! $this->values->values_match_for_conflict_check( $current_value, $new_value, (string) $field['type'] );
				if ( $is_empty_import_value && ! empty( $settings['skip_empty_values'] ) ) {
					$reason = __( 'Lege importwaarde; deze rij wordt overgeslagen.', 'acf-page-text-manager' );
				}
				if ( '' === $reason && '' === trim( (string) $raw_import_value ) && ! $has_value_change ) {
					$reason = __( 'Lege invoer zonder wijziging; deze rij wordt overgeslagen.', 'acf-page-text-manager' );
				}
				if ( '' === $reason && empty( $settings['overwrite_existing'] ) && $has_value_change && ! $this->is_effectively_empty_value( $current_value ) ) {
					$reason = __( 'Er bestaat al een waarde en overschrijven is uitgeschakeld.', 'acf-page-text-manager' );
				}
				if ( '' === $reason && ! $has_value_change ) {
					$reason = __( 'De nieuwe waarde is gelijk aan de huidige waarde; geen wijziging nodig.', 'acf-page-text-manager' );
				}

				if ( '' === $reason ) {
					$action = 'update';
					$updates++;
				} else {
					$skips++;
				}

				$operation = array(
					'row_index'      => (int) $row['row_index'],
					'action'         => $action,
					'acf_post_id'    => $target['acf_post_id'],
					'target_type'    => (string) $target['target_type'],
					'target_id'      => (string) $target['target_id'],
					'content_scope'  => (string) ( $target['content_scope'] ?? '' ),
					'reference'      => (string) ( $target['reference'] ?? '' ),
					'field_key'      => (string) $field['key'],
					'field_name'     => (string) $field['name'],
					'field_label'    => (string) $field['label'],
					'field_type'     => (string) $field['type'],
					'is_special'     => $is_special,
					'new_value'      => $new_value,
					'current_value'  => $this->values->stringify_value( $current_value ),
					'target_title'   => (string) ( $target['title'] ?? '' ),
					'target_slug'    => (string) ( $target['slug'] ?? '' ),
					'language_code'  => (string) ( $target['language_code'] ?? '' ),
				);

				if ( 'update' === $action ) {
					$operations[] = $operation;
				}
				if ( '' !== $warning ) {
					/* translators: 1: import row number, 2: warning message. */
					$warnings[] = sprintf( __( 'Rij %1$d: %2$s', 'acf-page-text-manager' ), (int) $row['row_index'], $warning );
				}
				continue;
			}
			$skips++;
		}

		$unmapped_columns = array_values( array_filter( array_keys( $mapping ), static fn( string $header ): bool => '' === (string) ( $mapping[ $header ] ?? '' ) ) );

		$rollback_rows = $this->build_rollback_rows( $operations );

		return array(
			'token'            => $token,
			'file_name'        => $file_name,
			'target_title'     => (string) $target['title'],
			'operations'       => $operations,
			'cursor'           => 0,
			'updated_count'    => 0,
			'error_messages'   => array(),
			'rollback_rows'    => $rollback_rows,
			'summary'           => array(
				'total_rows'      => count( $rows ),
				'update_count'    => $updates,
				'skipped_count'   => $skips,
				'warnings'        => array_values( array_unique( $warnings ) ),
				'mapped_columns'  => count( array_filter( $mapping ) ),
				'unmapped_columns' => $unmapped_columns,
			),
		);
	}


	private function build_rollback_rows( array $operations ): array {
		$rows = array();
		foreach ( $operations as $operation ) {
			if ( ! is_array( $operation ) || 'update' !== (string) ( $operation['action'] ?? '' ) ) {
				continue;
			}

			$target_type   = sanitize_key( (string) ( $operation['target_type'] ?? '' ) );
			$content_scope = sanitize_key( (string) ( $operation['content_scope'] ?? '' ) );
			$target_id     = (string) ( $operation['target_id'] ?? '' );

			$rows[] = array(
				$target_type,
				$content_scope,
				$target_id,
				(string) ( $operation['target_title'] ?? '' ),
				(string) ( $operation['target_slug'] ?? '' ),
				'page' === $content_scope ? absint( $target_id ) : 0,
				'page' === $content_scope ? (string) ( $operation['target_title'] ?? '' ) : '',
				(string) ( $operation['field_name'] ?? '' ),
				(string) ( $operation['field_key'] ?? '' ),
				(string) ( $operation['field_label'] ?? '' ),
				(string) ( $operation['field_type'] ?? '' ),
				(string) ( $operation['language_code'] ?? '' ),
				(string) ( $operation['current_value'] ?? '' ),
				(string) ( $operation['current_value'] ?? '' ),
			);
		}

		return $rows;
	}


	private function is_effectively_empty_value( $value ): bool {
		if ( null === $value || false === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			return '' === trim( $value );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! $this->is_effectively_empty_value( $item ) ) {
					return false;
				}
			}

			return true;
		}

		return false;
	}

	private function get_field_name_aliases( array $field ): array {
		$field_name = (string) ( $field['name'] ?? '' );
		$aliases = array(
			$field_name,
			'acf_' . $field_name,
			'field_' . $field_name,
		);

		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $aliases ) ) ) );
	}

}
