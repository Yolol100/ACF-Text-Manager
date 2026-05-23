<?php
namespace WA_ACF_PTM\Admin\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Field_Value_Service {
	public function sanitize_by_type( string $value, string $field_type ): mixed {
		$field_type = (string) $field_type;
		$value      = wp_unslash( $value );
		$decoded    = $this->decode_json_value( $value );

		switch ( $field_type ) {
			case 'true_false':
				return $this->normalize_bool_value( $value );
			case 'number':
			case 'range':
				return $this->normalize_numeric_value( $value );
			case 'checkbox':
			case 'gallery':
			case 'relationship':
			case 'taxonomy':
			case 'user':
			case 'post_object':
				return $this->normalize_list_value( $decoded ?? $value );
			case 'image':
			case 'file':
				return $this->normalize_media_value( $decoded ?? $value );
			case 'link':
				return $this->normalize_link_value( $decoded ?? $value );
			case 'google_map':
				return $this->normalize_map_value( $decoded ?? $value );
			case 'group':
			case 'repeater':
			case 'flexible_content':
			case 'clone':
				return null !== $decoded ? $this->sanitize_deep_value( $decoded ) : array();
			case 'wysiwyg':
				return wp_kses_post( $value );
			case 'textarea':
			case 'message':
				return sanitize_textarea_field( $value );
			case 'oembed':
				return esc_url_raw( $value );
			case 'image_meta':
				return (string) $value;
			case 'email':
				return sanitize_email( $value );
			case 'url':
			case 'page_link':
				return esc_url_raw( $value );
			case 'date_picker':
				return $this->normalize_date_value( $value );
			case 'date_time_picker':
				return $this->normalize_datetime_value( $value );
			case 'time_picker':
				return $this->normalize_time_value( $value );
			case 'color_picker':
				return $this->normalize_color_value( $value );
			case 'password':
			case 'text':
			case 'radio':
			case 'button_group':
			case 'select':
			default:
				return sanitize_text_field( $value );
		}
	}

	public function get_current_field_value( string $field_name, $acf_post_id, string $field_key = '' ): string {
		$value = null;

		if ( function_exists( 'get_field' ) ) {
			if ( '' !== $field_key ) {
				$value = get_field( $field_key, $acf_post_id, false );
			}
			if ( '' === $this->normalize_mixed_value( $value ) ) {
				$value = get_field( $field_name, $acf_post_id, false );
			}
			if ( '' === $this->normalize_mixed_value( $value ) ) {
				$value = get_field( '' !== $field_key ? $field_key : $field_name, $acf_post_id );
			}
		}

		if ( '' === $this->normalize_mixed_value( $value ) ) {
			$value = $this->get_raw_meta_fallback( $field_name, $acf_post_id );
		}

		return $this->stringify_value( $value );
	}

	public function values_match_for_conflict_check( string $left, $right, string $field_type ): bool {
		return $this->normalize_compare_value( $left, $field_type ) === $this->normalize_compare_value( $this->stringify_value( $right ), $field_type );
	}

	public function normalize_mixed_for_presence_check( $value ): string {
		return $this->normalize_for_presence_check( $this->stringify_value( $value ) );
	}

	public function deduplicate_fields( array $fields ): array {
		$deduplicated = array();
		$seen         = array();

		foreach ( $fields as $field ) {
			$signature = (string) ( $field['key'] ?? '' ) . '|' . (string) ( $field['name'] ?? '' );
			if ( isset( $seen[ $signature ] ) ) {
				continue;
			}
			$seen[ $signature ] = true;
			$deduplicated[]     = $field;
		}

		return $deduplicated;
	}

	public function make_preview_text( string $value ): string {
		$value = wp_strip_all_tags( $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return __( 'Leeg veld', 'acf-page-text-manager' );
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $value ) > 180 ) {
			return mb_substr( $value, 0, 180 ) . '...';
		}

		if ( strlen( $value ) > 180 ) {
			return substr( $value, 0, 180 ) . '...';
		}

