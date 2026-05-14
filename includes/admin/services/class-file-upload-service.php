<?php
namespace WA_ACF_PTM\Admin\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class File_Upload_Service {
	private const MAX_IMPORT_FILE_SIZE = 10485760;
	private const MAX_ZIP_IMPORT_FILES = 100;
	private const MAX_ZIP_TOTAL_UNCOMPRESSED_SIZE = 52428800;

	public function handle_import_uploads(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- AJAX nonce is verified by Admin_Actions before this service is called; upload arrays are validated below.
		if ( empty( $_FILES['import_file'] ) || ! is_array( $_FILES['import_file'] ) ) {
			return array( 'error' => __( 'Er is geen CSV-, XLSX- of ZIP-bestand ontvangen.', 'acf-page-text-manager' ) );
		}

		$files = $this->normalize_files_array( $_FILES['import_file'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $files ) ) {
			return array( 'error' => __( 'Er is geen CSV-, XLSX- of ZIP-bestand ontvangen.', 'acf-page-text-manager' ) );
		}

		$uploads = array();

		foreach ( $files as $file ) {
			$validation = $this->validate_file( $file );

			if ( isset( $validation['error'] ) ) {
				$this->cleanup_uploads( $uploads );
				return $validation;
			}

			$stored = $this->store_via_wordpress( $file );

			if ( is_wp_error( $stored ) ) {
				$this->cleanup_uploads( $uploads );
				return array( 'error' => $stored->get_error_message() );
			}

			$uploads[] = array(
				'file' => $stored['file'],
				'name' => $stored['name'],
				'ext'  => $stored['ext'],
			);

			if ( 'zip' === $stored['ext'] ) {
				$expanded = $this->expand_zip_upload( $stored );
				Temp_File_Service::delete( (string) $stored['file'] );
				array_pop( $uploads );

				if ( isset( $expanded['error'] ) ) {
					$this->cleanup_uploads( $uploads );
					return $expanded;
				}

				$uploads = array_merge( $uploads, $expanded['files'] ?? array() );
			}
		}

		return array( 'files' => $uploads );
	}

	private function validate_file( array $file ): array {
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return array(
				'error' => sprintf(
					/* translators: %s: upload error message. */
					__( 'Upload mislukt: %s', 'acf-page-text-manager' ),
					$this->code_to_message( (int) $file['error'] )
				),
			);
		}

		$original_name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
		$tmp_name      = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$allowed_exts  = array_keys( $this->get_allowed_mimes() );

		if ( '' === $original_name || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return array( 'error' => __( 'Het geüploade bestand kon niet worden gelezen.', 'acf-page-text-manager' ) );
		}

