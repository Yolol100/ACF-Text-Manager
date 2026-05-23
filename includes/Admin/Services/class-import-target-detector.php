<?php
namespace WA_ACF_PTM\Admin\Services;

use WA_ACF_PTM\Admin\CSV_Service;
use WA_ACF_PTM\Admin\Page_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Import_Target_Detector {
	private CSV_Service $csv_service;
	private Page_Repository $repository;

	public function __construct( CSV_Service $csv_service, Page_Repository $repository ) {
		$this->csv_service = $csv_service;
		$this->repository  = $repository;
	}

	public function detect_target_datasets_from_dataset( array $dataset, array $mapping, string $selected_reference, string $selected_scope = '', string $file_name = '' ): array {
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
		$candidates = $this->get_row_identity_candidates( $row );

		if ( empty( $candidates ) ) {
			$candidates = $this->get_file_name_identity_candidates( $file_name );
		}

		return $this->find_target_by_identity_candidates( $candidates, $selected_scope );
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<int,string>
	 */
	private function get_row_identity_candidates( array $row ): array {
		$candidates = array();

		foreach ( array( 'target_title', 'target_slug', 'page_title' ) as $key ) {
			$value = trim( (string) ( $row[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				$candidates[] = $value;
			}
		}

		return $candidates;
	}

	/**
	 * @return array<int,string>
	 */
	private function get_file_name_identity_candidates( string $file_name ): array {
		$stem = $this->normalize_file_name_identity_candidate( $file_name );

		return '' !== $stem ? array( $stem ) : array();
	}

	private function normalize_file_name_identity_candidate( string $file_name ): string {
		if ( '' === $file_name ) {
			return '';
		}

		$stem = preg_replace( '/\.[^.]+$/', '', $file_name );
		$stem = str_replace( array( '_', '-' ), ' ', (string) $stem );
		$stem = preg_replace( '/\s+/', ' ', (string) $stem );

		return trim( (string) $stem );
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

		return $this->find_target_by_identity_candidates( $candidates, $selected_scope );
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

		$stem = $this->normalize_file_name_identity_candidate( $file_name );
		if ( '' !== $stem ) {
			$candidates[] = $stem;
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
