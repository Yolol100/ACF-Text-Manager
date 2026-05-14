<?php
namespace WA_ACF_PTM\Admin;

use WA_ACF_PTM\Admin\Services\Temp_File_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Admin_Actions_Import_Trait {
	public function ajax_prepare_import(): void {
		$this->assert_ajax_permissions();

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
			$preview_rows     = array();
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
				$target_datasets = $this->detect_target_datasets_from_dataset( $dataset, $mapping, $selected_reference, $selected_scope, sanitize_file_name( (string) $file['name'] ) );

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
					$preview_rows     = array_merge( $preview_rows, (array) ( $plan['preview_rows'] ?? array() ) );
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
				$bundle['summary']['warnings'][] = __( 'Deze import wijzigt één of meer media-bestandsnamen. Vink de bevestiging voor media-URL wijzigingen aan en maak opnieuw een voorbeeld voordat je de import uitvoert.', 'acf-page-text-manager' );
			}

			$this->plan_store->save( $token, $bundle );

			wp_send_json_success(
				array(
					'token'         => $token,
					'file_name'     => $bundle['file_name'],
					'summary'       => $bundle['summary'],
					'target_title'  => $bundle['target_title'],
					'file_summaries' => $file_summaries,
					'preview_rows'  => array_slice( $preview_rows, 0, 50 ),
					'can_run'       => ! empty( $bundle['import_allowed'] ),
					'rollback_url'  => ! empty( $rollback_rows ) ? $this->get_rollback_download_url( $token ) : '',
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

	private function get_requested_import_options(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- AJAX nonce is verified before the options are used.
		$skip_empty_values    = isset( $_POST['skip_empty_values'] ) ? sanitize_key( wp_unslash( $_POST['skip_empty_values'] ) ) : '';
		$overwrite_existing   = isset( $_POST['overwrite_existing'] ) ? sanitize_key( wp_unslash( $_POST['overwrite_existing'] ) ) : '';
		$confirm_media_rename = isset( $_POST['confirm_media_rename'] ) ? sanitize_key( wp_unslash( $_POST['confirm_media_rename'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return array(
			'skip_empty_values'    => '1' === $skip_empty_values,
			'overwrite_existing'   => '1' === $overwrite_existing,
			'confirm_media_rename' => '1' === $confirm_media_rename,
		);
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

	public function ajax_process_import(): void {
		$this->assert_ajax_permissions();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- AJAX nonce is verified in assert_ajax_permissions() before this value is read.
		$token  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$result = $this->import_processor->process( $token );

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ), (int) ( $result['status'] ?? 400 ) );
		}

		wp_send_json_success( $result );
	}


	/**
	 * Build one import dataset per matched target. This lets one CSV update multiple
	 * items when each row contains target_title/page_title/target_slug data, while
	 * still supporting filename-based matching for a single CSV or ZIP entry.
	 *
	 * @return array<int,array{target:array<string,mixed>,dataset:array<string,mixed>}>
	 */
	private function detect_target_datasets_from_dataset( array $dataset, array $mapping, string $selected_reference, string $selected_scope = '', string $file_name = '' ): array {
		if ( '' !== $selected_reference ) {
			$target = $this->detect_target_from_dataset( $dataset, $mapping, $selected_reference, $selected_scope, $file_name );
			return ! empty( $target['fields'] ) ? array( array( 'target' => $target, 'dataset' => $dataset ) ) : array();
		}

		$rows           = $this->csv_service->map_dataset_rows( $dataset, $mapping );
		$detected_scope = $this->infer_dataset_content_scope( $dataset, $mapping );
		$scope_filter   = '' !== $selected_scope ? $selected_scope : $detected_scope;
		$groups         = array();
		$source_rows    = isset( $dataset['rows'] ) && is_array( $dataset['rows'] ) ? array_values( $dataset['rows'] ) : array();

		foreach ( $rows as $row ) {
			$target = $this->find_target_by_row_identity( $row, $scope_filter, $file_name );
			if ( empty( $target['fields'] ) ) {
				continue;
			}

			$reference = (string) ( $target['reference'] ?? '' );
			if ( '' === $reference ) {
				continue;
			}

			$source_row_index = max( 0, (int) ( $row['row_index'] ?? 2 ) - 2 );
			if ( ! isset( $source_rows[ $source_row_index ] ) ) {
				continue;
			}

			if ( ! isset( $groups[ $reference ] ) ) {
				$groups[ $reference ] = array(
					'target'      => $target,
					'row_indexes' => array(),
				);
			}

			$groups[ $reference ]['row_indexes'][ $source_row_index ] = true;
		}

		if ( empty( $groups ) ) {
			$target = $this->detect_target_from_dataset( $dataset, $mapping, '', $selected_scope, $file_name );
			return ! empty( $target['fields'] ) ? array( array( 'target' => $target, 'dataset' => $dataset ) ) : array();
		}

		$target_datasets = array();
		foreach ( $groups as $group ) {
			$row_indexes = array_keys( (array) ( $group['row_indexes'] ?? array() ) );
			sort( $row_indexes, SORT_NUMERIC );

			$subset_rows = array();
			foreach ( $row_indexes as $row_index ) {
				if ( isset( $source_rows[ $row_index ] ) ) {
					$subset_rows[] = $source_rows[ $row_index ];
				}
			}

			if ( empty( $subset_rows ) ) {
				continue;
			}

			$target_datasets[] = array(
				'target'  => (array) $group['target'],
				'dataset' => array(
					'headers' => isset( $dataset['headers'] ) && is_array( $dataset['headers'] ) ? array_values( $dataset['headers'] ) : array(),
					'rows'    => $subset_rows,
				),
			);
		}

		return $target_datasets;
	}

	private function detect_target_from_dataset( array $dataset, array $mapping, string $selected_reference, string $selected_scope = '', string $file_name = '' ): array {

		$detected_scope = $this->infer_dataset_content_scope( $dataset, $mapping );
		$scope_filter   = '' !== $selected_scope ? $selected_scope : $detected_scope;

		if ( '' !== $selected_reference ) {
			$selected_target = $this->repository->get_target_data( $selected_reference );
			if ( ! empty( $selected_target['fields'] ) ) {
				if ( '' !== $scope_filter && (string) ( $selected_target['content_scope'] ?? '' ) !== $scope_filter ) {
					return array();
				}

				$selected_target['ignore_import_target_mismatch'] = true;
				return $selected_target;
			}
		}

		// Match by real identity only (content scope + title/slug/file name).
		// Do not fall back to numeric IDs: IDs often differ between sites, so an ID-only match can update the wrong item.
		$matched_target = $this->find_target_by_dataset_identity( $dataset, $mapping, $scope_filter, $file_name );
		if ( ! empty( $matched_target['fields'] ) ) {
			$matched_target['ignore_import_target_mismatch'] = true;
			return $matched_target;
		}

		return array();
	}

	private function find_target_by_row_identity( array $row, string $selected_scope = '', string $file_name = '' ): array {
		$candidates = array();

		foreach ( array( 'target_title', 'target_slug', 'page_title' ) as $key ) {
			$value = trim( (string) ( $row[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				$candidates[] = $value;
			}
		}

		if ( empty( $candidates ) && '' !== $file_name ) {
			$stem = preg_replace( '/\.[^.]+$/', '', $file_name );
			$stem = str_replace( array( '_', '-' ), ' ', (string) $stem );
			$stem = preg_replace( '/\s+/', ' ', (string) $stem );
			$stem = trim( (string) $stem );
			if ( '' !== $stem ) {
				$candidates[] = $stem;
			}
		}

		return $this->find_target_by_identity_candidates( $candidates, $selected_scope );
	}

	private function find_target_by_identity_candidates( array $candidates, string $selected_scope = '' ): array {
		$candidates = array_values( array_unique( array_filter( array_map( 'trim', $candidates ) ) ) );
		if ( empty( $candidates ) ) {
			return array();
		}

		$targets = $this->repository->get_targets_index();
		$matches = array();
		foreach ( $targets as $target ) {
			$scope = (string) ( $target['content_scope'] ?? '' );
			if ( '' !== $selected_scope && $scope !== $selected_scope ) {
				continue;
			}

			$title = trim( (string) ( $target['title'] ?? '' ) );
			$slug  = trim( (string) ( $target['slug'] ?? '' ) );
			$haystacks = array_values( array_filter( array( sanitize_title( $title ), sanitize_title( $slug ) ) ) );
			$score = 0;

			foreach ( $candidates as $candidate ) {
				$needle = sanitize_title( $candidate );
				if ( '' === $needle ) {
					continue;
				}

				if ( in_array( $needle, $haystacks, true ) ) {
					$score = max( $score, 120 );
					continue;
				}

				// Partial title or slug matches are intentionally not auto-selected; exact matches only.
			}

			if ( $score > 0 ) {
				$matches[] = array(
					'score'     => $score,
					'reference' => (string) ( $target['reference'] ?? '' ),
				);
			}
		}

		if ( empty( $matches ) ) {
			return array();
		}

		usort(
			$matches,
			static function( array $a, array $b ): int {
				return (int) $b['score'] <=> (int) $a['score'];
			}
		);

		if ( count( $matches ) > 1 && (int) $matches[0]['score'] === (int) $matches[1]['score'] ) {
			return array();
		}

		$target = $this->repository->get_target_data( (string) $matches[0]['reference'] );
		return ! empty( $target['fields'] ) ? $target : array();
	}

	private function find_target_by_dataset_identity( array $dataset, array $mapping, string $selected_scope = '', string $file_name = '' ): array {
		$rows       = $this->csv_service->map_dataset_rows( $dataset, $mapping );
		$candidates = $this->get_dataset_identity_candidates( $rows, $file_name );

		if ( empty( $candidates ) ) {
			return array();
		}

		$targets = $this->repository->get_targets_index();
		$matches = array();
		foreach ( $targets as $target ) {
			$scope = (string) ( $target['content_scope'] ?? '' );
			if ( '' !== $selected_scope && $scope !== $selected_scope ) {
				continue;
			}

			$title = trim( (string) ( $target['title'] ?? '' ) );
			$slug  = trim( (string) ( $target['slug'] ?? '' ) );
			$haystacks = array_values( array_filter( array( sanitize_title( $title ), sanitize_title( $slug ) ) ) );
			$score = 0;

			foreach ( $candidates as $candidate ) {
				$needle = sanitize_title( $candidate );
				if ( '' === $needle ) {
					continue;
				}

				if ( in_array( $needle, $haystacks, true ) ) {
					$score = max( $score, 120 );
					continue;
				}

				// Partial title or slug matches are intentionally not auto-selected; exact matches only.
			}

			if ( $score > 0 ) {
				$matches[] = array(
					'score'     => $score,
					'reference' => (string) ( $target['reference'] ?? '' ),
				);
			}
		}

		if ( empty( $matches ) ) {
			return array();
		}

		usort(
			$matches,
			static function( array $a, array $b ): int {
				return (int) $b['score'] <=> (int) $a['score'];
			}
		);

		if ( count( $matches ) > 1 && (int) $matches[0]['score'] === (int) $matches[1]['score'] ) {
			return array();
		}

		$target = $this->repository->get_target_data( (string) $matches[0]['reference'] );
		return ! empty( $target['fields'] ) ? $target : array();
	}


	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<int,string>
	 */
	private function get_dataset_identity_candidates( array $rows, string $file_name = '' ): array {
		$candidates = array();

		foreach ( $rows as $row ) {
			foreach ( array( 'target_title', 'target_slug', 'page_title' ) as $key ) {
				$value = trim( (string) ( $row[ $key ] ?? '' ) );
				if ( '' !== $value ) {
					$candidates[] = $value;
				}
			}
		}

		if ( '' !== $file_name ) {
			$stem = preg_replace( '/\.[^.]+$/', '', $file_name );
			$stem = str_replace( array( '_', '-' ), ' ', (string) $stem );
			$stem = preg_replace( '/\s+/', ' ', (string) $stem );
			$stem = trim( (string) $stem );
			if ( '' !== $stem ) {
				$candidates[] = $stem;
			}
		}

		return array_values( array_unique( array_filter( array_map( 'trim', $candidates ) ) ) );
	}

	private function infer_dataset_content_scope( array $dataset, array $mapping ): string {
		$rows = $this->csv_service->map_dataset_rows( $dataset, $mapping );
		foreach ( $rows as $row ) {
			$scope = sanitize_key( (string) ( $row['content_scope'] ?? '' ) );
			if ( in_array( $scope, array( 'page', 'post' ), true ) ) {
				return $scope;
			}

			$type = sanitize_key( (string) ( $row['target_type'] ?? '' ) );
			if ( in_array( $type, array( 'page', 'post' ), true ) ) {
				return $type;
			}
		}

		return '';
	}

}
