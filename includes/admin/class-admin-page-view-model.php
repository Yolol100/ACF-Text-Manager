<?php
namespace WA_ACF_PTM\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Page_View_Model {
	private Page_Repository $repository;

	public function __construct( Page_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function build(): array {
		$targets            = $this->repository->get_targets_index();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filter value.
		$selected_reference = isset( $_GET['target'] ) ? sanitize_text_field( wp_unslash( $_GET['target'] ) ) : '';
		$filtered_targets   = $targets;
		$selected_target    = $this->resolve_selected_target( $filtered_targets, $selected_reference );
		$target_type_options = $this->repository->get_available_content_type_options( $filtered_targets );
		if ( empty( $target_type_options ) ) {
			$target_type_options = $this->repository->get_content_type_options();
		}
		$target_data        = ( '' !== $selected_reference && ! empty( $selected_target['reference'] ) ) ? $this->repository->get_target_data( (string) $selected_target['reference'] ) : array();
		$fields             = isset( $target_data['fields'] ) && is_array( $target_data['fields'] ) ? $target_data['fields'] : array();
		$active_providers   = $this->get_active_providers();
		$fields             = $this->filter_fields_by_active_providers( $fields, $active_providers );
		$export_fields      = $this->filter_import_export_fields( $fields );
		$export_targets     = $this->with_import_export_field_counts( $filtered_targets );
		$field_groups       = $this->group_fields( $fields, $active_providers );
		$field_filters      = $this->build_field_filters( $field_groups );
		$active_tab         = 'selected';

		return array(
			'targets'           => $export_targets,
			'all_targets'       => $targets,
			'selected_target'   => $selected_target,
			'target_data'       => $target_data,
			'fields'            => $fields,
			'export_fields'     => $export_fields,
			'field_groups'      => $field_groups,
			'field_filters'     => $field_filters,
			'active_tab'        => $active_tab,
			'target_count'      => count( $filtered_targets ),
			'target_type_options'=> $target_type_options,
			'tabs'              => array(
				'selected' => __( 'Item', 'acf-page-text-manager' ),
				'export'   => __( 'Export', 'acf-page-text-manager' ),
				'import'   => __( 'Import', 'acf-page-text-manager' ),
			),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $targets
	 * @return array<string,mixed>
	 */
	private function resolve_selected_target( array $targets, string $selected_reference ): array {
		if ( '' === $selected_reference ) {
			return array();
		}

		foreach ( $targets as $target ) {
			if ( (string) ( $target['reference'] ?? '' ) === $selected_reference ) {
				return $target;
			}
		}

		return array();
	}



	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array{label:string,fields:array<int,array<string,mixed>>}>
	 */
	private function group_fields( array $fields, array $active_providers ): array {
		$groups = array();
		foreach ( $fields as $field ) {
			$provider = $this->detect_field_provider( $field );
			if ( ! isset( $active_providers[ $provider ] ) ) {
				continue;
			}
			$label = $active_providers[ $provider ];
			if ( ! isset( $groups[ $provider ] ) ) {
				$groups[ $provider ] = array( 'label' => $label, 'fields' => array() );
			}
			$groups[ $provider ]['fields'][] = $field;
		}
		$ordered = array();
		foreach ( array_keys( $active_providers ) as $provider ) {
			if ( isset( $groups[ $provider ] ) ) {
				$ordered[] = $groups[ $provider ];
			}
		}
		return $ordered;
	}

	/**
	 * @param array<string,mixed> $field
	 */
	private function detect_field_provider( array $field ): string {
		$name = (string) ( $field['name'] ?? '' );
		$key  = (string) ( $field['key'] ?? '' );
		if ( 0 === strpos( $name, '_yoast_' ) ) {
			return 'yoast';
		}
		if ( 0 === strpos( $name, 'rank_math_' ) ) {
			return 'rankmath';
		}
		if ( 0 !== strpos( $key, 'special_' ) ) {
			return 'acf';
		}
		return 'wordpress';
	}

	/**
	 * @return array<string,string>
	 */
	private function get_active_providers(): array {
		$providers = array(
			'wordpress' => __( 'WordPress', 'acf-page-text-manager' ),
		);
		if ( function_exists( 'acf_get_field_groups' ) ) {
			$providers['acf'] = __( 'ACF', 'acf-page-text-manager' );
		}
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			$providers['yoast'] = __( 'Yoast SEO', 'acf-page-text-manager' );
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath\Helper' ) || class_exists( 'RankMath' ) ) {
			$providers['rankmath'] = __( 'Rank Math', 'acf-page-text-manager' );
		}
		return $providers;
	}

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @param array<string,string> $active_providers
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_fields_by_active_providers( array $fields, array $active_providers ): array {
		return array_values( array_filter(
			$fields,
			function ( array $field ) use ( $active_providers ): bool {
				return isset( $active_providers[ $this->detect_field_provider( $field ) ] );
			}
		) );
	}



	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_import_export_fields( array $fields ): array {
		return array_values( $fields );
	}

	/**
	 * @param array<int,array<string,mixed>> $targets
	 * @return array<int,array<string,mixed>>
	 */
	private function with_import_export_field_counts( array $targets ): array {
		return array_map(
			function ( array $target ): array {
				$target['import_export_field_count'] = (int) ( $target['field_count'] ?? 0 );
				return $target;
			},
			$targets
		);
	}


	/**
	 * @param array<int,array{label:string,fields:array<int,array<string,mixed>>}> $field_groups
	 * @return array<int,string>
	 */
	private function build_field_filters( array $field_groups ): array {
		$filters = array();

		foreach ( $field_groups as $group ) {
			$label = (string) ( $group['label'] ?? '' );
			if ( '' !== $label ) {
				$filters[] = $label;
			}
		}

		return array_values( array_unique( array_filter( array_map( 'strval', $filters ) ) ) );
	}

}
