<?php
/**
 * SEO AI Uninstall.
 *
 * Handles complete removal of all plugin data when the plugin is deleted
 * through the WordPress admin interface.
 *
 * @package SeoAi
 * @since   1.0.0
 */

// Abort if not called by WordPress during uninstall.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Delete plugin options.
delete_option( 'seo_ai_settings' );
delete_option( 'seo_ai_providers' );
delete_option( 'seo_ai_version' );

// Delete all post meta entries with plugin prefix.
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_seo_ai_' ) . '%'
	)
);

// Drop custom database tables.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}seo_ai_redirects" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}seo_ai_404_log" );

// Remove custom capabilities from all roles.
$capabilities = [
	'seo_ai_manage_settings',
	'seo_ai_manage_redirects',
	'seo_ai_view_reports',
];

foreach ( wp_roles()->roles as $role_name => $role_info ) {
	$role = get_role( $role_name );
	if ( $role ) {
		foreach ( $capabilities as $cap ) {
			$role->remove_cap( $cap );
		}
	}
}

// Clean up transients with the plugin prefix.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$transient_keys = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_seo_ai_' ) . '%'
	)
);

if ( ! empty( $transient_keys ) ) {
	foreach ( $transient_keys as $key ) {
		$transient_name = str_replace( '_transient_', '', $key );
		delete_transient( $transient_name );
	}
}

// Flush rewrite rules.
flush_rewrite_rules();
