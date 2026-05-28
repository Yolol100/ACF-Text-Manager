<?php
/**
 * Plugin Name: ACF Page Text Manager
 * Description: Manage ACF, Yoast SEO, Rank Math, image metadata, and page/post summary fields from one admin screen.
 * Plugin URI: https://www.webactueel.nl/acf-page-text-manager/
 * Version: 2.5.25
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Webactueel
 * Author URI: https://www.webactueel.nl/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-page-text-manager
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WA_ACF_PTM_VERSION', '2.5.25' );
define( 'WA_ACF_PTM_FILE', __FILE__ );
define( 'WA_ACF_PTM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WA_ACF_PTM_URL', plugin_dir_url( __FILE__ ) );

require_once WA_ACF_PTM_PATH . 'includes/Core/class-autoloader.php';

WA_ACF_PTM\Core\Autoloader::register();
WA_ACF_PTM\Core\Plugin::boot();
