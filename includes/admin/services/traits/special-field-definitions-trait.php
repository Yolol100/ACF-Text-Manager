<?php
namespace WA_ACF_PTM\Admin\Services;

use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Special_Field_Definitions_Trait {

	public function get_post_target_label( WP_Post $post ): string {
		$post_type_object = get_post_type_object( $post->post_type );
		return isset( $post_type_object->labels->singular_name ) ? (string) $post_type_object->labels->singular_name : ucfirst( (string) $post->post_type );
	}

	public function get_term_target_label( WP_Term $term ): string {
		$taxonomy = get_taxonomy( $term->taxonomy );
		return isset( $taxonomy->labels->singular_name ) ? (string) $taxonomy->labels->singular_name : ucfirst( (string) $term->taxonomy );
	}

	private function is_yoast_active(): bool {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
	}

	private function is_rank_math_active(): bool {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath\\Helper' ) || class_exists( 'RankMath' );
	}

	public function get_special_post_field_definitions( ?WP_Post $post = null ): array {
		$fields = array(
			array( 'name' => 'post_title', 'key' => 'special_post_title', 'label' => __( 'Titel', 'acf-page-text-manager' ), 'type' => 'text' ),
			array( 'name' => 'post_excerpt', 'key' => 'special_post_excerpt', 'label' => __( 'Excerpt / samenvatting', 'acf-page-text-manager' ), 'type' => 'textarea' ),
		);

		if ( $this->is_yoast_active() ) {
			foreach ( self::YOAST_META_KEYS as $meta_key => $meta ) {
				$fields[] = array(
					'name'  => $meta_key,
					'key'   => 'special_post_yoast_' . sanitize_key( str_replace( '_yoast_wpseo_', '', $meta_key ) ),
					'label' => $this->get_yoast_meta_label( (string) $meta_key ),
					'type'  => $meta['type'],
				);
			}
		}

		if ( $this->is_rank_math_active() ) {
			foreach ( self::RANK_MATH_META_KEYS as $meta_key => $meta ) {
				$fields[] = array(
					'name'  => $meta_key,
					'key'   => 'special_post_' . sanitize_key( $meta_key ),
					'label' => $this->get_rank_math_meta_label( (string) $meta_key ),
					'type'  => $meta['type'],
				);
			}
		}

		$fields[] = array( 'name' => '__featured_image', 'key' => 'special_featured_image', 'label' => __( 'Uitgelichte afbeelding', 'acf-page-text-manager' ), 'type' => 'image' );
		foreach ( self::IMAGE_META_KEYS as $meta_key ) {
			$field = array(
				'name'  => '__featured_image__' . $meta_key,
				'key'   => 'special_featured_image__' . $meta_key,
				'label' => $this->get_image_meta_label( __( 'Uitgelichte afbeelding', 'acf-page-text-manager' ), $meta_key ),
				'type'  => 'image_meta',
			);
			$fields[] = $field;
		}

		return $this->values->deduplicate_fields( $fields );
	}

	public function get_special_term_field_definitions( ?WP_Term $term = null ): array {
		$fields = array(
			array( 'name' => 'name', 'key' => 'special_term_name', 'label' => __( 'Naam', 'acf-page-text-manager' ), 'type' => 'text' ),
			array( 'name' => 'slug', 'key' => 'special_term_slug', 'label' => __( 'Slug', 'acf-page-text-manager' ), 'type' => 'text' ),
			array( 'name' => 'description', 'key' => 'special_term_description', 'label' => __( 'Beschrijving', 'acf-page-text-manager' ), 'type' => 'textarea' ),
		);

		return $fields;
	}

	private function get_yoast_meta_label( string $meta_key ): string {
		switch ( $meta_key ) {
			case '_yoast_wpseo_focuskw':
				return __( 'Yoast keyphrase', 'acf-page-text-manager' );
			case '_yoast_wpseo_title':
				return __( 'Yoast meta title', 'acf-page-text-manager' );
			case '_yoast_wpseo_metadesc':
				return __( 'Yoast meta description', 'acf-page-text-manager' );
			case '_yoast_wpseo_canonical':
				return __( 'Yoast canonical URL', 'acf-page-text-manager' );
			case '_yoast_wpseo_opengraph-title':
				return __( 'Yoast Open Graph title', 'acf-page-text-manager' );
			case '_yoast_wpseo_opengraph-description':
				return __( 'Yoast Open Graph description', 'acf-page-text-manager' );
			case '_yoast_wpseo_twitter-title':
				return __( 'Yoast Twitter title', 'acf-page-text-manager' );
			case '_yoast_wpseo_twitter-description':
				return __( 'Yoast Twitter description', 'acf-page-text-manager' );
			default:
				return __( 'Yoast SEO veld', 'acf-page-text-manager' );
		}
	}

	private function get_rank_math_meta_label( string $meta_key ): string {
		switch ( $meta_key ) {
			case 'rank_math_title':
				return __( 'Rank Math title', 'acf-page-text-manager' );
			case 'rank_math_description':
				return __( 'Rank Math description', 'acf-page-text-manager' );
			case 'rank_math_focus_keyword':
				return __( 'Rank Math focus keyword', 'acf-page-text-manager' );
			case 'rank_math_canonical_url':
				return __( 'Rank Math canonical URL', 'acf-page-text-manager' );
			default:
				return __( 'Rank Math veld', 'acf-page-text-manager' );
		}
	}

	public function get_special_post_fields( WP_Post $post ): array {
		$fields = array();
		foreach ( $this->get_special_post_field_definitions( $post ) as $field ) {
			if ( 0 === strpos( (string) $field['name'], '__taxonomy__' ) && ! taxonomy_exists( substr( (string) $field['name'], 12 ) ) ) {
				continue;
			}
			$value = $this->get_special_post_value( $post, (string) $field['name'] );
			$fields[] = array(
				'name' => (string) $field['name'], 'key' => (string) $field['key'], 'label' => (string) $field['label'], 'type' => (string) $field['type'],
				'raw_value' => $value, 'normalized_value' => $this->values->normalize_for_presence_check( $this->values->stringify_value( $value ) ), 'value_preview' => $this->values->make_preview_text( $this->values->stringify_value( $value ) ),
			);
		}
		return $this->values->deduplicate_fields( $fields );
	}

	public function get_special_term_fields( WP_Term $term ): array {
		$fields = array();
		foreach ( $this->get_special_term_field_definitions( $term ) as $field ) {
			$value = $this->get_special_term_value( $term, (string) $field['name'] );
			$fields[] = array(
				'name' => (string) $field['name'], 'key' => (string) $field['key'], 'label' => (string) $field['label'], 'type' => (string) $field['type'],
				'raw_value' => $value, 'normalized_value' => $this->values->normalize_for_presence_check( $this->values->stringify_value( $value ) ), 'value_preview' => $this->values->make_preview_text( $this->values->stringify_value( $value ) ),
			);
		}
		return $fields;
	}

	public function build_image_meta_fields( string $field_name, string $field_key, string $field_label, $acf_post_id ): array {
		$attachment_id = $this->resolve_acf_image_attachment_id( $field_name, $acf_post_id );
		$fields = array(
			array(
				'name' => $field_name,
				'key' => $field_key,
				'label' => $field_label,
				'type' => 'image',
				'raw_value' => $attachment_id,
				'normalized_value' => $this->values->normalize_for_presence_check( (string) $attachment_id ),
				'value_preview' => $this->values->make_preview_text( (string) $attachment_id ),
			),
		);
		foreach ( self::IMAGE_META_KEYS as $meta_key ) {
			$value = $this->get_attachment_meta_value( $attachment_id, $meta_key );
			$field = array(
				'name' => '__acf_image__' . $field_name . '__' . $meta_key,
				'key' => 'special_image_meta__' . $field_key . '__' . $meta_key,
				'label' => $this->get_image_meta_label( $field_label, $meta_key ),
				'type' => 'image_meta',
				'raw_value' => $value,
				'normalized_value' => $this->values->normalize_for_presence_check( $value ),
				'value_preview' => $this->values->make_preview_text( $value ),
			);
			$fields[] = $field;
		}
		return $fields;
	}

	public function build_image_meta_definitions( string $field_name, string $field_key, string $field_label ): array {
		$defs = array(
			array( 'name' => $field_name, 'key' => $field_key, 'label' => $field_label, 'type' => 'image' ),
		);
		foreach ( self::IMAGE_META_KEYS as $meta_key ) {
			$def = array(
				'name' => '__acf_image__' . $field_name . '__' . $meta_key,
				'key' => 'special_image_meta__' . $field_key . '__' . $meta_key,
				'label' => $this->get_image_meta_label( $field_label, $meta_key ),
				'type' => 'image_meta',
			);
			$defs[] = $def;
		}
		return $defs;
	}

}
