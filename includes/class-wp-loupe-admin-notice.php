<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * Shows admin notices when indexes are missing or empty.
 */
class WP_Loupe_Admin_Notice {

	/** @var WP_Loupe_Admin_Indexer */
	private $post_indexer;

	/** @var WP_Loupe_Admin_User_Indexer */
	private $user_indexer;

	/** @var WP_Loupe_Admin_Comment_Indexer */
	private $comment_indexer;

	/** @var WP_Loupe_Admin_Plugin_Indexer */
	private $plugin_indexer;

	/**
	 * @param WP_Loupe_Admin_Indexer         $post_indexer    Post indexer.
	 * @param WP_Loupe_Admin_User_Indexer    $user_indexer    User indexer.
	 * @param WP_Loupe_Admin_Comment_Indexer $comment_indexer Comment indexer.
	 * @param WP_Loupe_Admin_Plugin_Indexer  $plugin_indexer  Plugin indexer.
	 */
	public function __construct(
		WP_Loupe_Admin_Indexer $post_indexer,
		WP_Loupe_Admin_User_Indexer $user_indexer,
		WP_Loupe_Admin_Comment_Indexer $comment_indexer,
		WP_Loupe_Admin_Plugin_Indexer $plugin_indexer
	) {
		$this->post_indexer    = $post_indexer;
		$this->user_indexer    = $user_indexer;
		$this->comment_indexer = $comment_indexer;
		$this->plugin_indexer  = $plugin_indexer;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', [ $this, 'maybe_show_notice' ] );
	}

	/**
	 * Show a notice when any index needs rebuilding.
	 *
	 * Only visible to users with manage_options capability.
	 *
	 * @return void
	 */
	public function maybe_show_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$stale = [];

		if ( $this->post_indexer->needs_initial_index() ) {
			$stale[] = __( 'content (post types)', 'wp-loupe-admin' );
		}
		if ( $this->user_indexer->needs_initial_index() ) {
			$stale[] = __( 'users', 'wp-loupe-admin' );
		}
		if ( $this->comment_indexer->needs_initial_index() ) {
			$stale[] = __( 'comments', 'wp-loupe-admin' );
		}
		if ( $this->plugin_indexer->needs_initial_index() ) {
			$stale[] = __( 'plugins', 'wp-loupe-admin' );
		}

		if ( empty( $stale ) ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: comma-separated list of index types that need rebuilding */
			__( 'WP Loupe Admin: The following search indexes need rebuilding: %s. Run <code>wp loupe-admin reindex</code> or visit any admin page to trigger auto-rebuild.', 'wp-loupe-admin' ),
			implode( ', ', $stale )
		);

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			wp_kses( $message, [ 'code' => [] ] )
		);
	}
}
