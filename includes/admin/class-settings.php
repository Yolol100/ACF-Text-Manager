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
		);
	}
}
