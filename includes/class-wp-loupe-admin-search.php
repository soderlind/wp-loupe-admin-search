<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * Admin UI for WP Loupe Admin.
 */
class WP_Loupe_Admin_Search {
	/** @var array<int,string> */
	private $post_types = [];

	/**
	 * @param array<int,string> $post_types Indexed post types.
	 */
	public function __construct( array $post_types ) {
		$this->post_types = $post_types;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_item' ], 85 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_footer', [ $this, 'render_modal' ] );
	}

	/**
	 * Check if the current user can access the admin search UI.
	 *
	 * @return bool
	 */
	private function current_user_can_access_search(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) || current_user_can( 'activate_plugins' ) || current_user_can( 'list_users' ) || current_user_can( 'edit_users' ) ) {
			return true;
		}

		foreach ( $this->post_types as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			if ( ! $post_type_object || empty( $post_type_object->cap->edit_posts ) ) {
				continue;
			}

			if ( current_user_can( $post_type_object->cap->edit_posts ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register dashboard widget.
	 *
	 * @return void
	 */
	public function register_dashboard_widget(): void {
		if ( ! $this->current_user_can_access_search() || ! is_blog_admin() ) {
			return;
		}

		wp_add_dashboard_widget(
			'wp_loupe_admin_search',
			__( 'WP Loupe Search', 'wp-loupe-admin' ),
			[ $this, 'render_dashboard_widget' ]
		);
	}

	/**
	 * Render dashboard widget content.
	 *
	 * @return void
	 */
	public function render_dashboard_widget(): void {
		$this->render_search_shell(
			'widget',
			__( 'Search admin content', 'wp-loupe-admin' ),
			__( 'Search indexed content, users, and installed plugins from the admin.', 'wp-loupe-admin' )
		);
	}

	/**
	 * Add an admin bar launcher.
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar instance.
	 * @return void
	 */
	public function add_admin_bar_item( \WP_Admin_Bar $admin_bar ): void {
		if ( ! is_admin() || ! $this->current_user_can_access_search() ) {
			return;
		}

		$admin_bar->add_node( [
			'id'     => 'wp-loupe-admin-search',
			'parent' => 'top-secondary',
			'title'  => __( 'Loupe Search', 'wp-loupe-admin' ),
			'href'   => '#wp-loupe-admin-search',
			'meta'   => [
				'class' => 'wp-loupe-admin-search-trigger',
				'title' => __( 'Open WP Loupe admin search', 'wp-loupe-admin' ),
			],
		] );
	}

	/**
	 * Enqueue add-on assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ): void {
		if ( ! $this->current_user_can_access_search() || ! is_admin() ) {
			return;
		}

		wp_register_style(
			'wp-loupe-admin-addon',
			WP_LOUPE_ADMIN_URL . 'lib/css/admin-search.css',
			[],
			WP_LOUPE_ADMIN_VERSION
		);

		wp_register_script(
			'wp-loupe-admin-addon',
			WP_LOUPE_ADMIN_URL . 'lib/js/admin-search.js',
			[ 'wp-api-fetch', 'wp-i18n' ],
			WP_LOUPE_ADMIN_VERSION,
			true
		);

		wp_enqueue_style( 'wp-loupe-admin-addon' );
		wp_enqueue_script( 'wp-loupe-admin-addon' );

		wp_localize_script( 'wp-loupe-admin-addon', 'wpLoupeAdminSearch', [
			'path'    => '/wp-loupe-admin/v1/search',
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'labels'  => [
				'emptyQuery' => __( 'Enter a search term.', 'wp-loupe-admin' ),
				'loading'    => __( 'Searching...', 'wp-loupe-admin' ),
				'noResults'  => __( 'No matching results found.', 'wp-loupe-admin' ),
				'error'      => __( 'Search failed. Please try again.', 'wp-loupe-admin' ),
				'previous'   => __( 'Previous', 'wp-loupe-admin' ),
				'next'       => __( 'Next', 'wp-loupe-admin' ),
				'page'       => __( 'Page', 'wp-loupe-admin' ),
				'of'         => __( 'of', 'wp-loupe-admin' ),
				'content'    => __( 'Content', 'wp-loupe-admin' ),
				'users'      => __( 'Users', 'wp-loupe-admin' ),
				'plugins'    => __( 'Plugins', 'wp-loupe-admin' ),
			],
			'perPage' => 'index.php' === $hook ? 8 : 10,
		] );
	}

	/**
	 * Render shared modal markup.
	 *
	 * @return void
	 */
	public function render_modal(): void {
		if ( ! $this->current_user_can_access_search() || ! is_admin() ) {
			return;
		}

		?>
		<div id="wp-loupe-admin-search-modal" class="wp-loupe-admin-search-modal" hidden>
			<div class="wp-loupe-admin-search-backdrop" data-wp-loupe-close="1"></div>
			<div class="wp-loupe-admin-search-panel" role="dialog" aria-modal="true" aria-labelledby="wp-loupe-admin-search-title" tabindex="-1">
				<div class="wp-loupe-admin-search-header">
					<h2 id="wp-loupe-admin-search-title"><?php esc_html_e( 'WP Loupe Search', 'wp-loupe-admin' ); ?></h2>
					<button type="button" class="button-link" data-wp-loupe-close="1" aria-label="<?php esc_attr_e( 'Close search', 'wp-loupe-admin' ); ?>">&times;</button>
				</div>
				<?php
				$this->render_search_shell(
					'modal',
					__( 'Search admin content', 'wp-loupe-admin' ),
					__( 'Search indexed content, users, and installed plugins without leaving the current admin screen.', 'wp-loupe-admin' )
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render search markup.
	 *
	 * @param string $context Context identifier.
	 * @param string $label Search label.
	 * @param string $help Supporting copy.
	 * @return void
	 */
	private function render_search_shell( string $context, string $label, string $help ): void {
		$form_id = sprintf( 'wp-loupe-admin-search-%s', $context );
		?>
		<div class="wp-loupe-admin-search-shell" data-wp-loupe-search-shell="<?php echo esc_attr( $context ); ?>">
			<form class="wp-loupe-admin-search-form" data-wp-loupe-search-form="<?php echo esc_attr( $context ); ?>">
				<label class="screen-reader-text" for="<?php echo esc_attr( $form_id ); ?>"><?php echo esc_html( $label ); ?></label>
				<div class="wp-loupe-admin-search-row">
					<select name="scope" aria-label="<?php esc_attr_e( 'Search scope', 'wp-loupe-admin' ); ?>">
						<option value="content"><?php esc_html_e( 'Content', 'wp-loupe-admin' ); ?></option>
						<?php if ( current_user_can( 'list_users' ) || current_user_can( 'edit_users' ) || current_user_can( 'manage_options' ) ) : ?>
							<option value="users"><?php esc_html_e( 'Users', 'wp-loupe-admin' ); ?></option>
						<?php endif; ?>
						<?php if ( current_user_can( 'activate_plugins' ) || current_user_can( 'manage_options' ) ) : ?>
							<option value="plugins"><?php esc_html_e( 'Plugins', 'wp-loupe-admin' ); ?></option>
						<?php endif; ?>
					</select>
					<input
						type="search"
						id="<?php echo esc_attr( $form_id ); ?>"
						class="regular-text"
						name="q"
						placeholder="<?php esc_attr_e( 'Search content, users, plugins...', 'wp-loupe-admin' ); ?>"
						autocomplete="off"
					/>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Search', 'wp-loupe-admin' ); ?></button>
				</div>
			</form>
			<p class="description"><?php echo esc_html( $help ); ?></p>
			<div class="wp-loupe-admin-search-results" aria-live="polite"></div>
		</div>
		<?php
	}
}