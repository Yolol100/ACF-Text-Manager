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
	}

	public function deactivate(): void {
		$repository = new Page_Repository();
		$repository->clear_cache();
		delete_transient( 'wa_acf_ptm_target_index' );
		for ( $version = 2; $version <= 12; $version++ ) {
			delete_transient( 'wa_acf_ptm_target_index_v' . $version );
		}

		global $wpdb;

		if ( isset( $wpdb ) && isset( $wpdb->options ) ) {
			$detail_key         = $wpdb->esc_like( '_transient_wa_acf_ptm_target_detail_' ) . '%';
			$detail_timeout_key = $wpdb->esc_like( '_transient_timeout_wa_acf_ptm_target_detail_' ) . '%';
			$plan_key           = $wpdb->esc_like( '_transient_wa_acf_ptm_plan_' ) . '%';
			$plan_timeout_key   = $wpdb->esc_like( '_transient_timeout_wa_acf_ptm_plan_' ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deactivation cleanup removes plugin-owned transient leftovers.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $detail_key, $detail_timeout_key, $plan_key, $plan_timeout_key ) );
		}
	}
}
