<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * Server-side integration for native admin comment search (edit-comments.php).
 *
 * Intercepts WP_Comment_Query via `pre_get_comments` when on the native
 * comment list table and delegates search to the admin comment Loupe index.
 */
class WP_Loupe_Admin_Comment_Query_Integration {

	/** @var WP_Loupe_Admin_Comment_Indexer */
	private $indexer;

	/** @var bool */
	private $is_handling = false;

	/**
	 * @param WP_Loupe_Admin_Comment_Indexer $indexer Comment indexer instance.
	 */
	public function __construct( WP_Loupe_Admin_Comment_Indexer $indexer ) {
		$this->indexer = $indexer;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'pre_get_comments', [ $this, 'maybe_intercept' ], 10, 1 );
	}

	/**
	 * Intercept comment queries on the native comment list table.
	 *
	 * Similar to user integration, we replace the search with an explicit
	 * `comment__in` list of IDs from the Loupe index.
	 *
	 * @param \WP_Comment_Query $query Comment query instance.
	 * @return void
	 */
	public function maybe_intercept( $query ): void {
		if ( $this->is_handling || ! $this->should_intercept( $query ) ) {
			return;
		}

		$search = $query->query_vars[ 'search' ] ?? '';
		if ( empty( $search ) ) {
			return;
		}

		$term = trim( $search );
		if ( '' === $term ) {
			return;
		}

		$this->is_handling = true;

		$hits = $this->indexer->search( $term );

		if ( empty( $hits ) ) {
			$query->query_vars[ 'search' ]      = '';
			$query->query_vars[ 'comment__in' ] = [ 0 ];
			$this->is_handling                = false;
			return;
		}

		$comment_ids = array_map(
			static fn( array $hit ): int => (int) ( $hit[ 'id' ] ?? 0 ),
			$hits
		);
		$comment_ids = array_filter( $comment_ids );

		$query->query_vars[ 'search' ]      = '';
		$query->query_vars[ 'comment__in' ] = $comment_ids;
		$query->query_vars[ 'orderby' ]     = 'comment__in';

		$this->is_handling = false;
	}

	/**
	 * Check if we should intercept this comment query.
	 *
	 * @param \WP_Comment_Query $query Comment query.
	 * @return bool
	 */
	private function should_intercept( $query ): bool {
		global $pagenow;

		if ( ! is_admin() ) {
			return false;
		}

		if ( 'edit-comments.php' !== ( $pagenow ?? '' ) ) {
			return false;
		}

		$search = $query->query_vars[ 'search' ] ?? '';
		return ! empty( $search );
	}
}
