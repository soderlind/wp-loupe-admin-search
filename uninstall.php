<?php
/**
 * Uninstall handler for Loupe - Admin Search.
 *
 * Removes admin search indexes stored under {loupe_search_db_path}/admin/.
 *
 * @package Soderlind\Plugin\WPLoupeAdmin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

require_once __DIR__ . '/includes/class-wp-loupe-admin-paths.php';

$admin_path = \Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_Paths::admin_db_base();

if ( '' !== $admin_path && is_dir( $admin_path ) ) {
	if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
	}

	$fs = new WP_Filesystem_Direct( false );
	$fs->rmdir( $admin_path, true );
}
