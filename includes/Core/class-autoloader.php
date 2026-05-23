<?php
namespace WA_ACF_PTM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {
	/**
	 * @var array<string, string>
	 */
	private static array $class_map = array(
		'WA_ACF_PTM\\Core\\Autoloader'         => 'includes/Core/class-autoloader.php',
		'WA_ACF_PTM\\Core\\Container'          => 'includes/Core/class-container.php',
		'WA_ACF_PTM\\Core\\Lifecycle_Manager'  => 'includes/Core/class-lifecycle-manager.php',
		'WA_ACF_PTM\\Core\\Plugin'             => 'includes/Core/class-plugin.php',
	);

	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	public static function load( string $class ): void {
		if ( isset( self::$class_map[ $class ] ) ) {
			$path = WA_ACF_PTM_PATH . self::$class_map[ $class ];
			if ( is_readable( $path ) ) {
				require_once $path;
			}
			return;
		}

		$prefix = 'WA_ACF_PTM\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative       = substr( $class, strlen( $prefix ) );
		$parts          = explode( '\\', $relative );
		$class_name     = array_pop( $parts );
		$file_base      = strtolower( str_replace( '_', '-', $class_name ) );
		$file_name      = 'class-' . $file_base . '.php';
		$trait_file_name = $file_base . '.php';
		$modern_dir     = WA_ACF_PTM_PATH . 'includes/' . implode( '/', $parts ) . '/';
		$legacy_parts   = array_map( 'strtolower', $parts );
		$legacy_dir     = WA_ACF_PTM_PATH . 'includes/' . implode( '/', $legacy_parts ) . '/';

		foreach ( array( $modern_dir . $file_name, $modern_dir . $trait_file_name, $legacy_dir . $file_name, $legacy_dir . $trait_file_name ) as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
