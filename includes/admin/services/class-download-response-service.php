<?php
namespace WA_ACF_PTM\Admin\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Download_Response_Service {
	/**
	 * Send safe download headers for generated exports.
	 *
	 * @param string    $content_type MIME type for the generated file.
	 * @param string    $filename     Suggested download filename.
	 * @param int|false $length       Optional content length from filesize().
	 */
	public static function send_headers( string $content_type, string $filename, $length = false ): void {
		$safe_filename = str_replace( '"', '', sanitize_file_name( $filename ) );
		if ( '' === $safe_filename ) {
			$safe_filename = 'export';
		}

		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . $safe_filename . '"' );
		if ( is_int( $length ) && $length > 0 ) {
			header( 'Content-Length: ' . (string) absint( $length ) );
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
	}
}
