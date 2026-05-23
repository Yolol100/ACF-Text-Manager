<?php
namespace WA_ACF_PTM\Admin\Services\Traits;

use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Special_Field_Image_Trait {
	private function resolve_acf_image_attachment_id( string $field_name, $acf_post_id ): int {
		$value = function_exists( 'get_field' ) ? get_field( $field_name, $acf_post_id, false ) : null;
		if ( is_array( $value ) ) {
			return absint( $value['ID'] ?? $value['id'] ?? 0 );
		}
		if ( is_numeric( $value ) ) {
			return absint( $value );
		}
		if ( is_string( $value ) && '' !== $value ) {
			return attachment_url_to_postid( $value );
		}
		return 0;
	}

	private function get_image_meta_label( string $label, string $meta_key ): string {
		$meta_label = $this->get_image_meta_key_label( $meta_key );

		return sprintf(
			/* translators: 1: image field label, 2: image metadata label. */
			__( '%1$s — %2$s', 'acf-page-text-manager' ),
			$label,
			$meta_label
		);
	}

	private function get_image_meta_key_label( string $meta_key ): string {
		switch ( $meta_key ) {
			case 'file_name':
				return __( 'Bestandsnaam (wijzigt media-URL)', 'acf-page-text-manager' );
			case 'alt':
				return __( 'Alt tag', 'acf-page-text-manager' );
			case 'title':
				return __( 'Titel', 'acf-page-text-manager' );
			case 'caption':
				return __( 'Caption', 'acf-page-text-manager' );
			case 'description':
				return __( 'Beschrijving', 'acf-page-text-manager' );
			default:
				return sanitize_text_field( $meta_key );
		}
	}

	private function get_attachment_meta_value( int $attachment_id, string $meta_key ): string {
		if ( $attachment_id < 1 ) {
			return '';
		}
		if ( 'file_name' === $meta_key ) {
			return wp_basename( get_attached_file( $attachment_id ) ?: '' );
		}
		if ( 'alt' === $meta_key ) {
			return (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		}
		if ( 'title' === $meta_key ) {
			return (string) get_post_field( 'post_title', $attachment_id, 'raw' );
		}
		if ( 'caption' === $meta_key ) {
			return (string) get_post_field( 'post_excerpt', $attachment_id, 'raw' );
		}
		if ( 'description' === $meta_key ) {
			return (string) get_post_field( 'post_content', $attachment_id, 'raw' );
		}
		return '';
	}

	private function update_attachment_meta_value( int $attachment_id, string $meta_key, string $new_value ): bool {
		if ( $attachment_id < 1 ) {
			return false;
		}
		if ( 'file_name' === $meta_key ) {
			return $this->rename_attachment_file( $attachment_id, $new_value );
		}
		if ( 'alt' === $meta_key ) {
			return false !== update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $new_value ) );
		}
		$postarr = array( 'ID' => $attachment_id );
		if ( 'title' === $meta_key ) {
			$postarr['post_title'] = sanitize_text_field( $new_value );
		} elseif ( 'caption' === $meta_key ) {
			$postarr['post_excerpt'] = sanitize_textarea_field( $new_value );
		} elseif ( 'description' === $meta_key ) {
			$postarr['post_content'] = sanitize_textarea_field( $new_value );
		} else {
			return false;
		}
		$result = wp_update_post( $postarr, true );
		return ! is_wp_error( $result );
	}

	private function rename_attachment_file( int $attachment_id, string $new_value ): bool {
		if ( ! apply_filters( 'wa_acf_ptm_allow_media_file_rename', false, $attachment_id, $new_value ) ) {
			return false;
		}

		$attachment = get_post( $attachment_id );
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return false;
		}

		$current_path = get_attached_file( $attachment_id );
		if ( ! is_string( $current_path ) || '' === $current_path || ! file_exists( $current_path ) ) {
			return false;
		}

		$uploads = wp_get_upload_dir();
		$basedir = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
		$basedir_real = '' !== $basedir ? realpath( $basedir ) : false;
		$current_real = realpath( $current_path );
		$current_normalized = false !== $current_real ? wp_normalize_path( $current_real ) : wp_normalize_path( $current_path );
		$basedir_normalized = false !== $basedir_real ? wp_normalize_path( $basedir_real ) : $basedir;
		if ( '' === $basedir_normalized || 0 !== strpos( $current_normalized, trailingslashit( $basedir_normalized ) ) ) {
			return false;
		}

		$current_basename = wp_basename( $current_path );
		$current_ext      = pathinfo( $current_basename, PATHINFO_EXTENSION );
		$new_filename     = sanitize_file_name( trim( $new_value ) );
		if ( '' === $new_filename ) {
			return false;
		}

		if ( '' === (string) pathinfo( $new_filename, PATHINFO_FILENAME ) ) {
			return false;
		}

		$new_ext = (string) pathinfo( $new_filename, PATHINFO_EXTENSION );
		if ( '' === $new_ext && '' !== $current_ext ) {
			$new_filename .= '.' . $current_ext;
		} elseif ( '' !== $new_ext && strtolower( $new_ext ) !== strtolower( (string) $current_ext ) ) {
			$new_filename = (string) pathinfo( $new_filename, PATHINFO_FILENAME ) . ( '' !== $current_ext ? '.' . $current_ext : '' );
		}

		$directory = dirname( $current_path );
		$directory_real = realpath( $directory );
		$directory_normalized = false !== $directory_real ? wp_normalize_path( $directory_real ) : wp_normalize_path( $directory );
		if ( 0 !== strpos( $directory_normalized, trailingslashit( $basedir_normalized ) ) ) {
			return false;
		}
		if ( strtolower( $new_filename ) === strtolower( $current_basename ) ) {
			return true;
		}

		$target_filename = wp_unique_filename( $directory, $new_filename );
		if ( $target_filename === $current_basename ) {
			return true;
		}

		if ( file_exists( trailingslashit( $directory ) . $target_filename ) ) {
			return false;
		}

		$target_path = trailingslashit( $directory ) . $target_filename;
		$target_normalized = wp_normalize_path( trailingslashit( $directory_normalized ) . $target_filename );
		if ( 0 !== strpos( $target_normalized, trailingslashit( $basedir_normalized ) ) ) {
			return false;
		}

		if ( ! @rename( $current_path, $target_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Renames the media file and updates attachment metadata immediately after.
			return false;
		}

		$relative     = ltrim( substr( $target_normalized, strlen( trailingslashit( $basedir_normalized ) ) ), '/' );
		$updated_file = update_attached_file( $attachment_id, $relative );
		if ( false === $updated_file ) {
			@rename( $target_path, $current_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Roll back the physical rename when WordPress metadata cannot be updated.
			return false;
		}

		$old_relative = ltrim( substr( $current_normalized, strlen( trailingslashit( $basedir_normalized ) ) ), '/' );
		$metadata     = wp_get_attachment_metadata( $attachment_id );
		$old_metadata = is_array( $metadata ) ? $metadata : array();
		if ( ! is_array( $metadata ) ) {
			$reference_counts = $this->update_renamed_attachment_references( $attachment_id, $old_relative, $relative, $old_metadata, array() );
			$this->log_media_rename( $attachment_id, $current_basename, $target_filename, $reference_counts );
			return true;
		}

		$old_name_no_ext = (string) pathinfo( $current_basename, PATHINFO_FILENAME );
		$new_name_no_ext = (string) pathinfo( $target_filename, PATHINFO_FILENAME );
		$metadata['file'] = $relative;
		$renamed_size_files = array();

		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_key => $size ) {
				$size_file = isset( $size['file'] ) ? wp_basename( (string) $size['file'] ) : '';
				if ( '' === $size_file ) {
					continue;
				}
				$renamed_size_file = preg_replace( '/^' . preg_quote( $old_name_no_ext, '/' ) . '/u', $new_name_no_ext, $size_file, 1 ) ?: $size_file;
				$renamed_size_file = sanitize_file_name( $renamed_size_file );
				if ( '' === $renamed_size_file ) {
					continue;
				}
				if ( $renamed_size_file !== $size_file ) {
					$size_old_path = trailingslashit( $directory ) . $size_file;
					$size_new_path = trailingslashit( $directory ) . $renamed_size_file;
					$size_old_normalized = wp_normalize_path( $size_old_path );
					$size_new_normalized = wp_normalize_path( $size_new_path );
					if ( 0 !== strpos( $size_old_normalized, trailingslashit( $basedir_normalized ) ) || 0 !== strpos( $size_new_normalized, trailingslashit( $basedir_normalized ) ) ) {
						continue;
					}
					$size_renamed = false;
					if ( file_exists( $size_new_path ) ) {
						// Do not repoint metadata to an existing file that may belong to another attachment.
						continue;
					} elseif ( file_exists( $size_old_path ) ) {
						$size_renamed = @rename( $size_old_path, $size_new_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Renames generated attachment size files to keep metadata in sync.
					}

					if ( $size_renamed ) {
						$renamed_size_files[] = array(
							'old' => $size_old_path,
							'new' => $size_new_path,
						);
						$metadata['sizes'][ $size_key ]['file'] = $renamed_size_file;
					}
				}
			}
		}

		$metadata_updated = wp_update_attachment_metadata( $attachment_id, $metadata );
		if ( false === $metadata_updated ) {
			foreach ( array_reverse( $renamed_size_files ) as $renamed_size_file ) {
				if ( is_array( $renamed_size_file ) && ! empty( $renamed_size_file['new'] ) && ! empty( $renamed_size_file['old'] ) && file_exists( $renamed_size_file['new'] ) && ! file_exists( $renamed_size_file['old'] ) ) {
					@rename( $renamed_size_file['new'], $renamed_size_file['old'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Roll back generated image size file rename after metadata failure.
				}
			}
			update_attached_file( $attachment_id, $old_relative );
			@rename( $target_path, $current_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Roll back main media file rename after metadata failure.
			return false;
		}

		$reference_counts = $this->update_renamed_attachment_references( $attachment_id, $old_relative, $relative, $old_metadata, $metadata );
		$this->log_media_rename( $attachment_id, $current_basename, $target_filename, $reference_counts );
		return true;
	}

	/**
	 * Update stored URL references after a physical media file rename.
	 *
	 * Keeps the feature available, but reduces broken-link risk by replacing old
	 * upload URLs in post content and common WordPress meta stores. Serialized
	 * values are unserialized before replacement and serialized again afterwards.
	 */
	private function update_renamed_attachment_references( int $attachment_id, string $old_relative, string $new_relative, array $old_metadata, array $new_metadata ): array {
		$counts = array(
			'posts' => 0,
			'meta'  => 0,
			'guid'  => 0,
		);

		if ( ! apply_filters( 'wa_acf_ptm_update_media_rename_url_references', false, $attachment_id, $old_relative, $new_relative ) ) {
			return $counts;
		}

		$pairs = $this->build_media_rename_url_pairs( $old_relative, $new_relative, $old_metadata, $new_metadata );
		if ( empty( $pairs ) ) {
			return $counts;
		}

		$new_url = reset( $pairs );
		if ( apply_filters( 'wa_acf_ptm_update_media_rename_guid', false, $attachment_id, $old_relative, $new_relative ) && is_string( $new_url ) && '' !== $new_url ) {
			$guid_result = wp_update_post( array( 'ID' => $attachment_id, 'guid' => esc_url_raw( $new_url ) ), true );
			if ( ! is_wp_error( $guid_result ) ) {
				$counts['guid'] = 1;
			}
		}

		global $wpdb;
		foreach ( $pairs as $old_url => $replacement_url ) {
			$counts['posts'] += $this->replace_url_in_posts_table( (string) $old_url, (string) $replacement_url );
		}

		$tables = apply_filters(
			'wa_acf_ptm_media_rename_reference_tables',
			array(
				array( 'table' => $wpdb->postmeta, 'id_column' => 'meta_id', 'value_column' => 'meta_value', 'name_column' => 'meta_key' ),
				array( 'table' => $wpdb->termmeta, 'id_column' => 'meta_id', 'value_column' => 'meta_value', 'name_column' => 'meta_key' ),
				array( 'table' => $wpdb->options, 'id_column' => 'option_id', 'value_column' => 'option_value', 'name_column' => 'option_name' ),
			)
		);

		if ( ! is_array( $tables ) ) {
			return $counts;
		}

		foreach ( $tables as $table_config ) {
			if ( ! is_array( $table_config ) ) {
				continue;
			}
			$counts['meta'] += $this->replace_urls_in_value_table( $table_config, $pairs );
		}

		return $counts;
	}

	private function build_media_rename_url_pairs( string $old_relative, string $new_relative, array $old_metadata, array $new_metadata ): array {
		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? untrailingslashit( (string) $uploads['baseurl'] ) : '';
		if ( '' === $baseurl || '' === $old_relative || '' === $new_relative || $old_relative === $new_relative ) {
			return array();
		}

		$pairs = array(
			$baseurl . '/' . ltrim( $old_relative, '/' ) => $baseurl . '/' . ltrim( $new_relative, '/' ),
		);

		$old_dir = trim( dirname( $old_relative ), '.\/' );
		$new_dir = trim( dirname( $new_relative ), '.\/' );
		$old_sizes = isset( $old_metadata['sizes'] ) && is_array( $old_metadata['sizes'] ) ? $old_metadata['sizes'] : array();
		$new_sizes = isset( $new_metadata['sizes'] ) && is_array( $new_metadata['sizes'] ) ? $new_metadata['sizes'] : array();

		foreach ( $old_sizes as $size_key => $old_size ) {
			if ( ! isset( $new_sizes[ $size_key ] ) || ! is_array( $old_size ) || ! is_array( $new_sizes[ $size_key ] ) ) {
				continue;
			}
			$old_file = isset( $old_size['file'] ) ? wp_basename( (string) $old_size['file'] ) : '';
			$new_file = isset( $new_sizes[ $size_key ]['file'] ) ? wp_basename( (string) $new_sizes[ $size_key ]['file'] ) : '';
			if ( '' === $old_file || '' === $new_file || $old_file === $new_file ) {
				continue;
			}
			$old_size_relative = ( '' !== $old_dir ? trailingslashit( $old_dir ) : '' ) . $old_file;
			$new_size_relative = ( '' !== $new_dir ? trailingslashit( $new_dir ) : '' ) . $new_file;
			$pairs[ $baseurl . '/' . ltrim( $old_size_relative, '/' ) ] = $baseurl . '/' . ltrim( $new_size_relative, '/' );
		}

		return array_filter( $pairs );
	}

	private function replace_url_in_posts_table( string $old_url, string $new_url ): int {
		if ( '' === $old_url || '' === $new_url || $old_url === $new_url ) {
			return 0;
		}

		global $wpdb;
		$like = '%' . $wpdb->esc_like( $old_url ) . '%';
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s), post_excerpt = REPLACE(post_excerpt, %s, %s) WHERE post_content LIKE %s OR post_excerpt LIKE %s",
				$old_url,
				$new_url,
				$old_url,
				$new_url,
				$like,
				$like
			)
		);

		return false === $result ? 0 : (int) $result;
	}

	private function replace_urls_in_value_table( array $table_config, array $pairs ): int {
		global $wpdb;
		$validated_config = $this->get_validated_media_rename_reference_table_config( $table_config );
		if ( empty( $validated_config ) ) {
			return 0;
		}

		$table        = $validated_config['table'];
		$id_column    = $validated_config['id_column'];
		$value_column = $validated_config['value_column'];
		$name_column  = $validated_config['name_column'];

		$updated = 0;
		foreach ( $pairs as $old_url => $new_url ) {
			$like = '%' . $wpdb->esc_like( (string) $old_url ) . '%';
			$select_name = '' !== $name_column ? ', ' . $name_column . ' AS stored_name' : '';
			$row_limit = $this->get_media_rename_reference_row_limit();
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT {$id_column} AS row_id, {$value_column} AS stored_value{$select_name} FROM {$table} WHERE {$value_column} LIKE %s LIMIT %d", $like, $row_limit ),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$stored_name = isset( $row['stored_name'] ) ? (string) $row['stored_name'] : '';
				if ( '' !== $stored_name && $this->should_skip_media_rename_reference_row( $stored_name ) ) {
					continue;
				}
				$row_id = isset( $row['row_id'] ) ? absint( $row['row_id'] ) : 0;
				$stored = isset( $row['stored_value'] ) ? (string) $row['stored_value'] : '';
				if ( $row_id < 1 || '' === $stored ) {
					continue;
				}
				$is_serialized = is_serialized( $stored );
				$unserialized  = $is_serialized ? $this->safe_unserialize_for_url_replacement( $stored ) : $stored;
				if ( null === $unserialized ) {
					continue;
				}

				$replaced = $this->replace_urls_in_value( $unserialized, $pairs );
				$new_stored = $is_serialized ? maybe_serialize( $replaced ) : (string) $replaced;
				if ( $new_stored !== $stored ) {
					$result = $wpdb->update( $table, array( $value_column => $new_stored ), array( $id_column => $row_id ), array( '%s' ), array( '%d' ) );
					if ( false !== $result ) {
						$updated += (int) $result;
					}
				}
			}
		}

		return $updated;
	}

	private function get_validated_media_rename_reference_table_config( array $table_config ): array {
		global $wpdb;

		$table        = isset( $table_config['table'] ) ? (string) $table_config['table'] : '';
		$id_column    = isset( $table_config['id_column'] ) ? (string) $table_config['id_column'] : '';
		$value_column = isset( $table_config['value_column'] ) ? (string) $table_config['value_column'] : '';
		$name_column  = isset( $table_config['name_column'] ) ? (string) $table_config['name_column'] : '';

		$allowed_configs = array(
			$wpdb->postmeta => array(
				'id_column'    => 'meta_id',
				'value_column' => 'meta_value',
				'name_column'  => 'meta_key',
			),
			$wpdb->termmeta => array(
				'id_column'    => 'meta_id',
				'value_column' => 'meta_value',
				'name_column'  => 'meta_key',
			),
			$wpdb->options  => array(
				'id_column'    => 'option_id',
				'value_column' => 'option_value',
				'name_column'  => 'option_name',
			),
		);

		if ( apply_filters( 'wa_acf_ptm_allow_custom_media_rename_reference_tables', false ) ) {
			$filtered_configs = apply_filters( 'wa_acf_ptm_allowed_media_rename_reference_table_configs', $allowed_configs );
			$allowed_configs  = is_array( $filtered_configs ) ? $filtered_configs : $allowed_configs;
		}

		if ( ! is_array( $allowed_configs ) || '' === $table || ! isset( $allowed_configs[ $table ] ) || ! is_array( $allowed_configs[ $table ] ) ) {
			return array();
		}

		$allowed = $allowed_configs[ $table ];
		if (
			$id_column !== (string) ( $allowed['id_column'] ?? '' )
			|| $value_column !== (string) ( $allowed['value_column'] ?? '' )
			|| $name_column !== (string) ( $allowed['name_column'] ?? '' )
		) {
			return array();
		}

		foreach ( array( $table, $id_column, $value_column ) as $identifier ) {
			if ( '' === $identifier || ! preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
				return array();
			}
		}

		if ( '' !== $name_column && ! preg_match( '/^[A-Za-z0-9_]+$/', $name_column ) ) {
			return array();
		}

		return array(
			'table'        => $table,
			'id_column'    => $id_column,
			'value_column' => $value_column,
			'name_column'  => $name_column,
		);
	}

	private function get_media_rename_reference_row_limit(): int {
		/**
		 * Limits per-table rows scanned during opt-in media URL reference replacement.
		 *
		 * Keep this bounded because postmeta/options tables can be large on production sites.
		 *
		 * @param int $limit Maximum rows selected per table and URL pair.
		 */
		$limit = (int) apply_filters( 'wa_acf_ptm_media_rename_reference_row_limit', 1000 );
		return max( 1, min( 5000, $limit ) );
	}

	private function should_skip_media_rename_reference_row( string $name ): bool {
		$blocked_prefixes = apply_filters(
			'wa_acf_ptm_media_rename_reference_blocked_name_prefixes',
			array( '_transient_', '_site_transient_', '_wc_session_', 'wpseo_sitemap_', 'rank_math_sitemap_' )
		);

		if ( ! is_array( $blocked_prefixes ) ) {
			return false;
		}

		foreach ( $blocked_prefixes as $prefix ) {
			$prefix = (string) $prefix;
			if ( '' !== $prefix && 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}


	private function safe_unserialize_for_url_replacement( string $stored ) {
		if ( ! is_serialized( $stored ) ) {
			return $stored;
		}

		$value = @unserialize( trim( $stored ), array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Safe bounded migration of stored scalar/array values; object classes are intentionally not rehydrated.
		if ( false === $value && 'b:0;' !== $stored ) {
			return null;
		}

		return is_object( $value ) ? null : $value;
	}

	private function replace_urls_in_value( $value, array $pairs ) {
		if ( is_string( $value ) ) {
			return str_replace( array_keys( $pairs ), array_values( $pairs ), $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->replace_urls_in_value( $item, $pairs );
			}
			return $value;
		}
		if ( is_object( $value ) ) {
			return $value;
		}
		return $value;
	}

	private function log_media_rename( int $attachment_id, string $old_filename, string $new_filename, array $reference_counts = array() ): void {
		$log = get_option( 'wa_acf_ptm_media_rename_log', array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				'time'             => current_time( 'mysql', true ),
				'user_id'          => get_current_user_id(),
				'attachment_id'    => $attachment_id,
				'old_filename'     => sanitize_file_name( $old_filename ),
				'new_filename'     => sanitize_file_name( $new_filename ),
				'reference_counts' => array(
					'posts' => isset( $reference_counts['posts'] ) ? absint( $reference_counts['posts'] ) : 0,
					'meta'  => isset( $reference_counts['meta'] ) ? absint( $reference_counts['meta'] ) : 0,
					'guid'  => isset( $reference_counts['guid'] ) ? absint( $reference_counts['guid'] ) : 0,
				),
			)
		);
		$log = array_slice( $log, 0, 100 );
		update_option( 'wa_acf_ptm_media_rename_log', $log, false );
	}

	private function resolve_media_reference( $value ): int {
		if ( is_array( $value ) ) {
			$value = $value['ID'] ?? $value['id'] ?? $value['url'] ?? '';
		}
		if ( is_numeric( $value ) ) {
			return absint( $value );
		}
		$string = trim( (string) $value );
		return '' === $string ? 0 : absint( attachment_url_to_postid( $string ) );
	}
}
