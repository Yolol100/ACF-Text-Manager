<?php
namespace WA_ACF_PTM\Admin\Traits;

use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Page_Repository_Summary_Trait {
	private function get_post_summary( WP_Post $post ): array {
		$field_groups    = $this->get_field_groups_for_post_id( (int) $post->ID );
		$acf_field_defs  = $this->field_values->deduplicate_fields( $this->collect_field_definitions_for_groups( $field_groups ) );
		$acf_field_count = count( $acf_field_defs );
		$field_defs      = array_merge( $acf_field_defs, $this->special_fields->get_special_post_field_definitions( $post ) );
		$field_defs      = $this->field_values->deduplicate_fields( $field_defs );
		$field_count     = count( $field_defs );
		// Keep the target index lightweight; detailed values are resolved only after selecting an item.
		$filled_count   = 0;
		$content_scope  = $this->get_post_content_scope( $post );
		$reference_type = 'page' === $post->post_type ? 'page' : 'post';

		return array(
			'reference'      => $reference_type . ':' . $post->ID,
			'target_type'    => $reference_type,
			'target_id'      => (string) $post->ID,
			'acf_post_id'    => (int) $post->ID,
			'id'             => (int) $post->ID,
			'title'          => get_the_title( $post->ID ),
			'slug'           => $post->post_name,
			'edit_url'       => get_edit_post_link( $post->ID, 'raw' ),
			'language_code'  => $this->detect_language_code( (int) $post->ID ),
			'target_label'   => $this->special_fields->get_post_target_label( $post ),
			'content_scope'  => $content_scope,
			'fields'         => array(),
			'filled_fields'  => array(),
			'field_count'    => $field_count,
			'acf_field_count'=> $acf_field_count,
			'filled_count'   => $filled_count,
			'has_content'    => $filled_count > 0,
			'source_summary' => $this->special_fields->get_post_target_label( $post ),
		);
	}

	private function get_term_summary( WP_Term $term ): array {
		$field_groups    = $this->get_field_groups_for_term( $term );
		$acf_field_defs  = $this->field_values->deduplicate_fields( $this->collect_field_definitions_for_groups( $field_groups ) );
		$acf_field_count = count( $acf_field_defs );
		$field_defs      = array_merge( $acf_field_defs, $this->special_fields->get_special_term_field_definitions( $term ) );
		$field_defs      = $this->field_values->deduplicate_fields( $field_defs );
		$field_count     = count( $field_defs );
		// Keep the target index lightweight; detailed values are resolved only after selecting an item.
		$filled_count = 0;
		$label        = $this->special_fields->get_term_target_label( $term );
		$content_scope = $this->get_term_content_scope( $term );

		return array(
			'reference'      => 'term:' . $term->taxonomy . ':' . $term->term_id,
			'target_type'    => 'term',
			'target_id'      => $term->taxonomy . ':' . $term->term_id,
			'acf_post_id'    => 'term_' . $term->term_id,
			'id'             => (int) $term->term_id,
			'title'          => $term->name,
			'slug'           => $term->slug,
			'edit_url'       => get_edit_term_link( $term, $term->taxonomy, '' ) ?: '',
			'language_code'  => '',
			'target_label'   => $label,
			'content_scope'  => $content_scope,
			'fields'         => array(),
			'filled_fields'  => array(),
			'field_count'    => $field_count,
			'acf_field_count'=> $acf_field_count,
			'filled_count'   => $filled_count,
			'has_content'    => $filled_count > 0,
			'source_summary' => $label,
		);
	}

	private function get_option_summary( string $option_post_id, array $page ): array {
		$field_groups = $this->get_field_groups_for_options_page( (string) ( $page['menu_slug'] ?? '' ) );
		$field_defs   = $this->collect_field_definitions_for_groups( $field_groups );
		$field_count  = count( $field_defs );
		// Keep the target index lightweight; detailed values are resolved only after selecting an item.
		$filled_count = 0;

		return array(
			'reference'      => 'option:' . $option_post_id,
			'target_type'    => 'option',
			'target_id'      => $option_post_id,
			'acf_post_id'    => $option_post_id,
			'id'             => $option_post_id,
			'title'          => (string) ( $page['page_title'] ?? $option_post_id ),
			'slug'           => (string) ( $page['menu_slug'] ?? $option_post_id ),
			'edit_url'       => isset( $page['menu_slug'] ) ? add_query_arg( 'page', sanitize_key( (string) $page['menu_slug'] ), admin_url( 'admin.php' ) ) : '',
			'language_code'  => '',
			'target_label'   => __( 'Optiepagina', 'acf-page-text-manager' ),
			'content_scope'  => 'option',
			'fields'         => array(),
			'filled_fields'  => array(),
			'field_count'    => $field_count,
			'filled_count'   => $filled_count,
			'has_content'    => $filled_count > 0,
			'source_summary' => __( 'ACF-optiepagina', 'acf-page-text-manager' ),
		);
	}

	private function get_post_content_scope( WP_Post $post ): string {
		if ( 'page' === $post->post_type ) {
			return 'page';
		}

		if ( 'post' === $post->post_type ) {
			return 'post';
		}

		return '';
	}

	private function get_term_content_scope( WP_Term $term ): string {
		return '';
	}

	public function get_content_type_options(): array {
		return array(
			'post' => __( 'Bericht', 'acf-page-text-manager' ),
			'page' => __( 'Pagina', 'acf-page-text-manager' ),
		);
	}


	private function detect_language_code( int $post_id ): string {
		if ( function_exists( 'pll_get_post_language' ) ) {
			$language = pll_get_post_language( $post_id, 'slug' );
			if ( is_string( $language ) ) {
				return $language;
			}
		}

		if ( has_filter( 'wpml_post_language_details' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party WPML hook name.
				$details = apply_filters( 'wpml_post_language_details', null, $post_id );
			if ( is_array( $details ) && ! empty( $details['language_code'] ) ) {
				return (string) $details['language_code'];
			}
		}

		return '';
	}
}
