<?php
namespace WA_ACF_PTM\Admin\Traits;

use WA_ACF_PTM\Admin\Services\Field_Value_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Admin_Actions_Inline_Trait {
	public function ajax_save_field(): void {
		$this->assert_ajax_permissions();

		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- AJAX nonce is verified in assert_ajax_permissions(); value is sanitized according to field type in save_single_field_value().
		$target_reference     = isset( $_POST['target_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['target_reference'] ) ) : '';
		$field_key            = isset( $_POST['field_key'] ) ? sanitize_text_field( wp_unslash( $_POST['field_key'] ) ) : '';
		$value                = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- AJAX nonce is verified in Admin_Actions::assert_ajax_permissions() before this handler is called.
		$confirm_media_rename = isset( $_POST['confirm_media_rename'] ) ? sanitize_key( wp_unslash( $_POST['confirm_media_rename'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $target_reference || '' === $field_key ) {
			wp_send_json_error( array( 'message' => __( 'Veld of item ontbreekt.', 'acf-page-text-manager' ) ), 400 );
		}

		$result = $this->save_single_field_value( $target_reference, $field_key, is_scalar( $value ) ? (string) $value : '', '1' === $confirm_media_rename );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	private function save_single_field_value( string $target_reference, string $field_key, string $raw_value, bool $confirm_media_rename = false ) {
		$target = $this->repository->get_target_data( $target_reference );
		if ( empty( $target['fields'] ) ) {
			return new \WP_Error( 'invalid_target', __( 'Het gekozen item is niet gevonden.', 'acf-page-text-manager' ) );
		}

		$field = null;
		foreach ( (array) $target['fields'] as $candidate ) {
			if ( (string) ( $candidate['key'] ?? '' ) === $field_key ) {
				$field = $candidate;
				break;
			}
		}

		if ( ! is_array( $field ) ) {
			return new \WP_Error( 'invalid_field', __( 'Het veld kon niet worden gevonden.', 'acf-page-text-manager' ) );
		}

		if ( ! empty( $field['read_only'] ) ) {
			return new \WP_Error( 'readonly_field', ! empty( $field['readonly_reason'] ) ? (string) $field['readonly_reason'] : __( 'Dit veld is alleen-lezen en kan niet worden aangepast.', 'acf-page-text-manager' ) );
		}

		if ( ! $this->target_permissions->current_user_can_edit( $target ) ) {
			return new \WP_Error( 'forbidden_target', __( 'Je hebt geen toestemming om dit item te bewerken.', 'acf-page-text-manager' ) );
		}

		if ( $this->is_media_filename_field( $field ) && ! $confirm_media_rename ) {
			return new \WP_Error( 'media_rename_confirmation_required', __( 'Bevestig expliciet dat je begrijpt dat een media-bestandsnaamwijziging bestaande media-URL’s kan wijzigen.', 'acf-page-text-manager' ) );
		}

		$values         = new Field_Value_Service();
		$special_fields = new \WA_ACF_PTM\Admin\Services\Special_Field_Service( $values );
		$field_type     = (string) ( $field['type'] ?? 'text' );
		$new_value      = $values->sanitize_by_type( $raw_value, $field_type );
		$updated        = false;
		$is_special     = 0 === strpos( (string) ( $field['key'] ?? '' ), 'special_' );

		$operation_context = array(
			'acf_post_id'   => $target['acf_post_id'],
			'target_type'   => (string) ( $target['target_type'] ?? '' ),
			'target_id'     => (string) ( $target['target_id'] ?? '' ),
			'content_scope' => (string) ( $target['content_scope'] ?? '' ),
			'reference'     => (string) ( $target['reference'] ?? $target_reference ),
			'field_key'     => (string) $field['key'],
			'field_name'    => (string) ( $field['name'] ?? '' ),
			'field_type'    => $field_type,
		);

		if ( $is_special ) {
			$updated = $special_fields->update_special_field_value( $operation_context, $new_value );
		} else {
			if ( ! function_exists( 'update_field' ) ) {
				return new \WP_Error( 'acf_unavailable', __( 'ACF is niet actief voor dit ACF-veld.', 'acf-page-text-manager' ) );
			}

			$updated = update_field( (string) $field['key'], $new_value, $target['acf_post_id'] );
			if ( false === $updated && ! empty( $field['name'] ) ) {
				$updated = update_field( (string) $field['name'], $new_value, $target['acf_post_id'] );
			}
		}

		$current_after = $is_special
			? $special_fields->get_special_field_value( $operation_context )
			: $values->get_current_field_value( (string) ( $field['name'] ?? '' ), $target['acf_post_id'], (string) ( $field['key'] ?? '' ) );

		if ( false === $updated && $values->values_match_for_conflict_check( $values->stringify_value( $current_after ), $new_value, $field_type ) ) {
			$updated = true;
		}

		if ( false === $updated ) {
			return new \WP_Error( 'save_failed', __( 'De tekst kon niet worden opgeslagen.', 'acf-page-text-manager' ) );
		}

		$this->repository->clear_cache();
		$display_value = $values->stringify_value( $new_value );

		return array(
			'message'      => __( 'Opgeslagen.', 'acf-page-text-manager' ),
			'value'        => $display_value,
			'display_html' => '' === trim( wp_strip_all_tags( $display_value ) ) ? '<span class="wa-acf-ptm-empty">' . esc_html__( 'Leeg', 'acf-page-text-manager' ) . '</span>' : wpautop( wp_kses_post( $display_value ) ),
		);
	}

	private function is_media_filename_field( array $field ): bool {
		$field_type = (string) ( $field['type'] ?? '' );
		$field_name = (string) ( $field['name'] ?? '' );

		return 'image_meta' === $field_type && str_ends_with( $field_name, '__file_name' );
	}


}
