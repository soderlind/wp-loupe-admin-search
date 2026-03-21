<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * REST controller for WP Loupe Admin.
 */
class WP_Loupe_Admin_REST {
	/** @var array<int,string> */
	private $post_types = [];

	/** @var WP_Loupe_Admin_Indexer|null */
	private $indexer;

	/** @var WP_Loupe_Admin_User_Indexer|null */
	private $user_indexer;

	/** @var WP_Loupe_Admin_Comment_Indexer|null */
	private $comment_indexer;

	/** @var WP_Loupe_Admin_Plugin_Indexer|null */
	private $plugin_indexer;

	/** @var bool */
	private $routes_registered = false;

	/**
	 * @param array<int,string>                  $post_types      Indexed post types.
	 * @param WP_Loupe_Admin_Indexer|null         $indexer         Admin indexer for content searches.
	 * @param WP_Loupe_Admin_User_Indexer|null    $user_indexer    User indexer.
	 * @param WP_Loupe_Admin_Comment_Indexer|null $comment_indexer Comment indexer.
	 * @param WP_Loupe_Admin_Plugin_Indexer|null  $plugin_indexer  Plugin indexer.
	 */
	public function __construct(
		array $post_types,
		?WP_Loupe_Admin_Indexer $indexer = null,
		?WP_Loupe_Admin_User_Indexer $user_indexer = null,
		?WP_Loupe_Admin_Comment_Indexer $comment_indexer = null,
		?WP_Loupe_Admin_Plugin_Indexer $plugin_indexer = null
	) {
		$this->post_types      = $post_types;
		$this->indexer         = $indexer;
		$this->user_indexer    = $user_indexer;
		$this->comment_indexer = $comment_indexer;
		$this->plugin_indexer  = $plugin_indexer;
	}

