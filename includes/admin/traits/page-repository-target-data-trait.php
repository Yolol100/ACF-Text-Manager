<?php
namespace WA_ACF_PTM\Admin;

use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Page_Repository_Target_Data_Trait {
	public function get_target_data( string $target_reference ): array {
		$target_reference = sanitize_text_field( $target_reference );
		if ( '' === $target_reference ) {
			return $this->get_empty_target_data();
		}

		$cache_key = self::DETAIL_CACHE_PREFIX . md5( $target_reference );
		$cache     = get_transient( $cache_key );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		$parts = explode( ':', $target_reference, 3 );
		$type  = $parts[0] ?? '';
		$id    = $parts[1] ?? '';
		$extra = $parts[2] ?? '';

		if ( 'page' === $type || 'post' === $type ) {
			$data = $this->get_post_data( absint( $id ) );
		} elseif ( 'term' === $type && '' !== $id && '' !== $extra ) {
			$data = $this->get_term_data( sanitize_key( $id ), absint( $extra ) );
		} elseif ( 'option' === $type && '' !== $id ) {
			$data = $this->get_option_target_data( $id );
		} else {
			$data = $this->get_empty_target_data();
		}

		if ( ! empty( $data['reference'] ) ) {
			set_transient( $cache_key, $data, HOUR_IN_SECONDS );
		}

		return $data;
	}

	public function get_post_data( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return $this->get_empty_target_data();
		}

		$summary      = $this->get_post_summary( $post );
		$field_groups = $this->get_field_groups_for_post_id( $post_id );
		$fields       = $this->collect_fields_for_post_id( $field_groups, $post_id );
		$fields       = array_merge( $fields, $this->special_fields->get_special_post_fields( $post ) );
		$fields       = $this->field_values->deduplicate_fields( $fields );

		$summary['acf_post_id']    = $post_id;
		$summary['fields']         = $fields;
		$summary['filled_fields']  = $this->field_values->filter_filled_fields( $fields );
		$summary['field_count']    = count( $fields );
		$summary['filled_count']   = count( $summary['filled_fields'] );
		$summary['has_content']    = $summary['filled_count'] > 0;
		$summary['source_summary'] = $this->special_fields->get_post_target_label( $post );

		return $summary;
	}

	public function get_term_data( string $taxonomy, int $term_id ): array {
		$term = get_term( $term_id, $taxonomy );

		if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
			return $this->get_empty_target_data();
		}

		$summary      = $this->get_term_summary( $term );
		$field_groups = $this->get_field_groups_for_term( $term );
		$fields       = $this->collect_fields_for_post_id( $field_groups, 'term_' . $term->term_id );
		$fields       = array_merge( $fields, $this->special_fields->get_special_term_fields( $term ) );
		$fields       = $this->field_values->deduplicate_fields( $fields );

		$summary['acf_post_id']    = 'term_' . $term->term_id;
		$summary['fields']         = $fields;
		$summary['filled_fields']  = $this->field_values->filter_filled_fields( $fields );
		$summary['field_count']    = count( $fields );
		$summary['filled_count']   = count( $summary['filled_fields'] );
		$summary['has_content']    = $summary['filled_count'] > 0;
		$summary['source_summary'] = $summary['target_label'];

		return $summary;
	}

	public function get_option_target_data( string $option_post_id ): array {
		if ( '' === $option_post_id ) {
			return $this->get_empty_target_data();
		}

		$page = $this->find_options_page( $option_post_id );
		if ( ! is_array( $page ) ) {
			return $this->get_empty_target_data();
		}

		$summary      = $this->get_option_summary( $option_post_id, $page );
		$field_groups = $this->get_field_groups_for_options_page( (string) ( $page['menu_slug'] ?? '' ) );
		$fields       = $this->collect_fields_for_post_id( $field_groups, $option_post_id );

		$summary['acf_post_id']    = $option_post_id;
		$summary['fields']         = $fields;
		$summary['filled_fields']  = $this->field_values->filter_filled_fields( $fields );
		$summary['field_count']    = count( $fields );
		$summary['filled_count']   = count( $summary['filled_fields'] );
		$summary['has_content']    = $summary['filled_count'] > 0;
		$summary['source_summary'] = __( 'Optiepagina', 'acf-page-text-manager' );

		return $summary;
	}

	private function get_empty_target_data(): array {
		return array(
			'reference'      => '',
			'target_type'    => '',
			'target_id'      => '',
			'acf_post_id'    => 0,
			'id'             => '',
			'title'          => '',
			'slug'           => '',
			'edit_url'       => '',
			'language_code'  => '',
			'target_label'   => '',
			'content_scope'  => '',
			'fields'         => array(),
			'filled_fields'  => array(),
			'field_count'    => 0,
			'acf_field_count'=> 0,
			'filled_count'   => 0,
			'has_content'    => false,
			'source_summary' => '',
		);
	}
}
