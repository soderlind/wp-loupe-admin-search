<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * Server-side integration for native admin post search tables.
 */
class WP_Loupe_Admin_Query_Integration {
	/** @var array<int,string> */
	private $post_types = [];

	/** @var WP_Loupe_Admin_Indexer */
	private $indexer;

	/** @var bool */
	private $is_handling_posts_query = false;

	/**
	 * @param array<int,string>      $post_types Indexed post types.
	 * @param WP_Loupe_Admin_Indexer $indexer    Admin indexer instance.
	 */
	public function __construct( array $post_types, WP_Loupe_Admin_Indexer $indexer ) {
		$this->post_types = $post_types;
		$this->indexer    = $indexer;
	}

	/**
	 * Register native admin query hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'posts_pre_query', [ $this, 'maybe_short_circuit_posts_query' ], 10, 2 );
	}

	/**
	 * Short-circuit supported admin post search queries with WP Loupe results.
	 *
	 * @param mixed     $posts Existing short-circuit value.
	 * @param \WP_Query $query Query instance.
	 * @return mixed
	 */
	public function maybe_short_circuit_posts_query( $posts, \WP_Query $query ) {
		if ( null !== $posts || ! $this->should_intercept_posts_query( $query ) ) {
			return $posts;
		}

		$query_string = trim( (string) $query->get( 's' ) );
		if ( '' === $query_string ) {
			return $posts;
		}

		$post_types = $this->get_query_post_types( $query );
		if ( empty( $post_types ) ) {
			return $posts;
		}

		$hits = array_values( array_filter(
			$this->indexer->search( $query_string, $post_types ),
			static function ( $hit ) use ( $post_types ): bool {
				return is_array( $hit )
					&& ! empty( $hit[ 'id' ] )
					&& ! empty( $hit[ 'post_type' ] )
					&& in_array( (string) $hit[ 'post_type' ], $post_types, true );
			}
		) );

		$per_page     = max( 1, (int) $query->get( 'posts_per_page' ) );
		$current_page = max( 1, (int) $query->get( 'paged' ) );
		$total        = count( $hits );
		$offset       = ( $current_page - 1 ) * $per_page;
		$page_hits    = array_slice( $hits, $offset, $per_page );

		$query->found_posts   = $total;
		$query->max_num_pages = max( 1, (int) ceil( $total / $per_page ) );

		return $this->hydrate_posts( $page_hits );
	}

	/**
	 * Determine whether the query is a supported admin search.
	 *
	 * @param \WP_Query $query Query instance.
	 * @return bool
	 */
	private function should_intercept_posts_query( \WP_Query $query ): bool {
		global $pagenow;

		if ( $this->is_handling_posts_query || ! is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return false;
		}

		if ( ! in_array( $pagenow, [ 'edit.php', 'upload.php' ], true ) ) {
			return false;
		}

		if ( 'attachment' === $query->get( 'post_type' ) ) {
			return false;
		}

		$status = $query->get( 'post_status' );
		if ( $status && 'any' !== $status && ! in_array( $status, [ 'publish', 'draft', 'pending', 'private', 'future' ], true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolve indexed post types relevant to the query.
	 *
	 * @param \WP_Query $query Query instance.
	 * @return array<int,string>
	 */
	private function get_query_post_types( \WP_Query $query ): array {
		$post_type = $query->get( 'post_type' );

		if ( empty( $post_type ) ) {
			$post_type = 'post';
		}

		$post_types = is_array( $post_type ) ? array_map( 'strval', $post_type ) : [ (string) $post_type ];

		return array_values( array_intersect( $this->post_types, $post_types ) );
	}

	/**
	 * Convert hit IDs into WP_Post objects while preserving Loupe order.
	 *
	 * @param array<int,array<string,mixed>> $hits Current-page hits.
	 * @return array<int,\WP_Post>
	 */
	private function hydrate_posts( array $hits ): array {
		$posts                         = [];
		$this->is_handling_posts_query = true;

		foreach ( $hits as $hit ) {
			$post = get_post( (int) $hit[ 'id' ] );
			if ( $post instanceof \WP_Post ) {
				$posts[] = $post;
			}
		}

		$this->is_handling_posts_query = false;

		return $posts;
	}

}