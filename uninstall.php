<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wa_acf_ptm_settings' );
delete_option( 'wa_acf_ptm_media_rename_log' );
delete_transient( 'wa_acf_ptm_target_index' );
delete_transient( 'wa_acf_ptm_target_index_v2' );
delete_transient( 'wa_acf_ptm_target_index_v3' );
delete_transient( 'wa_acf_ptm_target_index_v4' );
delete_transient( 'wa_acf_ptm_target_index_v5' );
delete_transient( 'wa_acf_ptm_target_index_v6' );
delete_transient( 'wa_acf_ptm_target_index_v7' );
delete_transient( 'wa_acf_ptm_target_index_v8' );
delete_transient( 'wa_acf_ptm_target_index_v9' );
delete_transient( 'wa_acf_ptm_target_index_v10' );
delete_transient( 'wa_acf_ptm_target_index_v11' );
delete_transient( 'wa_acf_ptm_target_index_v12' );

global $wpdb;

if ( isset( $wpdb ) && isset( $wpdb->options ) ) {
	$wa_acf_ptm_detail_key       = $wpdb->esc_like( '_transient_wa_acf_ptm_target_detail_' ) . '%';
	$wa_acf_ptm_detail_timeout   = $wpdb->esc_like( '_transient_timeout_wa_acf_ptm_target_detail_' ) . '%';
	$wa_acf_ptm_plan_key         = $wpdb->esc_like( '_transient_wa_acf_ptm_plan_' ) . '%';
	$wa_acf_ptm_plan_timeout_key = $wpdb->esc_like( '_transient_timeout_wa_acf_ptm_plan_' ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup removes plugin-owned transient leftovers.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $wa_acf_ptm_detail_key, $wa_acf_ptm_detail_timeout, $wa_acf_ptm_plan_key, $wa_acf_ptm_plan_timeout_key ) );
}
