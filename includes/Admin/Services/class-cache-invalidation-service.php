<?php
namespace WA_ACF_PTM\Admin\Services;

use WA_ACF_PTM\Admin\Page_Repository;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cache_Invalidation_Service {
	private Page_Repository $repository;

	public function __construct( Page_Repository $repository ) {
		$this->repository = $repository;
	}

	public function flush_after_acf_save( $post_id = 0 ): void {
		if ( is_string( $post_id ) && 0 === strpos( $post_id, 'acf-' ) ) {
			return;
		}
		$this->repository->clear_cache();
	}

	public function flush_after_post_save( int $post_id, WP_Post $post ): void {
		unset( $post );
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$this->repository->clear_cache();
	}

	public function flush_after_term_change( int $term_id, int $tt_id = 0, string $taxonomy = '' ): void {
		unset( $term_id, $tt_id, $taxonomy );
		$this->repository->clear_cache();
	}
}
