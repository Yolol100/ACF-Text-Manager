<?php
namespace WA_ACF_PTM\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'wa_acf_ptm_settings';

	public static function defaults(): array {
		return array(
			'skip_empty_values'  => true,
			'overwrite_existing' => true,
			'import_batch_size'  => 25,
			'delimiter'          => ';',
			'language_mode'      => 'warn',
			'max_zip_files'       => 50,
			'max_import_upload_files' => 25,
		);
	}

	public static function get(): array {
		$value = get_option( self::OPTION_KEY, array() );
		$value = is_array( $value ) ? $value : array();

		$defaults = self::defaults();

		return array(
			'skip_empty_values'  => array_key_exists( 'skip_empty_values', $value ) ? ! empty( $value['skip_empty_values'] ) : $defaults['skip_empty_values'],
			'overwrite_existing' => array_key_exists( 'overwrite_existing', $value ) ? ! empty( $value['overwrite_existing'] ) : $defaults['overwrite_existing'],
			'import_batch_size'  => max( 1, min( 200, absint( $value['import_batch_size'] ?? $defaults['import_batch_size'] ) ) ),
			'delimiter'          => ',' === ( $value['delimiter'] ?? '' ) ? ',' : $defaults['delimiter'],
			'language_mode'      => in_array( $value['language_mode'] ?? '', array( 'off', 'warn', 'strict' ), true ) ? (string) $value['language_mode'] : $defaults['language_mode'],
			'max_zip_files'       => max( 1, min( 50, absint( $value['max_zip_files'] ?? $defaults['max_zip_files'] ) ) ),
			'max_import_upload_files' => max( 1, min( 25, absint( $value['max_import_upload_files'] ?? $defaults['max_import_upload_files'] ) ) ),
		);
	}

	public static function get_manage_capability(): string {
		/**
		 * Filters the capability required to access and operate ACF Page Text Manager.
		 *
		 * Agencies can lower this to edit_pages or a custom capability when their role model allows it.
		 * Keep manage_options as the safest default because imports can change options, SEO fields and media metadata.
		 *
		 * @param string $capability Required capability.
		 */
		$capability = apply_filters( 'wa_acf_ptm_manage_capability', 'manage_options' );
		$capability = is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
		return sanitize_key( $capability );
	}

	public static function get_options_capability(): string {
		/**
		 * Filters the capability required for option-level targets.
		 *
		 * @param string $capability Required capability.
		 */
		$capability = apply_filters( 'wa_acf_ptm_options_capability', self::get_manage_capability() );
		$capability = is_string( $capability ) && '' !== $capability ? $capability : self::get_manage_capability();
		return sanitize_key( $capability );
	}


	public static function get_max_import_upload_files(): int {
		$settings = self::get();
		$max_files = (int) ( $settings['max_import_upload_files'] ?? 25 );

		/**
		 * Filters the maximum number of separate CSV/XLSX/ZIP files accepted in one upload request.
		 *
		 * ZIP contents are still limited separately by wa_acf_ptm_max_zip_files.
		 *
		 * @param int $max_files Maximum number of uploaded import files.
		 */
		$max_files = (int) apply_filters( 'wa_acf_ptm_max_import_upload_files', $max_files );
		return max( 1, min( 25, $max_files ) );
	}

	public static function get_max_zip_files(): int {
		$settings = self::get();
		$max_files = (int) ( $settings['max_zip_files'] ?? 50 );

		/**
		 * Filters the maximum number of CSV/XLSX files allowed inside one import ZIP.
		 *
		 * @param int $max_files Maximum number of import files.
		 */
		$max_files = (int) apply_filters( 'wa_acf_ptm_max_zip_files', $max_files );
		return max( 1, min( 50, $max_files ) );
	}

}
