<?php
/**
 * Uninstall script for ACF Page Text Manager.
 *
 * Runs when an admin deletes the plugin from the Plugins screen.
 * Removes plugin-owned options and transients from every site on a
 * multisite network, or from the single site on a non-multisite install.
 *
 * Does NOT touch ACF data, page/post content, or media — only the
 * plugin's own settings and transients.
 *
 * @package WA_ACF_PTM
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin options and transients on a single site.
 *
 * Must be called inside the correct site context on multisite
 * (the caller is responsible for switch_to_blog / restore_current_blog).
 */
function wa_acf_ptm_uninstall_cleanup_current_site() {
	global $wpdb;

	delete_option( 'wa_acf_ptm_settings' );
	delete_option( 'wa_acf_ptm_media_rename_log' );

	if ( isset( $wpdb ) && isset( $wpdb->options ) ) {
		$transient_key = $wpdb->esc_like( '_transient_wa_acf_ptm_' ) . '%';
		$timeout_key   = $wpdb->esc_like( '_transient_timeout_wa_acf_ptm_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup removes plugin-owned transient leftovers by prefix.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $transient_key, $timeout_key ) );
	}
}

if ( is_multisite() ) {
	// Multisite: clean up every site on the network.
	$wa_acf_ptm_site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 0,
			'update_site_cache'      => false,
			'update_site_meta_cache' => false,
		)
	);

	if ( is_array( $wa_acf_ptm_site_ids ) ) {
		foreach ( $wa_acf_ptm_site_ids as $wa_acf_ptm_site_id ) {
			switch_to_blog( (int) $wa_acf_ptm_site_id );
			wa_acf_ptm_uninstall_cleanup_current_site();
			restore_current_blog();
		}
	}

	// Also clean up any sitemeta transients we may have used (defensive — none used currently).
	delete_site_option( 'wa_acf_ptm_settings' );
	delete_site_option( 'wa_acf_ptm_media_rename_log' );
} else {
	wa_acf_ptm_uninstall_cleanup_current_site();
}
