<?php
/**
 * Uninstall handler for WP Loupe Admin.
 *
 * Removes admin search indexes stored under {wp_loupe_db_path}/admin/.
 *
 * @package Soderlind\Plugin\WPLoupeAdmin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

$default = defined( 'WP_CONTENT_DIR' ) ? ( WP_CONTENT_DIR . '/wp-loupe-db' ) : '';
$base    = apply_filters( 'wp_loupe_db_path', $default );
$base    = is_string( $base ) ? trim( $base ) : '';

if ( '' === $base ) {
	$base = $default;
}

$admin_path = rtrim( $base, '/' ) . '/admin';

if ( '' !== $admin_path && is_dir( $admin_path ) ) {
	if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
	}

	$fs = new WP_Filesystem_Direct( false );
	$fs->rmdir( $admin_path, true );
}
