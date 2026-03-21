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

		// Core classes.
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-rest.php';
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-schema.php';
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-indexer.php';
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-query-integration.php';

		// Entity indexers.
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-user-indexer.php';
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-comment-indexer.php';
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-plugin-indexer.php';

		// Query integrations.
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-user-query-integration.php';
		require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-comment-query-integration.php';

		$schema = new WP_Loupe_Admin_Schema();

		// Post type indexer.
		$admin_indexer = new WP_Loupe_Admin_Indexer( $this->post_types, $schema );
		$admin_indexer->register();

		if ( $admin_indexer->needs_initial_index() ) {
			add_action( 'admin_init', [ $admin_indexer, 'reindex_all' ] );
		}

		// Entity indexers.
		$user_indexer    = new WP_Loupe_Admin_User_Indexer( $schema );
		$comment_indexer = new WP_Loupe_Admin_Comment_Indexer( $schema );
		$plugin_indexer  = new WP_Loupe_Admin_Plugin_Indexer( $schema );

		$user_indexer->register();
		$comment_indexer->register();
		$plugin_indexer->register();

		if ( $user_indexer->needs_initial_index() ) {
			add_action( 'admin_init', [ $user_indexer, 'reindex_all' ] );
		}
		if ( $comment_indexer->needs_initial_index() ) {
			add_action( 'admin_init', [ $comment_indexer, 'reindex_all' ] );
		}
		if ( $plugin_indexer->needs_initial_index() ) {
			add_action( 'admin_init', [ $plugin_indexer, 'reindex_all' ] );
		}

		// REST controller — receives all indexers.
		$rest_controller = new WP_Loupe_Admin_REST(
			$this->post_types,
			$admin_indexer,
			$user_indexer,
			$comment_indexer,
			$plugin_indexer
		);
		$rest_controller->register();

		// Query integrations.
		$post_query_integration = new WP_Loupe_Admin_Query_Integration( $this->post_types, $admin_indexer );
		$post_query_integration->register();

		$user_query_integration = new WP_Loupe_Admin_User_Query_Integration( $user_indexer );
		$user_query_integration->register();

		$comment_query_integration = new WP_Loupe_Admin_Comment_Query_Integration( $comment_indexer );
		$comment_query_integration->register();

		if ( is_admin() ) {
			require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-search.php';
			require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-notice.php';

			$admin_search = new WP_Loupe_Admin_Search( $this->post_types );
			$admin_search->register();

			$admin_notice = new WP_Loupe_Admin_Notice( $admin_indexer, $user_indexer, $comment_indexer, $plugin_indexer );
			$admin_notice->register();
		} else {
			// Frontend: always load search class; its methods gate on is_admin_bar_showing()
			// at render time (too early to check here at plugins_loaded).
			require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-search.php';

			$admin_search = new WP_Loupe_Admin_Search( $this->post_types );
			$admin_search->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once WP_LOUPE_ADMIN_PATH . 'includes/class-wp-loupe-admin-cli.php';

			\WP_CLI::add_command(
				'loupe-admin',
				new WP_Loupe_Admin_CLI(
					$admin_indexer,
					$this->post_types,
					$user_indexer,
					$comment_indexer,
					$plugin_indexer
				)
			);
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