<?php
namespace WA_ACF_PTM\Core;

use WA_ACF_PTM\Admin\Page_Repository;
use WA_ACF_PTM\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Lifecycle_Manager {
	public function register(): void {
		register_activation_hook( WA_ACF_PTM_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( WA_ACF_PTM_FILE, array( $this, 'deactivate' ) );
	}

	public function activate(): void {
		add_option( Settings::OPTION_KEY, Settings::defaults() );
		( new Upgrade_Cleaner() )->mark_installed();
	}

	public function deactivate(): void {
		$repository = new Page_Repository();
		$repository->clear_cache();
		global $wpdb;

		if ( isset( $wpdb ) && isset( $wpdb->options ) ) {
			$transient_key = $wpdb->esc_like( '_transient_wa_acf_ptm_' ) . '%';
			$timeout_key   = $wpdb->esc_like( '_transient_timeout_wa_acf_ptm_' ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deactivation cleanup removes plugin-owned transient leftovers by prefix.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $transient_key, $timeout_key ) );
		}
	}
}
