<?php
namespace WA_ACF_PTM\Admin\Controllers;

use WA_ACF_PTM\Admin\CSV_Service;
use WA_ACF_PTM\Admin\Import_Plan_Store;
use WA_ACF_PTM\Admin\Services\File_Upload_Service;
use WA_ACF_PTM\Admin\Services\Import_Plan_Builder;
use WA_ACF_PTM\Admin\Services\Import_Processor;
use WA_ACF_PTM\Admin\Services\Import_Target_Detector;
use WA_ACF_PTM\Admin\Services\Temp_File_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Import_Controller {
	private CSV_Service $csv_service;
	private Import_Plan_Store $plan_store;
	private File_Upload_Service $upload_service;
	private Import_Plan_Builder $plan_builder;
	private Import_Processor $import_processor;
	private Import_Target_Detector $target_detector;

	public function __construct(
		CSV_Service $csv_service,
		Import_Plan_Store $plan_store,
		File_Upload_Service $upload_service,
		Import_Plan_Builder $plan_builder,
		Import_Processor $import_processor,
		Import_Target_Detector $target_detector
	) {
		$this->csv_service       = $csv_service;
		$this->plan_store        = $plan_store;
		$this->upload_service    = $upload_service;
		$this->plan_builder      = $plan_builder;
		$this->import_processor  = $import_processor;
		$this->target_detector   = $target_detector;
	}

	public function prepare_import(): void {
		$this->extend_import_time_limit();

		try {
			// Import is intentionally automatic: the selected admin item must not force the import target.
			$selected_reference = '';
			$selected_scope     = '';
			$uploaded           = $this->upload_service->handle_import_uploads();
			$import_options     = $this->get_requested_import_options();

			if ( isset( $uploaded['error'] ) ) {
				wp_send_json_error( array( 'message' => $uploaded['error'] ), 400 );
			}

			$files            = $uploaded['files'] ?? array();
			$all_operations   = array();
			$all_warnings     = array();
			$total_rows       = 0;
			$update_count     = 0;
			$skipped_count    = 0;
			$last_target_ref  = '';
			$file_summaries   = array();
			$unmapped_columns = array();
			$rollback_rows     = array();
			$has_media_rename  = false;

			foreach ( $files as $file ) {
				$file_path = (string) $file['file'];
				try {
					$dataset = $this->csv_service->read_spreadsheet_dataset( $file_path );
				} finally {
					Temp_File_Service::delete( $file_path );
				}

				if ( empty( $dataset['headers'] ) ) {
					continue;
				}

				$mapping         = $this->csv_service->detect_column_mapping( array_values( $dataset['headers'] ) );
				$target_datasets = $this->target_detector->detect_target_datasets_from_dataset( $dataset, $mapping, $selected_reference, $selected_scope, sanitize_file_name( (string) $file['name'] ) );

				if ( empty( $target_datasets ) ) {
					$all_warnings[] = sprintf(
						/* translators: %s: uploaded filename. */
						__( 'Bestand %s kon niet automatisch aan een geldig item worden gekoppeld.', 'acf-page-text-manager' ),
						sanitize_file_name( (string) $file['name'] )
					);
					continue;
				}

				foreach ( $target_datasets as $target_dataset ) {
					$target         = isset( $target_dataset['target'] ) && is_array( $target_dataset['target'] ) ? $target_dataset['target'] : array();
					$target_dataset = isset( $target_dataset['dataset'] ) && is_array( $target_dataset['dataset'] ) ? $target_dataset['dataset'] : $dataset;

					if ( empty( $target['fields'] ) ) {
						continue;
					}

					$target['ignore_import_target_mismatch'] = true;
					$target['import_options'] = $import_options;

					$plan = $this->plan_builder->build(
						wp_generate_uuid4(),
						$target,
						$target_dataset,
						$mapping,
						sanitize_file_name( (string) $file['name'] )
					);

					$all_operations = array_merge( $all_operations, $plan['operations'] ?? array() );
					$total_rows    += (int) ( $plan['summary']['total_rows'] ?? 0 );
					$update_count  += (int) ( $plan['summary']['update_count'] ?? 0 );
					$skipped_count += (int) ( $plan['summary']['skipped_count'] ?? 0 );
					$all_warnings    = array_merge( $all_warnings, (array) ( $plan['summary']['warnings'] ?? array() ) );
					$unmapped_columns = array_merge( $unmapped_columns, (array) ( $plan['summary']['unmapped_columns'] ?? array() ) );
					$rollback_rows     = array_merge( $rollback_rows, (array) ( $plan['rollback_rows'] ?? array() ) );
					$has_media_rename  = $has_media_rename || $this->plan_has_media_rename_updates( $plan );
					$last_target_ref  = (string) ( $target['reference'] ?? $last_target_ref );

					$file_summaries[] = array(
						'file_name'        => (string) $file['name'],
						'target_title'     => (string) ( $target['title'] ?? '' ),
						'update_count'     => (int) ( $plan['summary']['update_count'] ?? 0 ),
						'skipped_count'    => (int) ( $plan['summary']['skipped_count'] ?? 0 ),
						'mapped_columns'   => (int) ( $plan['summary']['mapped_columns'] ?? 0 ),
						'unmapped_columns' => (array) ( $plan['summary']['unmapped_columns'] ?? array() ),
					);
				}
			}

			if ( empty( $all_operations ) ) {
				wp_send_json_error( array( 'message' => __( 'Er konden geen geldige importregels worden voorbereid.', 'acf-page-text-manager' ) ), 400 );
			}

			$token  = wp_generate_uuid4();
			$bundle = array(
				'token'                     => $token,
				'user_id'                   => get_current_user_id(),
				'file_name'                 => count( $file_summaries ) > 1 ? __( 'Meerdere bestanden', 'acf-page-text-manager' ) : (string) ( $file_summaries[0]['file_name'] ?? '' ),
				'target_title'              => count( $file_summaries ) > 1 ? __( 'Meerdere items', 'acf-page-text-manager' ) : (string) ( $file_summaries[0]['target_title'] ?? '' ),
				'operations'                => $all_operations,
				'import_options'            => $import_options,
				'cursor'                    => 0,
				'updated_count'             => 0,
				'error_messages'            => array(),
				'redirect_target_reference' => $last_target_ref,
				'summary'                   => array(
					'total_rows'       => $total_rows,
					'update_count'     => $update_count,
					'skipped_count'    => $skipped_count,
					'warnings'         => array_values( array_unique( $all_warnings ) ),
					'unmapped_columns' => array_values( array_unique( $unmapped_columns ) ),
				),
				'file_summaries'            => $file_summaries,
			);

			$bundle['rollback_rows'] = $rollback_rows;
			$bundle['import_allowed'] = true;


			if ( $has_media_rename && empty( $import_options['confirm_media_rename'] ) ) {
				$bundle['import_allowed'] = false;
				$bundle['summary']['warnings'][] = __( 'Deze import wijzigt één of meer media-bestandsnamen. Vink de bevestiging voor media-URL wijzigingen aan en start de import opnieuw.', 'acf-page-text-manager' );
			}

			$this->plan_store->save( $token, $bundle );

			wp_send_json_success(
				array(
					'token'         => $token,
					'file_name'     => $bundle['file_name'],
					'summary'       => $bundle['summary'],
					'target_title'  => $bundle['target_title'],
					'file_summaries' => $file_summaries,
					'can_run'       => ! empty( $bundle['import_allowed'] ),
					'rollback_url'  => ! empty( $rollback_rows ) ? Import_Plan_Store::build_rollback_download_url( $token ) : '',
				)
			);
		} catch ( \Throwable $exception ) {
			$error_message = sanitize_text_field( $exception->getMessage() );

			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: exception message. */
						__( 'Import voorbereiden mislukt: %s', 'acf-page-text-manager' ),
						$error_message
					),
				),
				500
			);
		}
	}


	private function extend_import_time_limit(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}
	}

	private function get_requested_import_options(): array {
		return array(
			'skip_empty_values'    => $this->request_has_enabled_checkbox( 'skip_empty_values' ),
			'overwrite_existing'   => $this->request_has_enabled_checkbox( 'overwrite_existing' ),
			'confirm_media_rename' => $this->request_has_enabled_checkbox( 'confirm_media_rename' ),
		);
	}

	private function request_has_enabled_checkbox( string $key ): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- AJAX nonce is verified by Admin_Actions before this controller method is called.
		$value = isset( $_POST[ $key ] ) ? sanitize_key( wp_unslash( $_POST[ $key ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return '1' === $value;
	}

	private function plan_has_media_rename_updates( array $plan ): bool {
		$operations = isset( $plan['operations'] ) && is_array( $plan['operations'] ) ? $plan['operations'] : array();
		foreach ( $operations as $operation ) {
			if ( ! is_array( $operation ) || 'update' !== (string) ( $operation['action'] ?? '' ) ) {
				continue;
			}

			if ( $this->operation_is_media_filename_update( $operation ) ) {
				return true;
			}
		}
		return false;
	}

	private function operation_is_media_filename_update( array $operation ): bool {
		$field_type = (string) ( $operation['field_type'] ?? '' );
		$field_name = (string) ( $operation['field_name'] ?? '' );

		return 'image_meta' === $field_type && str_ends_with( $field_name, '__file_name' );
	}

	public function process_import(): void {
		$this->extend_import_time_limit();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- AJAX nonce is verified in assert_ajax_permissions() before this value is read.
		$token  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$result = $this->import_processor->process( $token );

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ), (int) ( $result['status'] ?? 400 ) );
		}

		wp_send_json_success( $result );
	}

}