		$ext = strtolower( (string) pathinfo( $original_name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			return array( 'error' => __( 'Alleen CSV-, XLSX- en ZIP-bestanden zijn toegestaan.', 'acf-page-text-manager' ) );
		}

		$type_validation = $this->validate_upload_filetype( $tmp_name, $original_name, $ext );
		if ( isset( $type_validation['error'] ) ) {
			return $type_validation;
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( $size < 1 || $size > self::MAX_IMPORT_FILE_SIZE ) {
			return array(
				'error' => sprintf(
					/* translators: %s: maximum file size in megabytes. */
					__( 'Het importbestand mag maximaal %s MB zijn.', 'acf-page-text-manager' ),
					'10'
				),
			);
		}

		if ( 'csv' === $ext ) {
			$csv_validation = $this->validate_csv_file( $tmp_name );

			if ( isset( $csv_validation['error'] ) ) {
				return $csv_validation;
			}
		}

		if ( 'xlsx' === $ext ) {
			$xlsx_validation = $this->validate_xlsx_file( $tmp_name );

			if ( isset( $xlsx_validation['error'] ) ) {
				return $xlsx_validation;
			}
		}

		if ( 'zip' === $ext ) {
			$zip_validation = $this->validate_zip_file( $tmp_name );

			if ( isset( $zip_validation['error'] ) ) {
				return $zip_validation;
			}
		}

		return array();
	}

	private function store_via_wordpress( array $file ): array|\WP_Error {
		$original_name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
		$tmp_name      = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		$temp_dir = trailingslashit( get_temp_dir() ) . 'wa-acf-ptm-imports';
		if ( ! wp_mkdir_p( $temp_dir ) || ! is_dir( $temp_dir ) || ! wp_is_writable( $temp_dir ) ) {
			return new \WP_Error(
				'wa_acf_ptm_temp_dir_unavailable',
				__( 'Er kon geen veilige tijdelijke importmap worden aangemaakt.', 'acf-page-text-manager' )
			);
		}

		if ( '' === $original_name || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return new \WP_Error(
				'wa_acf_ptm_store_failed',
				__( 'Het geüploade bestand kon niet worden opgeslagen.', 'acf-page-text-manager' )
			);
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$upload_dir_filter = static function ( array $uploads ) use ( $temp_dir ): array {
			$uploads['path']   = $temp_dir;
			$uploads['url']    = '';
			$uploads['subdir'] = '';
			return $uploads;
		};

		$upload = array(
			'name'     => $original_name,
			'type'     => isset( $file['type'] ) ? (string) $file['type'] : '',
			'tmp_name' => $tmp_name,
			'error'    => isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_OK,
			'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
		);

		add_filter( 'upload_dir', $upload_dir_filter );

		try {
			$stored = wp_handle_upload(
				$upload,
				array(
					'test_form' => false,
					'mimes'     => $this->get_allowed_mimes(),
				)
			);
		} finally {
			remove_filter( 'upload_dir', $upload_dir_filter );
		}

		if ( ! is_array( $stored ) || ! empty( $stored['error'] ) || empty( $stored['file'] ) ) {
			return new \WP_Error(
				'wa_acf_ptm_store_failed',
				isset( $stored['error'] ) ? (string) $stored['error'] : __( 'Het geüploade bestand kon niet worden opgeslagen.', 'acf-page-text-manager' )
			);
		}

		$stored_file = wp_normalize_path( (string) $stored['file'] );
		$temp_dir_normalized = wp_normalize_path( trailingslashit( $temp_dir ) );

		if ( 0 !== strpos( $stored_file, $temp_dir_normalized ) ) {
			Temp_File_Service::delete( $stored_file );
			return new \WP_Error(
				'wa_acf_ptm_store_failed',
				__( 'Het geüploade bestand kon niet veilig worden opgeslagen.', 'acf-page-text-manager' )
			);
		}

		return array(
			'file' => $stored_file,
			'name' => $original_name,
			'ext'  => strtolower( (string) pathinfo( $original_name, PATHINFO_EXTENSION ) ),
		);
	}

	// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV validation requires streaming fgetcsv() from a temporary upload.
	private function validate_upload_filetype( string $tmp_name, string $original_name, string $expected_ext ): array {
		if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
			return array();
		}

		$checked = wp_check_filetype_and_ext( $tmp_name, $original_name, $this->get_allowed_mimes() );
		$checked_ext = isset( $checked['ext'] ) ? strtolower( (string) $checked['ext'] ) : '';

		if ( '' !== $checked_ext && $checked_ext !== $expected_ext ) {
			return array( 'error' => __( 'Het bestandstype komt niet overeen met de bestandsextensie.', 'acf-page-text-manager' ) );
		}

		if ( empty( $checked['type'] ) && 'csv' !== $expected_ext ) {
			return array( 'error' => __( 'Het bestandstype kon niet veilig worden gevalideerd.', 'acf-page-text-manager' ) );
		}

		return array();
	}

	private function validate_csv_file( string $path ): array {
		$handle = fopen( $path, 'r' );

		if ( false === $handle ) {
			return array( 'error' => __( 'Het CSV-bestand kon niet worden geopend.', 'acf-page-text-manager' ) );
		}

		$header = fgetcsv( $handle, 0, ',' );

		if ( empty( $header ) || ! is_array( $header ) || 1 === count( $header ) ) {
			rewind( $handle );
			$header = fgetcsv( $handle, 0, ';' );
		}

		fclose( $handle );

		if ( empty( $header ) || ! is_array( $header ) || count( $header ) > 200 ) {
			return array( 'error' => __( 'Het CSV-bestand heeft geen geldige headerstructuur.', 'acf-page-text-manager' ) );
		}

		$normalized = array_filter(
			array_map(
				static function ( $value ): string {
					$value = preg_replace( '/^\xEF\xBB\xBF/u', '', (string) $value );
					$value = trim( strtolower( (string) $value ) );
					$value = preg_replace( '/[^a-z0-9_]+/u', '_', (string) $value );
					return trim( (string) $value, '_' );
				},
				$header
			)
		);

		if ( empty( $normalized ) ) {
			return array( 'error' => __( 'Het CSV-bestand heeft geen leesbare kolomkoppen.', 'acf-page-text-manager' ) );
		}

		return array();
	}
	// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	private function validate_xlsx_file( string $path ): array {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return array( 'error' => __( 'XLSX-import vereist de PHP ZipArchive-extensie.', 'acf-page-text-manager' ) );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $path ) ) {
			return array( 'error' => __( 'Het XLSX-bestand kon niet worden geopend.', 'acf-page-text-manager' ) );
		}

