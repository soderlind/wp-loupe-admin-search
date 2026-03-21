<?php

namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * WP-CLI commands for WP Loupe Admin.
 *
 * ## EXAMPLES
 *
 *     wp loupe-admin reindex
 *     wp loupe-admin reindex --type=post
 *     wp loupe-admin status
 */
class WP_Loupe_Admin_CLI {

	/** @var WP_Loupe_Admin_Indexer */
	private $indexer;

	/** @var array<int,string> */
	private $post_types;

	/**
	 * @param WP_Loupe_Admin_Indexer $indexer    Admin indexer instance.
	 * @param array<int,string>      $post_types Configured post types.
	 */
	public function __construct( WP_Loupe_Admin_Indexer $indexer, array $post_types ) {
		$this->indexer    = $indexer;
		$this->post_types = $post_types;
	}

	/**
	 * Rebuild admin search indexes.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<post_type>]
	 * : Reindex only a specific post type. Omit to reindex all.
	 *
	 * ## EXAMPLES
	 *
	 *     wp loupe-admin reindex
	 *     wp loupe-admin reindex --type=post
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public function reindex( array $args, array $assoc_args ): void {
		$type = $assoc_args['type'] ?? null;

		if ( null !== $type && ! in_array( $type, $this->post_types, true ) ) {
			\WP_CLI::error( sprintf( 'Post type "%s" is not configured. Available: %s', $type, implode( ', ', $this->post_types ) ) );
			return;
		}

		if ( null !== $type ) {
			\WP_CLI::log( sprintf( 'Reindexing admin index for "%s"...', $type ) );
		} else {
			\WP_CLI::log( sprintf( 'Reindexing admin indexes for: %s', implode( ', ', $this->post_types ) ) );
		}

		$this->indexer->reindex_all();

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

		foreach ( $this->post_types as $post_type ) {
			$sqlite_path = $admin_base . '/' . $post_type . '/loupe.db';

			if ( ! file_exists( $sqlite_path ) ) {
				$rows[] = [
					'post_type' => $post_type,
					'documents' => 0,
					'status'    => 'missing',
				];
				continue;
			}

			try {
				$pdo   = new \PDO( 'sqlite:' . $sqlite_path );
				$stmt  = $pdo->query( 'SELECT COUNT(*) FROM documents' );
				$count = $stmt ? (int) $stmt->fetchColumn() : 0;

				$rows[] = [
					'post_type' => $post_type,
					'documents' => $count,
					'status'    => $count > 0 ? 'ok' : 'empty',
				];
			} catch ( \Throwable $e ) {
				$rows[] = [
					'post_type' => $post_type,
					'documents' => 0,
					'status'    => 'error',
				];
			}
		}

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'post_type', 'documents', 'status' ] );
	}
}
