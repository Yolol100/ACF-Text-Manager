<?php
namespace WA_ACF_PTM\Admin\Services\Traits;

use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Special_Field_Value_Trait {
	public function get_special_post_value( WP_Post $post, string $field_name ) {
		if ( '__featured_image' === $field_name ) {
			return get_post_thumbnail_id( $post->ID );
		}
		if ( 0 === strpos( $field_name, '__featured_image__' ) ) {
			return $this->get_attachment_meta_value( get_post_thumbnail_id( $post->ID ), substr( $field_name, strlen( '__featured_image__' ) ) );
		}
		if ( 0 === strpos( $field_name, '__taxonomy__' ) ) {
			return $this->get_post_taxonomy_value( $post->ID, substr( $field_name, 12 ) );
		}

		switch ( $field_name ) {
			case 'post_title':
			case 'post_name':
			case 'post_content':
			case 'post_excerpt':
			case 'post_status':
			case 'post_password':
			case 'post_date':
			case 'comment_status':
			case 'ping_status':
			case 'post_parent':
			case 'post_author':
				return get_post_field( $field_name, $post->ID, 'raw' );
			case '_wp_page_template':
				return (string) get_post_meta( $post->ID, '_wp_page_template', true );
			default:
				return get_post_meta( $post->ID, $field_name, true );
		}
	}

	public function get_special_term_value( WP_Term $term, string $field_name ) {
		switch ( $field_name ) {
			case 'name':
				return $term->name;
			case 'slug':
				return $term->slug;
			case 'description':
				return (string) $term->description;
			default:
				return get_term_meta( $term->term_id, $field_name, true );
		}
	}

	public function update_special_field_value( array $operation, $new_value ): bool {
		$field_name = (string) ( $operation['field_name'] ?? '' );
		$target_id   = (string) ( $operation['target_id'] ?? '' );
		$post_id     = absint( $target_id );
		if ( $post_id < 1 ) {
			$post_id = absint( $operation['acf_post_id'] ?? 0 );
		}

		if ( $post_id > 0 && ! get_post( $post_id ) && ! ( is_string( $target_id ) && 0 === strpos( $target_id, 'term:' ) ) ) {
			return false;
		}

		if ( 0 === strpos( $field_name, '__featured_image__' ) ) {
			if ( $post_id < 1 || ! get_post( $post_id ) ) {
				return false;
			}
			return $this->update_attachment_meta_value( get_post_thumbnail_id( $post_id ), substr( $field_name, strlen( '__featured_image__' ) ), (string) $new_value );
		}
		if ( '__featured_image' === $field_name ) {
			if ( $post_id < 1 || ! get_post( $post_id ) ) {
				return false;
			}
			$attachment_id = $this->resolve_media_reference( $new_value );
			return $attachment_id > 0 ? false !== set_post_thumbnail( $post_id, $attachment_id ) : delete_post_thumbnail( $post_id );
		}
		if ( 0 === strpos( $field_name, '__acf_image__' ) ) {
			$parts = explode( '__', $field_name );
			$field_base = $parts[2] ?? '';
			$meta_key = $parts[3] ?? '';
			$attachment_id = $this->resolve_acf_image_attachment_id( $field_base, $operation['acf_post_id'] ?? $post_id );
			return $this->update_attachment_meta_value( $attachment_id, $meta_key, (string) $new_value );
		}
		if ( 0 === strpos( $field_name, '__taxonomy__' ) ) {
			return ( $post_id > 0 && get_post( $post_id ) ) ? $this->set_post_taxonomy_value( $post_id, substr( $field_name, 12 ), $new_value ) : false;
		}

		$term_context = $this->get_term_context_from_operation( $operation );
		if ( $term_context['term_id'] > 0 ) {
			$term_id  = (int) $term_context['term_id'];
			$taxonomy = (string) $term_context['taxonomy'];

			if ( in_array( $field_name, array( 'name', 'slug', 'description' ), true ) ) {
				if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
					return false;
				}

				$args = array();
				if ( 'name' === $field_name ) {
					$args['name'] = sanitize_text_field( (string) $new_value );
				}
				if ( 'slug' === $field_name ) {
					$args['slug'] = sanitize_title( (string) $new_value );
				}
				if ( 'description' === $field_name ) {
					$args['description'] = wp_kses_post( (string) $new_value );
				}
				$result = wp_update_term( $term_id, $taxonomy, $args );
				return ! is_wp_error( $result );
			}

			if ( 'thumbnail_id' === $field_name ) {
				return false !== update_term_meta( $term_id, 'thumbnail_id', $this->resolve_media_reference( $new_value ) );
			}

			return false !== update_term_meta( $term_id, $field_name, is_scalar( $new_value ) ? (string) $new_value : wp_json_encode( $new_value ) );
		}

		if ( $post_id < 1 || ! get_post( $post_id ) ) {
			return false;
		}

		$postarr = array( 'ID' => $post_id );
		switch ( $field_name ) {
			case 'post_title':
				$postarr['post_title'] = sanitize_text_field( (string) $new_value );
				break;
			case 'post_name':
				$postarr['post_name'] = sanitize_title( (string) $new_value );
				break;
			case 'post_content':
				$postarr['post_content'] = wp_kses_post( (string) $new_value );
				break;
			case 'post_excerpt':
				$postarr['post_excerpt'] = sanitize_textarea_field( (string) $new_value );
				break;
			case 'post_status':
				$status = sanitize_key( (string) $new_value );
				if ( ! in_array( $status, get_post_stati(), true ) || ! in_array( $status, self::ALLOWED_POST_STATUSES, true ) ) {
					return false;
				}
				$postarr['post_status'] = $status;
				break;
			case 'post_password':
				$postarr['post_password'] = sanitize_text_field( (string) $new_value );
				break;
			case 'post_date':
				$timestamp = strtotime( (string) $new_value );
				if ( false === $timestamp ) {
					return false;
				}
				$postarr['post_date'] = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $timestamp ) : gmdate( 'Y-m-d H:i:s', $timestamp );
				break;
			case 'comment_status':
				$postarr['comment_status'] = in_array( (string) $new_value, array( 'open', 'closed' ), true ) ? (string) $new_value : 'closed';
				break;
			case 'ping_status':
				$postarr['ping_status'] = in_array( (string) $new_value, array( 'open', 'closed' ), true ) ? (string) $new_value : 'closed';
				break;
			case 'post_parent':
				$postarr['post_parent'] = absint( $new_value );
				break;
			case 'post_author':
				$author_id = absint( $new_value );
				if ( $author_id < 1 || ! get_userdata( $author_id ) ) {
					return false;
				}
				$postarr['post_author'] = $author_id;
				break;
			case '_wp_page_template':
				$template = sanitize_text_field( (string) $new_value );
				$allowed_templates = array_merge( array( 'default' ), array_keys( wp_get_theme()->get_page_templates( get_post( $post_id ) ) ) );
				if ( ! in_array( $template, $allowed_templates, true ) ) {
					return false;
				}
				return false !== update_post_meta( $post_id, '_wp_page_template', $template );
			default:
				if ( in_array( $field_name, array( 'rank_math_facebook_image', 'rank_math_twitter_image' ), true ) ) {
					$new_value = $this->resolve_media_reference( $new_value );
				}
				return false !== update_post_meta( $post_id, $field_name, $new_value );
		}
		$result = wp_update_post( $postarr, true );
		return ! is_wp_error( $result );
	}

	public function get_special_field_value( array $operation ) {
		$field_name = (string) ( $operation['field_name'] ?? '' );
		$target_id   = (string) ( $operation['target_id'] ?? '' );
		$post_id     = absint( $target_id );
		if ( $post_id < 1 ) {
			$post_id = absint( $operation['acf_post_id'] ?? 0 );
		}
		if ( '__featured_image' === $field_name ) {
			return ( $post_id > 0 && get_post( $post_id ) ) ? get_post_thumbnail_id( $post_id ) : 0;
		}
		if ( 0 === strpos( $field_name, '__featured_image__' ) ) {
			return ( $post_id > 0 && get_post( $post_id ) ) ? $this->get_attachment_meta_value( get_post_thumbnail_id( $post_id ), substr( $field_name, strlen( '__featured_image__' ) ) ) : '';
		}
		if ( 0 === strpos( $field_name, '__acf_image__' ) ) {
			$parts = explode( '__', $field_name );
			$field_base = $parts[2] ?? '';
			$meta_key = $parts[3] ?? '';
			$attachment_id = $this->resolve_acf_image_attachment_id( $field_base, $operation['acf_post_id'] ?? $post_id );
			return $this->get_attachment_meta_value( $attachment_id, $meta_key );
		}
		if ( 0 === strpos( $field_name, '__taxonomy__' ) ) {
			return ( $post_id > 0 && get_post( $post_id ) ) ? $this->get_post_taxonomy_value( $post_id, substr( $field_name, 12 ) ) : '';
		}
		$term_context = $this->get_term_context_from_operation( $operation );
		if ( $term_context['term_id'] > 0 ) {
			$term = '' !== $term_context['taxonomy']
				? get_term( (int) $term_context['term_id'], (string) $term_context['taxonomy'] )
				: get_term( (int) $term_context['term_id'] );
			return $term instanceof WP_Term && ! is_wp_error( $term ) ? $this->get_special_term_value( $term, $field_name ) : '';
		}
		$post = get_post( $post_id );
		return $post instanceof WP_Post ? $this->get_special_post_value( $post, $field_name ) : '';
	}

	private function get_term_context_from_operation( array $operation ): array {
		$term_id  = 0;
		$taxonomy = '';

		$reference = (string) ( $operation['reference'] ?? '' );
		if ( preg_match( '/^term:([^:]+):(\d+)$/', $reference, $matches ) ) {
			$taxonomy = sanitize_key( (string) $matches[1] );
			$term_id  = absint( $matches[2] );
		}

		$target_id = (string) ( $operation['target_id'] ?? '' );
		if ( preg_match( '/^([^:]+):(\d+)$/', $target_id, $matches ) ) {
			$taxonomy = sanitize_key( (string) $matches[1] );
			$term_id  = absint( $matches[2] );
		}

		$acf_post_id = (string) ( $operation['acf_post_id'] ?? '' );
		if ( $term_id < 1 && preg_match( '/^term_(\d+)$/', $acf_post_id, $matches ) ) {
			$term_id = absint( $matches[1] );
		}

		foreach ( array( 'taxonomy', 'content_scope', 'target_type' ) as $key ) {
			$candidate = sanitize_key( (string) ( $operation[ $key ] ?? '' ) );
			if ( $this->is_real_taxonomy_slug( $candidate ) ) {
				$taxonomy = $candidate;
				break;
			}
		}

		if ( $term_id > 0 && '' === $taxonomy ) {
			$term = get_term( $term_id );
			if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
				$taxonomy = (string) $term->taxonomy;
			}
		}

		return array(
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
		);
	}

	private function is_real_taxonomy_slug( string $taxonomy ): bool {
		if ( '' === $taxonomy || in_array( $taxonomy, array( 'term', 'post', 'page', 'option' ), true ) ) {
			return false;
		}

		return taxonomy_exists( $taxonomy );
	}

	private function get_post_taxonomy_value( int $post_id, string $taxonomy ): string {
		if ( $post_id < 1 || ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		return is_wp_error( $terms ) ? '' : implode( ', ', array_map( 'strval', $terms ) );
	}

	private function set_post_taxonomy_value( int $post_id, string $taxonomy, $value ): bool {
		if ( $post_id < 1 || ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}
		$items = is_array( $value ) ? $value : preg_split( '/\s*(?:\||,|\n)\s*/u', (string) $value );
		if ( ! is_array( $items ) ) {
			$items = array();
		}
		$items = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $items ) ) ) );
		$result = wp_set_post_terms( $post_id, $items, $taxonomy, false );
		return ! is_wp_error( $result );
	}
}
