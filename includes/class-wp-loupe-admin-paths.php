<?php
/**
 * Index path resolution shared by the admin indexers.
 *
 * @package Soderlind\Plugin\WPLoupeAdmin
 */

declare(strict_types=1);

namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * Resolves the directory Loupe Search stores its indexes in, so the admin
 * indexes land alongside them under `/admin`.
 *
 * Mirrors the resolution in Loupe Search's WP_Loupe_DB: the default is
 * `wp-content/loupe-search-db`, except on installs carried over from WP Loupe,
 * where the legacy `wp-content/wp-loupe-db` directory is kept.
 */
class WP_Loupe_Admin_Paths {

	/**
	 * Resolve the base directory for the admin indexes.
	 *
	 * @return string Absolute path without a trailing slash, or an empty string
	 *                when WP_CONTENT_DIR is unavailable and no filter supplies one.
	 */
	public static function admin_db_base(): string {
		$default = self::default_db_path();

		/** This filter is documented in Loupe Search's class-wp-loupe-db.php */
		$base = apply_filters( 'loupe_search_db_path', $default );
		$base = is_string( $base ) ? trim( $base ) : '';

		if ( '' === $base ) {
			$base = $default;
		}

		if ( '' === $base ) {
			return '';
		}

		return rtrim( $base, '/' ) . '/admin';
	}

	/**
	 * Determine the default index directory, preferring a pre-existing legacy one.
	 *
	 * @return string
	 */
	private static function default_db_path(): string {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			return '';
		}

		$default = WP_CONTENT_DIR . '/loupe-search-db';
		$legacy  = WP_CONTENT_DIR . '/wp-loupe-db';

		if ( is_dir( $legacy ) && ! is_dir( $default ) ) {
			return $legacy;
		}

		return $default;
	}
}
