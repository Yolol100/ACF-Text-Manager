<?php
namespace WA_ACF_PTM\Admin;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Page_Repository_Index_Trait {
	public function get_targets_index(): array {
		$cache = get_transient( self::CACHE_KEY );

		if ( is_array( $cache ) ) {
			return $this->normalize_targets_index( $cache );
		}

		$targets = array();

		foreach ( $this->get_post_targets() as $target ) {
			$targets[] = $target;
		}

		usort(
			$targets,
			static function( array $left, array $right ): int {
				return strcasecmp( (string) $left['title'], (string) $right['title'] );
			}
		);

		set_transient( self::CACHE_KEY, $targets, HOUR_IN_SECONDS );

		return $targets;
	}

	private function normalize_targets_index( array $targets ): array {
		$normalized = array();

		foreach ( $targets as $target ) {
			if ( ! is_array( $target ) ) {
				continue;
			}

			$scope = isset( $target['content_scope'] ) ? sanitize_key( (string) $target['content_scope'] ) : '';
			if ( '' === $scope ) {
				$scope = $this->infer_content_scope_from_target( $target );
			}

			$target['content_scope'] = $scope;
			if ( '' === $scope || ! isset( $this->get_content_type_options()[ $scope ] ) ) {
				continue;
			}

			$normalized[] = $target;
		}

		return $normalized;
	}

	private function infer_content_scope_from_target( array $target ): string {
		$reference = isset( $target['reference'] ) ? (string) $target['reference'] : '';
		$target_type = isset( $target['target_type'] ) ? sanitize_key( (string) $target['target_type'] ) : '';

		if ( 'option' === $target_type || 0 === strpos( $reference, 'option:' ) ) {
			return '';
		}

		if ( 'page' === $target_type || 0 === strpos( $reference, 'page:' ) ) {
			return 'page';
		}

		if ( preg_match( '/^term:([^:]+):/i', $reference ) ) {
			return '';
		}

		if ( 'post' === $target_type ) {
			$post_id = isset( $target['id'] ) ? (int) $target['id'] : 0;
			$post = $post_id > 0 ? get_post( $post_id ) : null;
			if ( $post instanceof WP_Post ) {
				return $this->get_post_content_scope( $post );
			}
			return 'post';
		}

		$post_id = isset( $target['id'] ) ? (int) $target['id'] : 0;
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( $post instanceof WP_Post ) {
			return $this->get_post_content_scope( $post );
		}

		return $target_type ?: 'post';
	}

	public function get_available_content_type_options( array $targets ): array {
		$labels    = $this->get_content_type_options();
		$available = array();

		foreach ( $targets as $target ) {
			if ( ! is_array( $target ) ) {
				continue;
			}

			$scope = isset( $target['content_scope'] ) ? sanitize_key( (string) $target['content_scope'] ) : '';
			if ( '' === $scope ) {
				$scope = $this->infer_content_scope_from_target( $target );
			}

			if ( '' === $scope || ! isset( $labels[ $scope ] ) ) {
				continue;
			}

			$available[ $scope ] = $labels[ $scope ];
		}

		if ( ! empty( $available ) ) {
			return $available;
		}

		return $this->get_existing_content_type_options();
	}

	private function get_existing_content_type_options(): array {
		$labels    = $this->get_content_type_options();
		$available = array();

		$post_types = array(
			'page' => 'page',
			'post' => 'post',
		);

		foreach ( $post_types as $post_type => $scope ) {
			if ( post_type_exists( $post_type ) && isset( $labels[ $scope ] ) ) {
				$available[ $scope ] = $labels[ $scope ];
			}
		}

		return $available;
	}

	public function clear_cache(): void {
		$targets = get_transient( self::CACHE_KEY );

		if ( is_array( $targets ) ) {
			foreach ( $targets as $target ) {
				if ( ! empty( $target['reference'] ) ) {
					delete_transient( self::DETAIL_CACHE_PREFIX . md5( (string) $target['reference'] ) );
				}
			}
		}

		delete_transient( self::CACHE_KEY );
	}

	private function get_post_targets(): array {
		$allowed_post_types = array();

		foreach ( array( 'page', 'post' ) as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
				$post_type_object = get_post_type_object( $post_type );
				if ( $post_type_object ) {
					$allowed_post_types[ $post_type ] = $post_type_object;
				}
			}
		}

		$targets = array();
		$per_page = 200;

		foreach ( $allowed_post_types as $post_type => $post_type_object ) {
			$paged = 1;

			do {
				$post_ids = get_posts(
					array(
						'post_type'              => $post_type,
						'post_status'            => array( 'publish', 'draft', 'private', 'pending', 'future' ),
						'posts_per_page'         => $per_page,
						'paged'                  => $paged,
						'orderby'                => 'title',
						'order'                  => 'ASC',
						'fields'                 => 'ids',
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					)
				);

				if ( empty( $post_ids ) ) {
					break;
				}

				foreach ( $post_ids as $post_id ) {
					$post = get_post( (int) $post_id );
					if ( ! $post instanceof WP_Post || $post_type !== $post->post_type ) {
						continue;
					}

					$target = $this->get_post_summary( $post );
					if ( ! empty( $target['field_count'] ) ) {
						$targets[] = $target;
					}
				}

				$paged++;
			} while ( count( $post_ids ) === $per_page );
		}

		return $targets;
	}

}
