<?php
namespace WA_ACF_PTM\Admin\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Spreadsheet_Upload_Service {
	private const MAX_ZIP_TOTAL_UNCOMPRESSED_SIZE = 52428800;

	/**
	 * @return array<string,string>
	 */
	public function get_allowed_mimes(): array {
		return array(
			'csv'  => 'text/csv',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'zip'  => 'application/zip',
		);
	}

	public function validate_upload_filetype( string $tmp_name, string $original_name, string $expected_ext ): array {
		if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
			return array();
		}

		$checked     = wp_check_filetype_and_ext( $tmp_name, $original_name, $this->get_allowed_mimes() );
		$checked_ext = isset( $checked['ext'] ) ? strtolower( (string) $checked['ext'] ) : '';

		if ( '' !== $checked_ext && $checked_ext !== $expected_ext ) {
			return array( 'error' => __( 'Het bestandstype komt niet overeen met de bestandsextensie.', 'acf-page-text-manager' ) );
		}

		if ( empty( $checked['type'] ) && 'csv' !== $expected_ext ) {
			return array( 'error' => __( 'Het bestandstype kon niet veilig worden gevalideerd.', 'acf-page-text-manager' ) );
		}

		return array();
	}

	// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV validation requires streaming fgetcsv() from a temporary upload.
	public function validate_csv_file( string $path ): array {
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

	public function validate_xlsx_file( string $path ): array {
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
		$total_size        = 0;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( '' === $name || Zip_Import_Service::is_unsafe_zip_entry_name( $name ) ) {
				$zip->close();
				return array( 'error' => __( 'Het XLSX-bestand bevat onveilige interne paden.', 'acf-page-text-manager' ) );
			}

			$stat = $zip->statIndex( $i );
			$size = is_array( $stat ) && isset( $stat['size'] ) ? (int) $stat['size'] : 0;
			$total_size += max( 0, $size );
			if ( $total_size > self::MAX_ZIP_TOTAL_UNCOMPRESSED_SIZE ) {
				$zip->close();
				return array( 'error' => __( 'Het XLSX-bestand is te groot om veilig te verwerken.', 'acf-page-text-manager' ) );
			}

			if ( preg_match( '#^xl/worksheets/sheet[0-9]+\.xml$#', $name ) ) {
				$has_sheet = true;
			}
		}

		$zip->close();

		if ( ! $has_content_types || ! $has_workbook || ! $has_sheet ) {
			return array( 'error' => __( 'Het XLSX-bestand heeft geen herkenbare spreadsheetstructuur.', 'acf-page-text-manager' ) );
		}

		return array();
	}
}
