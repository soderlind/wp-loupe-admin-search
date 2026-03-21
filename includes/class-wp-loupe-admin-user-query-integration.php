<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * Server-side integration for native admin user search (users.php).
 *
 * Intercepts WP_User_Query via `pre_get_users` when on the native
 * user list table and delegates search to the admin user Loupe index.
 */
class WP_Loupe_Admin_User_Query_Integration {

	/** @var WP_Loupe_Admin_User_Indexer */
	private $indexer;

	/** @var bool */
	private $is_handling = false;

	/**
	 * @param WP_Loupe_Admin_User_Indexer $indexer User indexer instance.
	 */
	public function __construct( WP_Loupe_Admin_User_Indexer $indexer ) {
		$this->indexer = $indexer;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'pre_get_users', [ $this, 'maybe_intercept' ], 10, 1 );
	}

	/**
	 * Intercept user queries on the native user list table.
	 *
	 * We cannot short-circuit WP_User_Query the same way posts_pre_query works.
	 * Instead, we replace the search term with an explicit `include` list of
	 * user IDs from Loupe and clear the search columns.
	 *
	 * @param \WP_User_Query $query User query instance.
	 * @return void
	 */
	public function maybe_intercept( $query ): void {
		if ( $this->is_handling || ! $this->should_intercept( $query ) ) {
			return;
		}

		$search = $query->get( 'search' );
		if ( empty( $search ) ) {
			return;
		}

		// WP wraps the search term in wildcards: *term*
		$term = trim( $search, '*' );
		if ( '' === $term ) {
			return;
		}

		$this->is_handling = true;

		$hits = $this->indexer->search( $term );

		if ( empty( $hits ) ) {
			// No Loupe results — force empty result set.
			$query->set( 'search', '' );
			$query->set( 'include', [ 0 ] );
			$this->is_handling = false;
			return;
		}

		// Extract user IDs in Loupe-ranked order.
		$user_ids = array_map(
			static fn( array $hit ): int => (int) ( $hit[ 'id' ] ?? 0 ),
			$hits
		);
		$user_ids = array_filter( $user_ids );

		// Replace the search with an include list — preserves Loupe ranking.
		$query->set( 'search', '' );
		$query->set( 'include', $user_ids );
		$query->set( 'orderby', 'include' );

		$this->is_handling = false;
	}

	/**
	 * Check if we should intercept this user query.
	 *
	 * @param \WP_User_Query $query User query.
	 * @return bool
	 */
	private function should_intercept( $query ): bool {
		global $pagenow;

		if ( ! is_admin() ) {
			return false;
		}

		if ( 'users.php' !== ( $pagenow ?? '' ) ) {
			return false;
		}

		$search = $query->get( 'search' );
		return ! empty( $search );
	}
}
