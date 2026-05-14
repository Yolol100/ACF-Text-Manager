<?php
namespace WA_ACF_PTM\Admin\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Target_Permission_Service {
	/**
	 * Determine whether the current user can edit a target/operation payload.
	 *
	 * @param array<string,mixed> $target Target or import operation context.
	 */
	public function current_user_can_edit( array $target ): bool {
		$target_type   = sanitize_key( (string) ( $target['target_type'] ?? '' ) );
		$content_scope = sanitize_key( (string) ( $target['content_scope'] ?? '' ) );
		$target_id     = (string) ( $target['target_id'] ?? '' );
		$acf_post_id   = $target['acf_post_id'] ?? 0;

		if ( 'option' === $target_type || 'option' === $content_scope || 'option' === (string) $acf_post_id ) {
			return current_user_can( 'manage_options' );
		}

		$term_id = $this->extract_term_id( $target_id, $acf_post_id );
		if ( $term_id > 0 || 'term' === $target_type || $this->is_term_scope( $content_scope ) ) {
			return $term_id > 0 ? current_user_can( 'edit_term', $term_id ) : current_user_can( 'manage_categories' );
		}

		$post_id = absint( is_numeric( $target_id ) ? $target_id : $acf_post_id );
		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id );
		}

		return current_user_can( 'manage_options' );
	}

	private function extract_term_id( string $target_id, $acf_post_id ): int {
		if ( preg_match( '/^([^:]+):(\d+)$/', $target_id, $matches ) ) {
			return absint( $matches[2] );
		}

		$acf_post_id = (string) $acf_post_id;
		if ( preg_match( '/^term_(\d+)$/', $acf_post_id, $matches ) ) {
			return absint( $matches[1] );
		}

		return 0;
	}

	private function is_term_scope( string $content_scope ): bool {
		return in_array( $content_scope, array( 'category' ), true ) || taxonomy_exists( $content_scope );
	}
}
