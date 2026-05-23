<?php
namespace WA_ACF_PTM\Admin\Services;

use WA_ACF_PTM\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class File_Upload_Service {
	private Spreadsheet_Upload_Service $spreadsheet;
	private Upload_Validator $validator;
	private Zip_Import_Service $zip_importer;

	public function __construct( ?Spreadsheet_Upload_Service $spreadsheet = null, ?Upload_Validator $validator = null, ?Zip_Import_Service $zip_importer = null ) {
		$this->spreadsheet  = $spreadsheet ?? new Spreadsheet_Upload_Service();
		$this->zip_importer = $zip_importer ?? new Zip_Import_Service( $this->spreadsheet );
		$this->validator    = $validator ?? new Upload_Validator( $this->spreadsheet, $this->zip_importer );
	}

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

		$max_upload_files = Settings::get_max_import_upload_files();
		if ( count( $files ) > $max_upload_files ) {
			return array(
				'error' => sprintf(
					/* translators: %d: maximum number of upload files. */
					__( 'Je kunt maximaal %d importbestanden tegelijk uploaden.', 'acf-page-text-manager' ),
					$max_upload_files
				),
			);
		}

		$uploads = array();

		foreach ( $files as $file ) {
			$validation = $this->validator->validate_file( $file );

			if ( isset( $validation['error'] ) ) {
				Temp_File_Service::delete_upload_files( $uploads );
				return $validation;
			}

			$stored = $this->store_via_wordpress( $file );

			if ( is_wp_error( $stored ) ) {
				Temp_File_Service::delete_upload_files( $uploads );
				return array( 'error' => $stored->get_error_message() );
			}

			$uploads[] = array(
				'file' => $stored['file'],
				'name' => $stored['name'],
				'ext'  => $stored['ext'],
			);

			if ( 'zip' === $stored['ext'] ) {
				$expanded = $this->zip_importer->expand_zip_upload( $stored );
				Temp_File_Service::delete( (string) $stored['file'] );
				array_pop( $uploads );

				if ( isset( $expanded['error'] ) ) {
					Temp_File_Service::delete_upload_files( $uploads );
					return $expanded;
				}

				$uploads = array_merge( $uploads, $expanded['files'] ?? array() );
			}
		}

		return array( 'files' => $uploads );
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
					'mimes'     => $this->spreadsheet->get_allowed_mimes(),
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

		$stored_file         = wp_normalize_path( (string) $stored['file'] );
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
}
