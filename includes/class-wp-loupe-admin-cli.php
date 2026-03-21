<?php

namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * WP-CLI commands for WP Loupe Admin.
 *
 * ## EXAMPLES
 *
 *     wp loupe-admin reindex
 *     wp loupe-admin reindex --type=post
 *     wp loupe-admin reindex --type=user
 *     wp loupe-admin status
 */
class WP_Loupe_Admin_CLI {

	/** Entity types that are not post types. */
	private const ENTITY_TYPES = [ 'user', 'comment', 'plugin' ];

	/** @var WP_Loupe_Admin_Indexer */
	private $indexer;

	/** @var array<int,string> */
	private $post_types;

	/** @var WP_Loupe_Admin_User_Indexer|null */
	private $user_indexer;

	/** @var WP_Loupe_Admin_Comment_Indexer|null */
	private $comment_indexer;

	/** @var WP_Loupe_Admin_Plugin_Indexer|null */
	private $plugin_indexer;

	/**
	 * @param WP_Loupe_Admin_Indexer              $indexer         Admin post indexer.
	 * @param array<int,string>                   $post_types      Configured post types.
	 * @param WP_Loupe_Admin_User_Indexer|null    $user_indexer    User indexer.
	 * @param WP_Loupe_Admin_Comment_Indexer|null $comment_indexer Comment indexer.
	 * @param WP_Loupe_Admin_Plugin_Indexer|null  $plugin_indexer  Plugin indexer.
	 */
	public function __construct(
		WP_Loupe_Admin_Indexer $indexer,
		array $post_types,
		?WP_Loupe_Admin_User_Indexer $user_indexer = null,
		?WP_Loupe_Admin_Comment_Indexer $comment_indexer = null,
		?WP_Loupe_Admin_Plugin_Indexer $plugin_indexer = null
	) {
		$this->indexer         = $indexer;
		$this->post_types      = $post_types;
		$this->user_indexer    = $user_indexer;
		$this->comment_indexer = $comment_indexer;
		$this->plugin_indexer  = $plugin_indexer;
	}

	/**
	 * Rebuild admin search indexes.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Reindex only a specific type (post type slug, or user/comment/plugin).
	 *   Omit to reindex all.
	 *
	 * ## EXAMPLES
	 *
	 *     wp loupe-admin reindex
	 *     wp loupe-admin reindex --type=post
	 *     wp loupe-admin reindex --type=user
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public function reindex( array $args, array $assoc_args ): void {
		$type      = $assoc_args[ 'type' ] ?? null;
		$all_types = array_merge( $this->post_types, self::ENTITY_TYPES );

		if ( null !== $type && ! in_array( $type, $all_types, true ) ) {
			\WP_CLI::error( sprintf( 'Type "%s" is not configured. Available: %s', $type, implode( ', ', $all_types ) ) );
			return;
		}

		if ( null === $type || in_array( $type, $this->post_types, true ) ) {
			if ( null !== $type ) {
				\WP_CLI::log( sprintf( 'Reindexing admin index for "%s"...', $type ) );
			} else {
				\WP_CLI::log( sprintf( 'Reindexing post type indexes: %s', implode( ', ', $this->post_types ) ) );
			}
			$this->indexer->reindex_all();
		}

		if ( null === $type || 'user' === $type ) {
			if ( $this->user_indexer ) {
				\WP_CLI::log( 'Reindexing user index...' );
				$this->user_indexer->reindex_all();
			}
		}

		if ( null === $type || 'comment' === $type ) {
			if ( $this->comment_indexer ) {
				\WP_CLI::log( 'Reindexing comment index...' );
				$this->comment_indexer->reindex_all();
			}
		}

		if ( null === $type || 'plugin' === $type ) {
			if ( $this->plugin_indexer ) {
				\WP_CLI::log( 'Reindexing plugin index...' );
				$this->plugin_indexer->reindex_all();
			}
		}

		\WP_CLI::success( 'Admin indexes rebuilt.' );
	}

	/**
	 * Show admin index status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp loupe-admin status
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {
		$default = defined( 'WP_CONTENT_DIR' ) ? ( WP_CONTENT_DIR . '/wp-loupe-db' ) : '';
		$base    = apply_filters( 'wp_loupe_db_path', $default );
		$base    = is_string( $base ) ? trim( $base ) : '';

		if ( '' === $base ) {
			$base = $default;
		}

		$admin_base = rtrim( $base, '/' ) . '/admin';
		$rows       = [];

		// Post type indexes.
		foreach ( $this->post_types as $post_type ) {
			$rows[] = $this->get_index_status_row( $admin_base, $post_type );
		}

		// Entity type indexes.
		foreach ( self::ENTITY_TYPES as $entity_type ) {
			$rows[] = $this->get_index_status_row( $admin_base, $entity_type );
		}

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'type', 'documents', 'status' ] );
	}

	/**
	 * Get status row for a single index.
	 *
	 * @param string $admin_base Admin DB base path.
	 * @param string $type       Entity/post type name.
	 * @return array{type: string, documents: int, status: string}
	 */
	private function get_index_status_row( string $admin_base, string $type ): array {
		$sqlite_path = $admin_base . '/' . $type . '/loupe.db';

		if ( ! file_exists( $sqlite_path ) ) {
			return [
				'type'      => $type,
				'documents' => 0,
				'status'    => 'missing',
			];
		}

		try {
			$pdo   = new \PDO( 'sqlite:' . $sqlite_path );
			$stmt  = $pdo->query( 'SELECT COUNT(*) FROM documents' );
			$count = $stmt ? (int) $stmt->fetchColumn() : 0;

			return [
				'type'      => $type,
				'documents' => $count,
				'status'    => $count > 0 ? 'ok' : 'empty',
			];
		} catch (\Throwable $e) {
			return [
				'type'      => $type,
				'documents' => 0,
				'status'    => 'error',
			];
		}
	}
}