		return $value;
	}

	public function normalize_for_presence_check( string $value ): string {
		$value = wp_strip_all_tags( $value );
		$value = preg_replace( '/\xC2\xA0/u', ' ', $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		return trim( (string) $value );
	}

	public function filter_filled_fields( array $fields ): array {
		return array_values(
			array_filter(
				$fields,
				static function( array $field ): bool {
					return '' !== (string) ( $field['normalized_value'] ?? '' );
				}
			)
		);
	}

	public function stringify_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	private function normalize_compare_value( string $value, string $field_type ): string {
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		if ( 'wysiwyg' === $field_type ) {
			$value = preg_replace( '#<br\s*/?>#i', "\n", $value );
			$value = preg_replace( '#</p>\s*<p>#i', "\n\n", $value );
			$value = wp_strip_all_tags( $value );
		}

		$value = preg_replace( "/[ \t]+/", ' ', $value );
		$value = preg_replace( "/\n{3,}/", "\n\n", $value );
		return trim( (string) $value );
	}

	private function normalize_mixed_value( $value ): string {
		return trim( wp_strip_all_tags( $this->stringify_value( $value ) ) );
	}

	public function get_raw_meta_fallback( string $field_name, mixed $acf_post_id ): mixed {
		if ( is_numeric( $acf_post_id ) ) {
			$post_meta_value = get_post_meta( (int) $acf_post_id, $field_name, true );
			if ( '' !== $this->normalize_mixed_value( $post_meta_value ) ) {
				return $post_meta_value;
			}
			return null;
		}

		if ( is_string( $acf_post_id ) && 0 === strpos( $acf_post_id, 'term_' ) ) {
			$term_id = (int) substr( $acf_post_id, 5 );
			$term_meta_value = get_term_meta( $term_id, $field_name, true );
			if ( '' !== $this->normalize_mixed_value( $term_meta_value ) ) {
				return $term_meta_value;
			}
			return '';
		}

		$option_value = get_option( (string) $acf_post_id . '_' . $field_name, null );
		if ( '' !== $this->normalize_mixed_value( $option_value ) ) {
			return $option_value;
		}

		if ( 'options' !== (string) $acf_post_id ) {
			$default_option_value = get_option( 'options_' . $field_name, null );
			if ( '' !== $this->normalize_mixed_value( $default_option_value ) ) {
				return $default_option_value;
			}
		}

		return null;
	}

	private function sanitize_deep_value( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $key => $item ) {
				$sanitized_key = is_string( $key ) ? sanitize_key( $key ) : $key;
				$sanitized[ $sanitized_key ] = $this->sanitize_deep_value( $item );
			}
			return $sanitized;
		}
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	private function normalize_date_value( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^\d{8}$/', $value ) ) {
			return $value;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? '' : gmdate( 'Ymd', $timestamp );
	}

	private function normalize_datetime_value( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );
		if ( '' === $value ) {
			return '';
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? '' : ( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $timestamp ) : gmdate( 'Y-m-d H:i:s', $timestamp ) );
	}

	private function normalize_time_value( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );
		if ( preg_match( '/^\d{1,2}:\d{2}(:\d{2})?$/', $value ) ) {
			return $value;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? '' : ( function_exists( 'wp_date' ) ? wp_date( 'H:i:s', $timestamp ) : gmdate( 'H:i:s', $timestamp ) );
	}

	private function normalize_color_value( string $value ): string {
		$value = sanitize_text_field( trim( $value ) );
		return preg_match( '/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $value ) ? $value : '';
	}

	private function decode_json_value( string $value ) {
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return null;
		}
		if ( ! in_array( $trimmed[0], array( '{', '[' ), true ) ) {
			return null;
		}
		$decoded = json_decode( $trimmed, true );
		return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
	}

	private function normalize_bool_value( string $value ): int {
		$value = strtolower( trim( $value ) );
		return in_array( $value, array( '1', 'true', 'yes', 'ja', 'on' ), true ) ? 1 : 0;
	}

	private function normalize_numeric_value( string $value ): int|float|string {
		$value = trim( str_replace( ',', '.', $value ) );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}
		return false !== strpos( $value, '.' ) ? (float) $value : (int) $value;
	}

	private function normalize_list_value( $value ): array {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( array( $this, 'sanitize_list_item' ), $value ), static fn ( $item ) => '' !== $item ) );
		}
		$parts = preg_split( '/\s*(?:\||,|\n)\s*/u', (string) $value );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		return array_values( array_filter( array_map( array( $this, 'sanitize_list_item' ), $parts ), static fn ( $item ) => '' !== $item ) );
	}

	private function sanitize_list_item( $item ): string {
		if ( is_scalar( $item ) ) {
			return sanitize_text_field( (string) $item );
		}
		return '';
	}

	private function normalize_media_value( $value ) {
		if ( is_array( $value ) ) {
			if ( ! empty( $value['ID'] ) || ! empty( $value['id'] ) ) {
				return absint( $value['ID'] ?? $value['id'] );
			}
			if ( ! empty( $value['url'] ) ) {
				return esc_url_raw( (string) $value['url'] );
			}
		}
		$string = trim( (string) $value );
		if ( '' === $string ) {
			return '';
		}
		if ( is_numeric( $string ) ) {
			return absint( $string );
		}
		return esc_url_raw( $string );
	}

	private function normalize_link_value( $value ): array {
		if ( ! is_array( $value ) ) {
			$url = esc_url_raw( (string) $value );
			return '' === $url ? array() : array( 'url' => $url, 'title' => '', 'target' => '' );
		}
		return array(
			'url'    => esc_url_raw( (string) ( $value['url'] ?? '' ) ),
			'title'  => sanitize_text_field( (string) ( $value['title'] ?? '' ) ),
			'target' => sanitize_text_field( (string) ( $value['target'] ?? '' ) ),
		);
	}

	private function normalize_map_value( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array(
			'address' => sanitize_text_field( (string) ( $value['address'] ?? '' ) ),
			'lat'     => isset( $value['lat'] ) && is_numeric( (string) $value['lat'] ) ? (float) $value['lat'] : '',
			'lng'     => isset( $value['lng'] ) && is_numeric( (string) $value['lng'] ) ? (float) $value['lng'] : '',
		);
	}
}
