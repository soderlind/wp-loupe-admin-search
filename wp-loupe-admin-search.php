<?php
/**
 * Plugin Name:       WP Loupe - Admin Search
 * Plugin URI:        https://github.com/soderlind/wp-loupe-admin
 * Description:       Admin search add-on for WP Loupe.
 * Version:           1.1.0
 * Author:            Per Soderlind
 * Author URI:        https://soderlind.no
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-loupe-admin
 * Requires at least: 6.8
 * Tested up to:      7.0
 * Requires PHP:      8.3
 * Requires Plugins:  wp-loupe
 */

declare(strict_types=1);

namespace Soderlind\Plugin\WPLoupeAdmin;

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WP_LOUPE_ADMIN_FILE', __FILE__ );
define( 'WP_LOUPE_ADMIN_PATH', plugin_dir_path( WP_LOUPE_ADMIN_FILE ) );
define( 'WP_LOUPE_ADMIN_URL', plugin_dir_url( WP_LOUPE_ADMIN_FILE ) );
define( 'WP_LOUPE_ADMIN_VERSION', '1.1.0' );

require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-loader.php';

// Update checker via GitHub releases.
if ( ! class_exists( \Soderlind\WordPress\GitHubUpdater::class ) ) {
	require_once __DIR__ . '/class-github-updater.php';
}
\Soderlind\WordPress\GitHubUpdater::init(
	github_url: 'https://github.com/soderlind/wp-loupe-admin',
	plugin_file: WP_LOUPE_ADMIN_FILE,
	plugin_slug: 'wp-loupe-admin-search',
	name_regex: '/wp-loupe-admin-search\.zip/',
	branch: 'main',
);

/**
 * Ensure WP Loupe classes are loaded for this request.
 *
 * @return bool
 */
function ensure_wp_loupe_loaded(): bool {
	if ( class_exists( '\\Soderlind\\Plugin\\WPLoupe\\WP_Loupe_Search_Engine' ) ) {
		return true;
	}

	if ( class_exists( '\\Soderlind\\Plugin\\WPLoupe\\WP_Loupe_Loader' ) ) {
		\Soderlind\Plugin\WPLoupe\WP_Loupe_Loader::get_instance();
	}

	return class_exists( '\\Soderlind\\Plugin\\WPLoupe\\WP_Loupe_Search_Engine' );
}

/**
 * Determine whether plugin bootstrap should run.
 *
 * @return bool
 */
function should_bootstrap(): bool {
	return true;
}

/**
 * Bootstrap the add-on plugin.
 *
 * @return void
 */
function bootstrap(): void {
	if ( ! should_bootstrap() ) {
		return;
	}

	if ( ! ensure_wp_loupe_loaded() ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', __NAMESPACE__ . '\\render_dependency_notice' );
		} else {
			// On the frontend, WP Loupe classes may not be loaded. The admin bar
			// search only needs the UI class and talks to the REST API, so register
			// it independently of the full loader.
			require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-search.php';
			$options    = get_option( 'wp_loupe_custom_post_types', [] );
			$post_types = ! empty( $options['wp_loupe_post_type_field'] )
				? (array) $options['wp_loupe_post_type_field']
				: [ 'post', 'page' ];
			$admin_search = new WP_Loupe_Admin_Search( $post_types );
			$admin_search->register();
		}
		return;
	}

	WP_Loupe_Admin_Loader::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap', 20 );

/**
 * Render an admin notice when WP Loupe is unavailable.
 *
 * @return void
 */
function render_dependency_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'WP Loupe Admin requires the WP Loupe plugin to be active.', 'wp-loupe-admin' ) .
		'</p></div>';
}