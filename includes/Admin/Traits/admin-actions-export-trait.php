<?php
namespace WA_ACF_PTM\Admin\Traits;

use WA_ACF_PTM\Admin\Import_Plan_Store;
use WA_ACF_PTM\Admin\Settings;
use WA_ACF_PTM\Admin\Services\Download_Response_Service;
use WA_ACF_PTM\Admin\Services\Temp_File_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Admin_Actions_Export_Trait {

	public function register_pages_bulk_export_action( array $bulk_actions ): array {
		if ( current_user_can( Settings::get_manage_capability() ) ) {
			$bulk_actions['wa_acf_ptm_export_pages'] = __( 'Exporteren met ACF Tekstbeheer', 'acf-page-text-manager' );
		}

		return $bulk_actions;
	}

	public function handle_pages_bulk_export_action( string $redirect_to, string $action, array $post_ids ): string {
		if ( 'wa_acf_ptm_export_pages' !== $action ) {
			return $redirect_to;
		}

		$this->assert_permissions();
		$this->assert_acf_dependency();

		$references = array();
		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( 0 === $post_id ) {
				continue;
			}

			$post_type = get_post_type( $post_id );
			if ( ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
				continue;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$references[] = $post_type . ':' . $post_id;
		}

		$references = array_values( array_unique( $references ) );
		if ( empty( $references ) ) {
			return add_query_arg(
				array(
					'wa_acf_ptm_bulk_export' => 'empty',
				),
				$redirect_to
			);
		}

		if ( 1 === count( $references ) ) {
			$target = $this->repository->get_target_data( $references[0] );
			if ( empty( $target['fields'] ) ) {
				return add_query_arg(
					array(
						'wa_acf_ptm_bulk_export' => 'no_fields',
					),
					$redirect_to
				);
			}

			$this->csv_service->stream_export( $target, array(), 'csv' );
		}

		$this->stream_multi_export_zip( $references, array(), 'csv' );

		return $redirect_to;
	}

	public function handle_export(): void {
		$this->assert_permissions();
		$this->assert_acf_dependency();
		check_admin_referer( 'wa_acf_ptm_export_action', 'wa_acf_ptm_export_nonce' );

		$content_scope = isset( $_POST['content_scope'] ) ? sanitize_key( wp_unslash( $_POST['content_scope'] ) ) : '';
		$references  = $this->get_requested_export_references();
		$format      = $this->get_requested_export_format();
		$field_keys  = $this->get_requested_export_field_keys();
		$field_selection_reference = $this->get_requested_field_selection_reference();
		$has_custom_selection = $this->has_custom_field_selection();

		if ( '' !== $content_scope ) {
			$references = array_values( array_filter( $references, function( string $reference ) use ( $content_scope ): bool {
				$target = $this->repository->get_target_data( $reference );
				return ! empty( $target['fields'] ) && (string) ( $target['content_scope'] ?? '' ) === $content_scope;
			} ) );
		}

		if ( empty( $references ) ) {
			$this->redirect_with_notice( 'error', __( 'Kies minstens één item om te exporteren.', 'acf-page-text-manager' ) );
		}

		if ( count( $references ) > 1 || ( $has_custom_selection && ( '' === $field_selection_reference || (string) $references[0] !== $field_selection_reference ) ) ) {
			$field_keys           = array();
			$has_custom_selection = false;
		}

		if ( $has_custom_selection && empty( $field_keys ) ) {
			$this->redirect_with_notice( 'error', __( 'Selecteer minstens één veld voor export.', 'acf-page-text-manager' ) );
		}

		if ( 1 === count( $references ) ) {
			$target = $this->repository->get_target_data( $references[0] );
			if ( empty( $target['fields'] ) ) {
				$this->redirect_with_notice( 'error', __( 'Er is geen geldig doel met exporteerbare velden gevonden.', 'acf-page-text-manager' ) );
			}

			$this->csv_service->stream_export( $target, $field_keys, $format );
		}

		$this->stream_multi_export_zip( $references, $field_keys, $format );
	}

	public function handle_rollback_download(): void {
		$this->assert_permissions();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce is verified against the sanitized token immediately below.
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $token || ! wp_is_uuid( $token ) ) {
			wp_die( esc_html__( 'Het rollbackbestand is niet gevonden of hoort niet bij de huidige gebruiker.', 'acf-page-text-manager' ) );
		}

		check_admin_referer( 'wa_acf_ptm_rollback_download_' . $token, 'wa_acf_ptm_rollback_nonce' );

		$plan  = $this->plan_store->get( $token );
		if ( ! is_array( $plan ) || empty( $plan['user_id'] ) || get_current_user_id() !== (int) $plan['user_id'] ) {
			wp_die( esc_html__( 'Het rollbackbestand is niet gevonden of hoort niet bij de huidige gebruiker.', 'acf-page-text-manager' ) );
		}

		$rows = isset( $plan['rollback_rows'] ) && is_array( $plan['rollback_rows'] ) ? $plan['rollback_rows'] : array();
		if ( empty( $rows ) ) {
			wp_die( esc_html__( 'Er zijn geen rollbackregels beschikbaar voor dit importplan.', 'acf-page-text-manager' ) );
		}

		$this->csv_service->stream_custom_csv_rows(
			'wa-acf-ptm-rollback-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv',
			array( 'target_type', 'content_scope', 'target_id', 'target_title', 'target_slug', 'page_id', 'page_title', 'field_name', 'field_key', 'field_label', 'field_type', 'language_code', 'original_value', 'value' ),
			$rows
		);
	}


	private function get_requested_export_references(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified in handle_export(); array entries are sanitized below.
		$references = isset( $_POST['target_references'] ) ? (array) wp_unslash( $_POST['target_references'] ) : array();
		$single     = isset( $_POST['target_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['target_reference'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' !== $single && empty( $references ) ) {
			$references = array( $single );
		}

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_text_field', $references )
				)
			)
		);
	}

	private function get_requested_export_format(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_export() before this value is read.
		$format = isset( $_POST['export_format'] ) && 'xlsx' === sanitize_key( wp_unslash( $_POST['export_format'] ) ) ? 'xlsx' : 'csv';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $format;
	}

	private function get_requested_export_field_keys(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified in handle_export(); array entries are sanitized below.
		$field_keys = isset( $_POST['selected_field_keys'] ) ? (array) wp_unslash( $_POST['selected_field_keys'] ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_text_field', $field_keys )
				)
			)
		);
	}


	private function get_requested_field_selection_reference(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_export() before this value is read.
		$reference = isset( $_POST['field_selection_target_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['field_selection_target_reference'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $reference;
	}

	private function has_custom_field_selection(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_export() before this value is read.
		$has_custom_selection = isset( $_POST['field_selection_mode'] ) && 'custom' === sanitize_key( wp_unslash( $_POST['field_selection_mode'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $has_custom_selection;
	}

	private function stream_multi_export_zip( array $references, array $field_keys = array(), string $format = 'csv' ): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->redirect_with_notice( 'error', __( 'Meerdere exports vereisen de PHP ZipArchive-extensie.', 'acf-page-text-manager' ) );
		}

		$temp_file = wp_tempnam( 'wa-acf-ptm-exports.zip' );
		$zip       = new \ZipArchive();

		if ( ! $temp_file || true !== $zip->open( $temp_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			$this->redirect_with_notice( 'error', __( 'Kon het export-zipbestand niet maken.', 'acf-page-text-manager' ) );
		}

		$xlsx_temp_files = array();
		$files_added      = 0;

		foreach ( $references as $reference ) {
			$target = $this->repository->get_target_data( $reference );
			if ( empty( $target['fields'] ) ) {
				continue;
			}

			$rows      = $this->csv_service->get_export_rows( $target, $field_keys );
			$base_name = sanitize_file_name( (string) ( $target['slug'] ?? $target['title'] ?? 'export' ) );

			if ( 'xlsx' === $format ) {
				$xlsx_file = $this->csv_service->build_xlsx_file( $rows, $base_name . '.xlsx' );
				if ( '' !== $xlsx_file && file_exists( $xlsx_file ) && false !== $zip->addFile( $xlsx_file, $base_name . '.xlsx' ) ) {
					$xlsx_temp_files[] = $xlsx_file;
					$files_added++;
				}
				continue;
			}

			$content  = $this->csv_service->build_csv_string( $rows );
			$filename = $base_name . '.csv';
			if ( false !== $zip->addFromString( $filename, $content ) ) {
				$files_added++;
			}
		}

		$zip->close();

		foreach ( $xlsx_temp_files as $xlsx_temp_file ) {
			Temp_File_Service::delete( (string) $xlsx_temp_file );
		}

		if ( 0 === $files_added ) {
			Temp_File_Service::delete( $temp_file );
			$this->redirect_with_notice( 'error', __( 'Er konden geen exportbestanden worden toegevoegd aan het ZIP-bestand.', 'acf-page-text-manager' ) );
		}

		if ( ! Temp_File_Service::is_safe_temp_file( $temp_file ) || ! is_readable( $temp_file ) ) {
			Temp_File_Service::delete( $temp_file );
			$this->redirect_with_notice( 'error', __( 'Kon het export-zipbestand niet veilig lezen.', 'acf-page-text-manager' ) );
		}

		Download_Response_Service::send_headers(
			'application/zip',
			'wa-acf-text-exports-' . gmdate( 'Y-m-d-H-i-s' ) . '.zip',
			filesize( $temp_file )
		);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams a generated ZIP export to the browser after temp-path validation.
		readfile( $temp_file );
		Temp_File_Service::delete( $temp_file );
		exit;
	}


}
