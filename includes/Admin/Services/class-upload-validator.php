<?php
namespace WA_ACF_PTM\Admin\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Upload_Validator {
	private const MAX_IMPORT_FILE_SIZE = 10485760;

	private Spreadsheet_Upload_Service $spreadsheet;
	private Zip_Import_Service $zip_importer;

	public function __construct( ?Spreadsheet_Upload_Service $spreadsheet = null, ?Zip_Import_Service $zip_importer = null ) {
		$this->spreadsheet = $spreadsheet ?? new Spreadsheet_Upload_Service();
		$this->zip_importer = $zip_importer ?? new Zip_Import_Service( $this->spreadsheet );
	}

	public function validate_file( array $file ): array {
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
		$allowed_exts  = array_keys( $this->spreadsheet->get_allowed_mimes() );

		if ( '' === $original_name || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return array( 'error' => __( 'Het geüploade bestand kon niet worden gelezen.', 'acf-page-text-manager' ) );
		}

		$ext = strtolower( (string) pathinfo( $original_name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			return array( 'error' => __( 'Alleen CSV-, XLSX- en ZIP-bestanden zijn toegestaan.', 'acf-page-text-manager' ) );
		}

		$type_validation = $this->spreadsheet->validate_upload_filetype( $tmp_name, $original_name, $ext );
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
			return $this->spreadsheet->validate_csv_file( $tmp_name );
		}

		if ( 'xlsx' === $ext ) {
			return $this->spreadsheet->validate_xlsx_file( $tmp_name );
		}

		if ( 'zip' === $ext ) {
			return $this->zip_importer->validate_zip_file( $tmp_name );
		}

		return array();
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
