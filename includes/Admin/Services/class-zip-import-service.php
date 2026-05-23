<?php
namespace WA_ACF_PTM\Admin\Services;

use WA_ACF_PTM\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Zip_Import_Service {
	private const MAX_IMPORT_FILE_SIZE = 10485760;
	private const MAX_ZIP_TOTAL_UNCOMPRESSED_SIZE = 52428800;

	private Spreadsheet_Upload_Service $spreadsheet;

	public function __construct( ?Spreadsheet_Upload_Service $spreadsheet = null ) {
		$this->spreadsheet = $spreadsheet ?? new Spreadsheet_Upload_Service();
	}

	public function validate_zip_file( string $path ): array {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return array( 'error' => __( 'ZIP-import vereist de PHP ZipArchive-extensie.', 'acf-page-text-manager' ) );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $path ) ) {
			return array( 'error' => __( 'Het ZIP-bestand kon niet worden geopend.', 'acf-page-text-manager' ) );
		}

		$allowed_files = 0;
		$total_size    = 0;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = $this->get_importable_zip_entry_data( $zip, $i, $allowed_files, $total_size );

			if ( ! empty( $entry['skip'] ) ) {
				continue;
			}

			if ( isset( $entry['error'] ) ) {
				$zip->close();
				return array( 'error' => $entry['error'] );
			}

			$allowed_files++;
			$total_size += (int) $entry['size'];
		}

		$zip->close();

		if ( 0 === $allowed_files ) {
			return array( 'error' => __( 'Het ZIP-bestand bevat geen CSV- of XLSX-bestanden.', 'acf-page-text-manager' ) );
		}

		return array();
	}

	public function expand_zip_upload( array $stored ): array {
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
			$entry = $this->get_importable_zip_entry_data( $zip, $i, count( $files ), $total_size );

			if ( ! empty( $entry['skip'] ) ) {
				continue;
			}

			if ( isset( $entry['error'] ) ) {
				Temp_File_Service::delete_upload_files( $files );
				$zip->close();
				return array( 'error' => $entry['error'] );
			}

			$total_size += (int) $entry['size'];
			$temp_file = wp_tempnam( (string) $entry['name'] );
			if ( ! $temp_file ) {
				Temp_File_Service::delete_upload_files( $files );
				$zip->close();
				return array( 'error' => __( 'Er kon geen tijdelijk bestand worden aangemaakt.', 'acf-page-text-manager' ) );
			}

			if ( ! $this->copy_zip_entry_to_temp_file( $zip, $i, $temp_file, self::MAX_IMPORT_FILE_SIZE ) ) {
				Temp_File_Service::delete( $temp_file );
				Temp_File_Service::delete_upload_files( $files );
				$zip->close();
				return array( 'error' => __( 'Het ZIP-bestand kon niet veilig worden verwerkt.', 'acf-page-text-manager' ) );
			}

			$validation = 'csv' === $entry['ext'] ? $this->spreadsheet->validate_csv_file( $temp_file ) : $this->spreadsheet->validate_xlsx_file( $temp_file );
			if ( isset( $validation['error'] ) ) {
				Temp_File_Service::delete( $temp_file );
				Temp_File_Service::delete_upload_files( $files );
				$zip->close();
				return $validation;
			}

			$files[] = array(
				'file' => $temp_file,
				'name' => (string) $entry['name'],
				'ext'  => (string) $entry['ext'],
			);
		}

		$zip->close();

		if ( empty( $files ) ) {
			return array( 'error' => __( 'Het ZIP-bestand bevat geen CSV- of XLSX-bestanden.', 'acf-page-text-manager' ) );
		}

		return array( 'files' => $files );
	}

	private function get_importable_zip_entry_data( \ZipArchive $zip, int $index, int $current_file_count, int $current_total_size ): array {
		$entry_name = (string) $zip->getNameIndex( $index );
		if ( '' === $entry_name || '/' === substr( $entry_name, -1 ) ) {
			return array( 'skip' => true );
		}

		if ( self::is_unsafe_zip_entry_name( $entry_name ) ) {
			return array( 'error' => __( 'Het ZIP-bestand bevat onveilige paden.', 'acf-page-text-manager' ) );
		}

		$sanitized_name = sanitize_file_name( (string) basename( $entry_name ) );
		$ext            = strtolower( (string) pathinfo( $sanitized_name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'xlsx' ), true ) ) {
			return array( 'error' => __( 'ZIP-imports mogen alleen CSV- en XLSX-bestanden bevatten.', 'acf-page-text-manager' ) );
		}

		$stat = $zip->statIndex( $index );
		$size = is_array( $stat ) && isset( $stat['size'] ) ? (int) $stat['size'] : -1;
		if ( $size < 1 || $size > self::MAX_IMPORT_FILE_SIZE ) {
			return array(
				'error' => sprintf(
					/* translators: %s: maximum file size in megabytes. */
					__( 'Elk bestand in een ZIP-import mag maximaal %s MB zijn.', 'acf-page-text-manager' ),
					'10'
				),
			);
		}

		if ( $current_file_count + 1 > Settings::get_max_zip_files() ) {
			return array(
				'error' => sprintf(
					/* translators: %d: maximum number of files in an import ZIP. */
					__( 'Een ZIP-import mag maximaal %d CSV/XLSX-bestanden bevatten.', 'acf-page-text-manager' ),
					Settings::get_max_zip_files()
				),
			);
		}

		if ( $current_total_size + $size > self::MAX_ZIP_TOTAL_UNCOMPRESSED_SIZE ) {
			return array(
				'error' => sprintf(
					/* translators: %s: maximum uncompressed ZIP size in megabytes. */
					__( 'De uitgepakte ZIP-import mag samen maximaal %s MB zijn.', 'acf-page-text-manager' ),
					'50'
				),
			);
		}

		return array(
			'name' => $sanitized_name,
			'ext'  => $ext,
			'size' => $size,
		);
	}

	public static function is_unsafe_zip_entry_name( string $entry_name ): bool {
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

}
