<?php
namespace WA_ACF_PTM\Admin\Traits;

use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Page_Repository_ACF_Trait {
	private function find_options_page( string $option_post_id ): ?array {
		$pages = $this->get_registered_acf_options_pages();
		foreach ( $pages as $candidate ) {
			if ( (string) ( $candidate['post_id'] ?? '' ) === $option_post_id ) {
				return $candidate;
			}
		}

		if ( 'options' === $option_post_id ) {
			return array(
				'page_title' => $option_post_id,
				'menu_slug'  => $option_post_id,
				'post_id'    => $option_post_id,
			);
		}

		return null;
	}

	private function get_registered_acf_options_pages(): array {
		if ( function_exists( 'acf_get_options_pages' ) ) {
			$pages = acf_get_options_pages();
			return is_array( $pages ) ? array_values( $pages ) : array();
		}

		return array();
	}

	private function get_all_field_groups(): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return array();
		}

		$field_groups = acf_get_field_groups();
		return is_array( $field_groups ) ? $field_groups : array();
	}

	private function get_field_groups_for_post_id( $post_id ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return array();
		}

		$field_groups = acf_get_field_groups(
			array(
				'post_id' => $post_id,
			)
		);

		return is_array( $field_groups ) ? $field_groups : array();
	}

	private function get_field_groups_for_term( WP_Term $term ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return array();
		}

		$field_groups = acf_get_field_groups(
			array(
				'taxonomy' => $term->taxonomy,
				'post_id'  => 'term_' . $term->term_id,
			)
		);

		return is_array( $field_groups ) ? $field_groups : array();
	}

	private function get_field_groups_for_options_page( string $menu_slug ): array {
		$groups = array();

		foreach ( $this->get_all_field_groups() as $group ) {
			if ( empty( $group['location'] ) || ! is_array( $group['location'] ) ) {
				continue;
			}

			foreach ( $group['location'] as $location_group ) {
				if ( ! is_array( $location_group ) ) {
					continue;
				}

				foreach ( $location_group as $rule ) {
					if ( ! is_array( $rule ) || 'options_page' !== ( $rule['param'] ?? '' ) ) {
						continue;
					}

					if ( $menu_slug === (string) ( $rule['value'] ?? '' ) ) {
						$groups[] = $group;
						continue 3;
					}
				}
			}
		}

		return $groups;
	}

	private function collect_field_definitions_for_groups( array $field_groups ): array {
		return $this->collect_acf_fields_for_groups( $field_groups, 'definition' );
	}

	private function collect_acf_fields_for_groups( array $field_groups, string $mode, $post_id = null ): array {
		if ( ! function_exists( 'acf_get_fields' ) ) {
			return array();
		}

		if ( 'value' === $mode && ! function_exists( 'get_field' ) ) {
			return array();
		}

		$fields = array();
		foreach ( $field_groups as $group ) {
			if ( empty( $group['key'] ) ) {
				continue;
			}

			$group_fields = acf_get_fields( $group['key'] );
			if ( empty( $group_fields ) || ! is_array( $group_fields ) ) {
				continue;
			}

			foreach ( $group_fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				if ( 'value' === $mode ) {
					$this->collect_supported_fields_recursive( $field, $fields, $post_id );
				} else {
					$this->collect_supported_field_definitions_recursive( $field, $fields );
				}
			}
		}

		return $this->field_values->deduplicate_fields( $fields );
	}

	private function collect_supported_field_definitions_recursive( array $field, array &$collector ): void {
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';
		$name = isset( $field['name'] ) ? (string) $field['name'] : '';
		$key  = isset( $field['key'] ) ? (string) $field['key'] : '';

		if ( in_array( $type, $this->supported_field_types, true ) && '' !== $name && '' !== $key ) {
			if ( 'image' === $type ) {
				$collector = array_merge( $collector, $this->special_fields->build_image_meta_definitions( $name, $key, isset( $field['label'] ) ? (string) $field['label'] : $name ) );
			} else {
				$collector[] = array(
					'name'  => $name,
					'key'   => $key,
					'label' => isset( $field['label'] ) ? (string) $field['label'] : $name,
					'type'  => $type,
				);
			}
		}

		$this->walk_acf_child_fields(
			$field,
			function( array $sub_field ) use ( &$collector ): void {
				$this->collect_supported_field_definitions_recursive( $sub_field, $collector );
			}
		);
	}

	private function collect_fields_for_post_id( array $field_groups, $post_id ): array {
		return $this->collect_acf_fields_for_groups( $field_groups, 'value', $post_id );
	}

	private function collect_supported_fields_recursive( array $field, array &$collector, $post_id ): void {
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';
		$name = isset( $field['name'] ) ? (string) $field['name'] : '';
		$key  = isset( $field['key'] ) ? (string) $field['key'] : '';

		if ( in_array( $type, $this->supported_field_types, true ) && '' !== $name && '' !== $key ) {
			if ( 'image' === $type ) {
				$collector = array_merge( $collector, $this->special_fields->build_image_meta_fields( $name, $key, isset( $field['label'] ) ? (string) $field['label'] : $name, $post_id ) );
			} else {
				$value = $this->resolve_display_value( $name, $post_id, $key );
				$collector[] = array(
					'name'             => $name,
					'key'              => $key,
					'label'            => isset( $field['label'] ) ? (string) $field['label'] : $name,
					'type'             => $type,
					'raw_value'        => $value,
					'normalized_value' => $this->field_values->normalize_for_presence_check( $value ),
					'value_preview'    => $this->field_values->make_preview_text( $value ),
				);
			}
		}

		$this->walk_acf_child_fields(
			$field,
			function( array $sub_field ) use ( &$collector, $post_id ): void {
				$this->collect_supported_fields_recursive( $sub_field, $collector, $post_id );
			}
		);
	}

	private function walk_acf_child_fields( array $field, callable $callback ): void {
		if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			foreach ( $field['sub_fields'] as $sub_field ) {
				if ( is_array( $sub_field ) ) {
					$callback( $sub_field );
				}
			}
		}

		if ( 'flexible_content' !== (string) ( $field['type'] ?? '' ) || empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
			return;
		}

		foreach ( $field['layouts'] as $layout ) {
			if ( empty( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
				continue;
			}

			foreach ( $layout['sub_fields'] as $sub_field ) {
				if ( is_array( $sub_field ) ) {
					$callback( $sub_field );
				}
			}
		}
	}

	private function resolve_display_value( string $name, $post_id, string $key = '' ): string {
		$value = null;

		if ( function_exists( 'get_field' ) ) {
			if ( '' !== $key ) {
				$value = get_field( $key, $post_id, false );
			}
			if ( '' === $this->field_values->normalize_mixed_for_presence_check( $value ) ) {
				$value = get_field( $name, $post_id, false );
			}
			if ( '' === $this->field_values->normalize_mixed_for_presence_check( $value ) ) {
				$value = get_field( '' !== $key ? $key : $name, $post_id );
			}
		}

		if ( '' === $this->field_values->normalize_mixed_for_presence_check( $value ) ) {
			$value = $this->field_values->get_raw_meta_fallback( $name, $post_id );
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}


}
