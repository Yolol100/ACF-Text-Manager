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
		'WA_ACF_PTM\\Core\\Autoloader'         => 'includes/core/class-autoloader.php',
		'WA_ACF_PTM\\Core\\Container'          => 'includes/core/class-container.php',
		'WA_ACF_PTM\\Core\\Lifecycle_Manager'  => 'includes/core/class-lifecycle-manager.php',
		'WA_ACF_PTM\\Core\\Plugin'             => 'includes/core/class-plugin.php',
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

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = array_map( 'strtolower', explode( '\\', $relative ) );
		$class_name = array_pop( $parts );
		$path = WA_ACF_PTM_PATH . 'includes/' . implode( '/', $parts ) . '/class-' . str_replace( '_', '-', $class_name ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
