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
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-schema.php';
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-indexer.php';
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-query-integration.php';

		$schema        = new WP_Loupe_Admin_Schema();
		$admin_indexer = new WP_Loupe_Admin_Indexer( $this->post_types, $schema );

		$rest_controller = new WP_Loupe_Admin_REST( $this->post_types, $admin_indexer );
		$rest_controller->register();
		$admin_indexer->register();

		if ( $admin_indexer->needs_initial_index() ) {
			add_action( 'admin_init', [ $admin_indexer, 'reindex_all' ] );
		}

		$query_integration = new WP_Loupe_Admin_Query_Integration( $this->post_types, $admin_indexer );
		$query_integration->register();

		if ( is_admin() ) {
			require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-search.php';

			$admin_search = new WP_Loupe_Admin_Search( $this->post_types );
			$admin_search->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-cli.php';

			\WP_CLI::add_command( 'loupe-admin', new WP_Loupe_Admin_CLI( $admin_indexer, $this->post_types ) );
		}
	}

	/**
	 * Resolve post types from WP Loupe settings.
	 *
	 * @return void
	 */
	private function setup_post_types(): void {
		$options = get_option( 'wp_loupe_custom_post_types', [] );

		if ( ! empty( $options ) && isset( $options[ 'wp_loupe_post_type_field' ] ) ) {
			$this->post_types = (array) $options[ 'wp_loupe_post_type_field' ];
		} else {
			$this->post_types = [ 'post', 'page' ];
		}

		$this->post_types = apply_filters( 'wp_loupe_post_types', $this->post_types );
	}

}