		$has_content_types = false !== $zip->locateName( '[Content_Types].xml' );
		$has_workbook      = false !== $zip->locateName( 'xl/workbook.xml' );
		$has_sheet         = false;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( preg_match( '#^xl/worksheets/sheet[0-9]+\.xml$#', $name ) ) {
				$has_sheet = true;
				break;
			}
		}

		$zip->close();

		if ( ! $has_content_types || ! $has_workbook || ! $has_sheet ) {
			return array( 'error' => __( 'Het XLSX-bestand heeft geen herkenbare spreadsheetstructuur.', 'acf-page-text-manager' ) );
		}

		return array();
	}

	private function get_allowed_mimes(): array {
		return array(
			'csv'  => 'text/csv',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'zip'  => 'application/zip',
		);
	}

	private function validate_zip_file( string $path ): array {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return array( 'error' => __( 'ZIP-import vereist de PHP ZipArchive-extensie.', 'acf-page-text-manager' ) );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $path ) ) {
			return array( 'error' => __( 'Het ZIP-bestand kon niet worden geopend.', 'acf-page-text-manager' ) );
		}

		$allowed_extensions = array( 'csv', 'xlsx' );
		$allowed_files      = 0;
		$total_size         = 0;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( '' === $name || '/' === substr( $name, -1 ) || $this->is_unsafe_zip_entry_name( $name ) ) {
				continue;
			}

			$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed_extensions, true ) ) {
				continue;
			}

			$stat = $zip->statIndex( $i );
			$size = is_array( $stat ) && isset( $stat['size'] ) ? (int) $stat['size'] : -1;
			if ( $size < 1 || $size > self::MAX_IMPORT_FILE_SIZE ) {
				$zip->close();
				return array(
					'error' => sprintf(
						/* translators: %s: maximum file size in megabytes. */
						__( 'Elk bestand in een ZIP-import mag maximaal %s MB zijn.', 'acf-page-text-manager' ),
						'10'
					),
				);
			}

			$allowed_files++;
			$total_size += $size;

			if ( $allowed_files > self::MAX_ZIP_IMPORT_FILES ) {
				$zip->close();
				return array(
					'error' => sprintf(
						/* translators: %s: maximum number of CSV or XLSX files in the ZIP import. */
						__( 'Een ZIP-import mag maximaal %s CSV- of XLSX-bestanden bevatten.', 'acf-page-text-manager' ),
						(string) self::MAX_ZIP_IMPORT_FILES
					),
				);
			}

			if ( $total_size > self::MAX_ZIP_TOTAL_UNCOMPRESSED_SIZE ) {
				$zip->close();
				return array(
					'error' => sprintf(
						/* translators: %s: maximum uncompressed ZIP size in megabytes. */
						__( 'De uitgepakte ZIP-import mag samen maximaal %s MB zijn.', 'acf-page-text-manager' ),
						'50'
					),
				);
			}
		}

		$zip->close();

		if ( 0 === $allowed_files ) {
			return array( 'error' => __( 'Het ZIP-bestand bevat geen CSV- of XLSX-bestanden.', 'acf-page-text-manager' ) );
		}

		return array();
	}

	private function expand_zip_upload( array $stored ): array {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return array( 'error' => __( 'ZIP-import vereist de PHP ZipArchive-extensie.', 'acf-page-text-manager' ) );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( (string) $stored['file'] ) ) {
			return array( 'error' => __( 'Het ZIP-bestand kon niet worden geopend.', 'acf-page-text-manager' ) );
		}

		$files      = array();
		$total_size = 0;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry_name = (string) $zip->getNameIndex( $i );
			if ( '' === $entry_name || '/' === substr( $entry_name, -1 ) || $this->is_unsafe_zip_entry_name( $entry_name ) ) {
				continue;
			}

			$sanitized_name = sanitize_file_name( (string) basename( $entry_name ) );
			$ext            = strtolower( (string) pathinfo( $sanitized_name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'csv', 'xlsx' ), true ) ) {
				continue;
			}

			$stat = $zip->statIndex( $i );
			$size = is_array( $stat ) && isset( $stat['size'] ) ? (int) $stat['size'] : -1;
			if ( $size < 1 || $size > self::MAX_IMPORT_FILE_SIZE ) {
				$this->cleanup_uploads( $files );
				$zip->close();
				return array(
					'error' => sprintf(
						/* translators: %s: maximum file size in megabytes. */
						__( 'Elk bestand in een ZIP-import mag maximaal %s MB zijn.', 'acf-page-text-manager' ),
						'10'
					),
				);
			}

			if ( count( $files ) >= self::MAX_ZIP_IMPORT_FILES ) {
				$this->cleanup_uploads( $files );
				$zip->close();
				return array(
					'error' => sprintf(
						/* translators: %s: maximum number of CSV or XLSX files in the ZIP import. */
						__( 'Een ZIP-import mag maximaal %s CSV- of XLSX-bestanden bevatten.', 'acf-page-text-manager' ),
						(string) self::MAX_ZIP_IMPORT_FILES
					),
				);
			}

			$total_size += $size;
			if ( $total_size > self::MAX_ZIP_TOTAL_UNCOMPRESSED_SIZE ) {
				$this->cleanup_uploads( $files );
				$zip->close();
				return array(
					'error' => sprintf(
						/* translators: %s: maximum uncompressed ZIP size in megabytes. */
						__( 'De uitgepakte ZIP-import mag samen maximaal %s MB zijn.', 'acf-page-text-manager' ),
						'50'
					),
				);
			}

			$temp_file = wp_tempnam( $sanitized_name );
			if ( ! $temp_file ) {
				$this->cleanup_uploads( $files );
				$zip->close();
				return array( 'error' => __( 'Er kon geen tijdelijk bestand worden aangemaakt.', 'acf-page-text-manager' ) );
			}

			if ( ! $this->copy_zip_entry_to_temp_file( $zip, $i, $temp_file, self::MAX_IMPORT_FILE_SIZE ) ) {
				Temp_File_Service::delete( $temp_file );
				$this->cleanup_uploads( $files );
				$zip->close();
				return array( 'error' => __( 'Het ZIP-bestand kon niet veilig worden verwerkt.', 'acf-page-text-manager' ) );
			}

			$validation = 'csv' === $ext ? $this->validate_csv_file( $temp_file ) : $this->validate_xlsx_file( $temp_file );
			if ( isset( $validation['error'] ) ) {
				Temp_File_Service::delete( $temp_file );
				$this->cleanup_uploads( $files );
				$zip->close();
				return $validation;
			}

			$files[] = array(
				'file' => $temp_file,
				'name' => $sanitized_name,
				'ext'  => $ext,
			);
		}

		$zip->close();

		if ( empty( $files ) ) {
			return array( 'error' => __( 'Het ZIP-bestand bevat geen CSV- of XLSX-bestanden.', 'acf-page-text-manager' ) );
		}

		return array( 'files' => $files );
	}

	private function is_unsafe_zip_entry_name( string $entry_name ): bool {
		$normalized = str_replace( '\\', '/', $entry_name );
		$segments   = array_filter( explode( '/', $normalized ), static fn ( string $segment ): bool => '' !== $segment );

		return 0 === strpos( $normalized, '/' )
			|| preg_match( '#^[a-z]:/#i', $normalized )
			|| in_array( '..', $segments, true );
	}

	// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fread,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- ZipArchive stream copying requires PHP stream functions with byte limits.
	private function copy_zip_entry_to_temp_file( \ZipArchive $zip, int $index, string $destination, int $max_bytes ): bool {
		$source = $zip->getStream( $zip->getNameIndex( $index ) );
		if ( false === $source ) {
			return false;
		}

		$target = fopen( $destination, 'wb' );
		if ( false === $target ) {
			fclose( $source );
			return false;
		}

		$bytes_written = 0;
		$ok            = true;

		while ( ! feof( $source ) ) {
			$chunk = fread( $source, 8192 );
			if ( false === $chunk ) {
				$ok = false;
				break;
			}

			$bytes_written += strlen( $chunk );
			if ( $bytes_written > $max_bytes ) {
				$ok = false;
				break;
			}

			if ( '' !== $chunk && false === fwrite( $target, $chunk ) ) {
				$ok = false;
				break;
			}
		}

		fclose( $source );
		fclose( $target );

		return $ok && $bytes_written > 0;
	}

	// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fread,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

	private function cleanup_uploads( array $uploads ): void {
		foreach ( $uploads as $upload ) {
			if ( ! empty( $upload['file'] ) && is_string( $upload['file'] ) && file_exists( $upload['file'] ) ) {
				Temp_File_Service::delete( (string) $upload['file'] );
			}
		}
	}


	private function normalize_files_array( array $file ): array {
		if ( ! isset( $file['name'] ) || ! is_array( $file['name'] ) ) {
			return array( $file );
		}

		$normalized = array();

		foreach ( array_keys( $file['name'] ) as $index ) {
			$normalized[] = array(
				'name'     => $file['name'][ $index ] ?? '',
				'type'     => $file['type'][ $index ] ?? '',
				'tmp_name' => $file['tmp_name'][ $index ] ?? '',
				'error'    => $file['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $file['size'][ $index ] ?? 0,
			);
		}

		return $normalized;
	}

	private function code_to_message( int $code ): string {
		switch ( $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'Het bestand is te groot.', 'acf-page-text-manager' );

			case UPLOAD_ERR_PARTIAL:
				return __( 'Het bestand is maar gedeeltelijk geüpload.', 'acf-page-text-manager' );

			case UPLOAD_ERR_NO_FILE:
				return __( 'Er is geen bestand ontvangen.', 'acf-page-text-manager' );

			default:
				return __( 'Onbekende uploadfout.', 'acf-page-text-manager' );
		}
	}
}
