<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * Main add-on loader for WP Loupe Admin.
 */
class WP_Loupe_Admin_Loader {
	/** @var self|null */
	private static $instance = null;

	/** @var array<int,string> */
	private $post_types = [];

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->setup_post_types();

		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-rest.php';

		$rest_controller = new WP_Loupe_Admin_REST( $this->post_types );
		$rest_controller->register();

		if ( is_admin() ) {
			require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-search.php';

			$admin_search = new WP_Loupe_Admin_Search( $this->post_types );
			$admin_search->register();
		}
	}

	/**
	 * Resolve post types from WP Loupe settings.
	 *
	 * @return void
	 */
	private function setup_post_types(): void {
		$options = get_option( 'wp_loupe_custom_post_types', [] );

		if ( ! empty( $options ) && isset( $options['wp_loupe_post_type_field'] ) ) {
			$this->post_types = (array) $options['wp_loupe_post_type_field'];
		} else {
			$this->post_types = [ 'post', 'page' ];
		}

		$this->post_types = apply_filters( 'wp_loupe_post_types', $this->post_types );
	}

}