	/**
	 * Register REST hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		if ( did_action( 'rest_api_init' ) ) {
			$this->register_routes();
		}
	}

	/**
	 * Register the admin search route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		if ( $this->routes_registered ) {
			return;
		}

		register_rest_route( 'wp-loupe-admin/v1', '/search', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_search' ],
			'permission_callback' => [ $this, 'can_access_search' ],
			'args'                => [
				'q'        => [
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'per_page' => [
					'default'           => 10,
					'sanitize_callback' => 'absint',
				],
				'page'     => [
					'default'           => 1,
					'sanitize_callback' => 'absint',
				],
				'scope'    => [
					'default'           => 'content',
					'sanitize_callback' => 'sanitize_key',
				],
			],
		] );

		$this->routes_registered = true;
	}

	/**
	 * Permission callback.
	 *
	 * @param mixed $request Request object.
	 * @return bool
	 */
	public function can_access_search( $request = null ): bool {
		$scope = 'content';

		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$scope = sanitize_key( (string) $request->get_param( 'scope' ) );
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( 'plugins' === $scope ) {
			return current_user_can( 'activate_plugins' );
		}

		if ( 'users' === $scope ) {
			return current_user_can( 'list_users' ) || current_user_can( 'edit_users' ) || current_user_can( 'manage_options' );
		}

		if ( 'comments' === $scope ) {
			return current_user_can( 'moderate_comments' ) || current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
		}

		if ( current_user_can( 'manage_options' ) ) {
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
	 * Handle admin search request.
	 *
	 * @param mixed $request REST request.
	 * @return array|\WP_Error
	 */
	public function handle_search( $request ) {
		$query = trim( (string) $request->get_param( 'q' ) );
		$scope = sanitize_key( (string) $request->get_param( 'scope' ) );
		if ( '' === $query ) {
			return new \WP_Error( 'wp_loupe_admin_search_missing_query', __( 'Missing or empty query parameter "q".', 'wp-loupe-admin' ), [ 'status' => 400 ] );
		}

		$per_page = max( 1, min( 20, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		if ( 'plugins' === $scope ) {
			return $this->handle_plugin_search( $query, $per_page, $page );
		}

		if ( 'users' === $scope ) {
			return $this->handle_user_search( $query, $per_page, $page );
		}

		if ( 'comments' === $scope ) {
			return $this->handle_comment_search( $query, $per_page, $page );
		}

		$hits    = $this->search_content( $query );
		$results = [];

		foreach ( (array) $hits as $hit ) {
			if ( empty( $hit[ 'id' ] ) || empty( $hit[ 'post_type' ] ) ) {
				continue;
			}

			$post_id   = (int) $hit[ 'id' ];
			$post_type = (string) $hit[ 'post_type' ];
			$post      = get_post( $post_id );
			if ( ! $post || $post->post_type !== $post_type ) {
				continue;
			}

			$edit_url = get_edit_post_link( $post_id, 'raw' );
			if ( empty( $edit_url ) ) {
				continue;
			}

			$post_type_object = get_post_type_object( $post_type );
			$status_object    = get_post_status_object( $post->post_status );
			$author           = get_userdata( (int) $post->post_author );

			$results[] = [
				'id'            => $post_id,
				'title'         => get_the_title( $post_id ) ?: __( '(no title)', 'wp-loupe-admin' ),
				'postType'      => $post_type,
				'postTypeLabel' => $post_type_object && isset( $post_type_object->labels->singular_name ) ? $post_type_object->labels->singular_name : $post_type,
				'status'        => $post->post_status,
				'statusLabel'   => $status_object && isset( $status_object->label ) ? $status_object->label : $post->post_status,
				'editUrl'       => $edit_url,
				'viewUrl'       => get_permalink( $post_id ),
				'excerpt'       => $this->get_result_excerpt( $post_id ),
				'authorName'    => $author && isset( $author->display_name ) ? (string) $author->display_name : '',
				'dateLabel'     => get_the_date( get_option( 'date_format' ), $post_id ),
				'_score'        => isset( $hit[ '_score' ] ) ? (float) $hit[ '_score' ] : 0.0,
			];
		}

		$total       = count( $results );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = min( $page, $total_pages );
		$offset      = ( $page - 1 ) * $per_page;
		$results     = array_slice( $results, $offset, $per_page );

		return $this->build_response( $results, $query, $per_page, $page, $total_pages, $total, 'content' );
	}

	/**
	 * Search installed plugins.
	 *
	 * @param string $query Search query.
	 * @param int    $per_page Results per page.
	 * @param int    $page Current page.
	 * @return \WP_REST_Response
	 */
	private function handle_plugin_search( string $query, int $per_page, int $page ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		// Use Loupe plugin indexer when available.
		if ( $this->plugin_indexer ) {
			$hits    = $this->plugin_indexer->search( $query );
			$results = [];

			foreach ( $hits as $hit ) {
				$plugin_file = $hit[ 'plugin_file' ] ?? '';
				if ( '' === $plugin_file || ! isset( $all_plugins[ $plugin_file ] ) ) {
					continue;
				}

				$plugin_data = $all_plugins[ $plugin_file ];
				$is_active   = is_plugin_active( $plugin_file );

				$results[] = [
					'id'            => $plugin_file,
					'title'         => $plugin_data[ 'Name' ] ?? $plugin_file,
					'postType'      => 'plugin',
					'postTypeLabel' => __( 'Plugin', 'wp-loupe-admin' ),
					'status'        => $is_active ? 'active' : 'inactive',
					'statusLabel'   => sprintf(
						/* translators: 1: plugin activation state, 2: version number */
						__( '%1$s, v%2$s', 'wp-loupe-admin' ),
						$is_active ? __( 'Active', 'wp-loupe-admin' ) : __( 'Inactive', 'wp-loupe-admin' ),
						$plugin_data[ 'Version' ] ?? '0'
					),
					'editUrl'       => admin_url( 'plugins.php' ),
					'viewUrl'       => ! empty( $plugin_data[ 'PluginURI' ] ) ? $plugin_data[ 'PluginURI' ] : '',
					'_score'        => isset( $hit[ '_score' ] ) ? (float) $hit[ '_score' ] : 0.0,
				];
			}

			return $this->build_paginated_array_response( $results, $query, $per_page, $page, 'plugins' );
		}

		// Fallback: substring match.
		$needle  = strtolower( $query );
		$results = [];

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$haystack = strtolower( implode( ' ', array_filter( [
				$plugin_data[ 'Name' ] ?? '',
				$plugin_data[ 'Description' ] ?? '',
				$plugin_data[ 'Author' ] ?? '',
				$plugin_data[ 'TextDomain' ] ?? '',
				$plugin_file,
			] ) ) );

			if ( false === strpos( $haystack, $needle ) ) {
				continue;
			}

			$is_active = is_plugin_active( $plugin_file );

			$results[] = [
				'id'            => $plugin_file,
				'title'         => $plugin_data[ 'Name' ] ?? $plugin_file,
				'postType'      => 'plugin',
				'postTypeLabel' => __( 'Plugin', 'wp-loupe-admin' ),
				'status'        => $is_active ? 'active' : 'inactive',
				'statusLabel'   => sprintf(
					/* translators: 1: plugin activation state, 2: version number */
					__( '%1$s, v%2$s', 'wp-loupe-admin' ),
					$is_active ? __( 'Active', 'wp-loupe-admin' ) : __( 'Inactive', 'wp-loupe-admin' ),
					$plugin_data[ 'Version' ] ?? '0'
				),
				'editUrl'       => admin_url( 'plugins.php' ),
				'viewUrl'       => ! empty( $plugin_data[ 'PluginURI' ] ) ? $plugin_data[ 'PluginURI' ] : '',
				'_score'        => 0.0,
			];
		}

		return $this->build_paginated_array_response( $results, $query, $per_page, $page, 'plugins' );
	}

	/**
	 * Search WordPress users.
	 *
	 * @param string $query Search query.
	 * @param int    $per_page Results per page.
	 * @param int    $page Current page.
	 * @return \WP_REST_Response
	 */
	private function handle_user_search( string $query, int $per_page, int $page ) {
		// Use Loupe user indexer when available.
		if ( $this->user_indexer ) {
			$hits    = $this->user_indexer->search( $query );
			$results = [];

			foreach ( $hits as $hit ) {
				$user_id = (int) ( $hit[ 'id' ] ?? 0 );
				$user    = get_userdata( $user_id );
				if ( ! $user ) {
					continue;
				}

				$results[] = [
					'id'            => $user_id,
					'title'         => $user->display_name ?: $user->user_login,
					'postType'      => 'user',
					'postTypeLabel' => __( 'User', 'wp-loupe-admin' ),
					'status'        => implode( ', ', array_map( 'strval', (array) $user->roles ) ),
					'statusLabel'   => $user->user_email,
					'editUrl'       => get_edit_user_link( $user_id ),
					'viewUrl'       => '',
					'_score'        => isset( $hit[ '_score' ] ) ? (float) $hit[ '_score' ] : 0.0,
				];
			}

			return $this->build_paginated_array_response( $results, $query, $per_page, $page, 'users' );
		}

		// Fallback: WP_User_Query.
		$user_query = new \WP_User_Query( [
			'search'         => '*' . $query . '*',
			'search_columns' => [ 'user_login', 'user_nicename', 'display_name', 'user_email' ],
			'number'         => $per_page,
			'offset'         => ( $page - 1 ) * $per_page,
			'orderby'        => 'display_name',
			'order'          => 'ASC',
		] );

		$results = [];

		foreach ( (array) $user_query->get_results() as $user ) {
			$results[] = [
				'id'            => (int) $user->ID,
				'title'         => $user->display_name ?: $user->user_login,
				'postType'      => 'user',
				'postTypeLabel' => __( 'User', 'wp-loupe-admin' ),
				'status'        => implode( ', ', array_map( 'strval', (array) $user->roles ) ),
				'statusLabel'   => $user->user_email,
				'editUrl'       => get_edit_user_link( (int) $user->ID ),
				'viewUrl'       => '',
				'_score'        => 0.0,
			];
		}

		$total       = (int) $user_query->get_total();
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = min( $page, $total_pages );

		return $this->build_response( $results, $query, $per_page, $page, $total_pages, $total, 'users' );
	}

	/**
	 * Search comments using Loupe index with fallback to WP_Comment_Query.
	 *
	 * @param string $query    Search query.
	 * @param int    $per_page Results per page.
	 * @param int    $page     Current page.
	 * @return \WP_REST_Response
	 */
	private function handle_comment_search( string $query, int $per_page, int $page ) {
		// Use Loupe comment indexer when available.
		if ( $this->comment_indexer ) {
			$hits    = $this->comment_indexer->search( $query );
			$results = [];

			foreach ( $hits as $hit ) {
				$comment_id = (int) ( $hit[ 'id' ] ?? 0 );
				$comment    = get_comment( $comment_id );
				if ( ! $comment ) {
					continue;
				}

				$results[] = [
					'id'            => $comment_id,
					'title'         => wp_trim_words( wp_strip_all_tags( (string) $comment->comment_content ), 12, '...' ),
					'postType'      => 'comment',
					'postTypeLabel' => __( 'Comment', 'wp-loupe-admin' ),
					'status'        => $comment->comment_approved,
					'statusLabel'   => $this->get_comment_status_label( $comment->comment_approved ),
					'editUrl'       => admin_url( 'comment.php?action=editcomment&c=' . $comment_id ),
					'viewUrl'       => get_comment_link( $comment_id ),
					'authorName'    => (string) $comment->comment_author,
					'_score'        => isset( $hit[ '_score' ] ) ? (float) $hit[ '_score' ] : 0.0,
				];
			}

			return $this->build_paginated_array_response( $results, $query, $per_page, $page, 'comments' );
		}

		// Fallback: WP_Comment_Query.
		$comment_query = new \WP_Comment_Query( [
			'search'  => $query,
			'number'  => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'status'  => 'all',
		] );

		$results = [];

		foreach ( (array) $comment_query->get_comments() as $comment ) {
			$results[] = [
				'id'            => (int) $comment->comment_ID,
				'title'         => wp_trim_words( wp_strip_all_tags( (string) $comment->comment_content ), 12, '...' ),
				'postType'      => 'comment',
				'postTypeLabel' => __( 'Comment', 'wp-loupe-admin' ),
				'status'        => $comment->comment_approved,
				'statusLabel'   => $this->get_comment_status_label( $comment->comment_approved ),
				'editUrl'       => admin_url( 'comment.php?action=editcomment&c=' . (int) $comment->comment_ID ),
				'viewUrl'       => get_comment_link( (int) $comment->comment_ID ),
				'authorName'    => (string) $comment->comment_author,
				'_score'        => 0.0,
			];
		}

		$total       = (int) ( $comment_query->found_comments ?? count( $results ) );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = min( $page, $total_pages );

		return $this->build_response( $results, $query, $per_page, $page, $total_pages, $total, 'comments' );
	}

	/**
	 * Get a human-readable comment status label.
	 *
	 * @param string $status Comment approved value.
	 * @return string
	 */
	private function get_comment_status_label( string $status ): string {
		$map = [
			'1'        => __( 'Approved', 'wp-loupe-admin' ),
			'0'        => __( 'Pending', 'wp-loupe-admin' ),
			'spam'     => __( 'Spam', 'wp-loupe-admin' ),
			'trash'    => __( 'Trash', 'wp-loupe-admin' ),
			'approved' => __( 'Approved', 'wp-loupe-admin' ),
			'hold'     => __( 'Pending', 'wp-loupe-admin' ),
		];

		return $map[ $status ] ?? $status;
	}

	/**
	 * Paginate an in-memory result set.
	 *
	 * @param array<int,array<string,mixed>> $results Results.
	 * @param string                         $query Search query.
	 * @param int                            $per_page Results per page.
	 * @param int                            $page Current page.
	 * @param string                         $scope Search scope.
	 * @return \WP_REST_Response
	 */
	private function build_paginated_array_response( array $results, string $query, int $per_page, int $page, string $scope ) {
		$total       = count( $results );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = min( $page, $total_pages );
		$offset      = ( $page - 1 ) * $per_page;
		$results     = array_slice( $results, $offset, $per_page );

		return $this->build_response( $results, $query, $per_page, $page, $total_pages, $total, $scope );
	}

	/**
	 * Build a consistent REST response payload.
	 *
	 * @param array<int,array<string,mixed>> $results Results.
	 * @param string                         $query Search query.
	 * @param int                            $per_page Results per page.
	 * @param int                            $page Current page.
	 * @param int                            $total_pages Total pages.
	 * @param int                            $total Total results.
	 * @param string                         $scope Search scope.
	 * @return \WP_REST_Response
	 */
	private function build_response( array $results, string $query, int $per_page, int $page, int $total_pages, int $total, string $scope ) {
		return rest_ensure_response( [
			'hits'       => $results,
			'total'      => $total,
			'query'      => $query,
			'page'       => $page,
			'perPage'    => $per_page,
			'totalPages' => $total_pages,
			'scope'      => $scope,
		] );
	}

	/**
	 * Build a plain-text excerpt suitable for admin search results.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_result_excerpt( int $post_id ): string {
		$excerpt = trim( wp_strip_all_tags( (string) get_the_excerpt( $post_id ) ) );

		if ( '' === $excerpt ) {
			return '';
		}

		return wp_trim_words( $excerpt, 24, '...' );
	}

	/**
	 * Search content using the admin indexer (preferred) or the main search engine.
	 *
	 * @param string $query Search query.
	 * @return array<int,array<string,mixed>>
	 */
	private function search_content( string $query ): array {
		if ( $this->indexer ) {
			return $this->indexer->search( $query );
		}

		$class_name = '\\Soderlind\\Plugin\\WPLoupe\\WP_Loupe_Search_Engine';
		if ( ! class_exists( $class_name ) ) {
			return [];
		}

		$engine = new $class_name( $this->post_types );
		return (array) $engine->search( $query );
	}
}