<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

use Loupe\Loupe\Config\TypoTolerance;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\SearchParameters;

/**
 * Admin-specific Loupe indexer for WordPress comments.
 *
 * Maintains a separate index at {wp_loupe_db_path}/admin/comment/
 * using the admin schema's comment entity definition.
 */
class WP_Loupe_Admin_Comment_Indexer {

	/** Comment statuses to include in admin indexes. */
	private const ADMIN_STATUSES = [ 'approved', 'hold', 'spam' ];

	/** @var WP_Loupe_Admin_Schema */
	private $schema;

	/** @var string */
	private $admin_db_base;

	/** @var \Loupe\Loupe\Loupe|null */
	private $loupe;

	/**
	 * @param WP_Loupe_Admin_Schema $schema Admin schema provider.
	 */
	public function __construct( WP_Loupe_Admin_Schema $schema ) {
		$this->schema        = $schema;
		$this->admin_db_base = $this->resolve_admin_db_base();
		$this->init_loupe();
	}

	/**
	 * Register indexing hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_insert_comment', [ $this, 'on_insert_comment' ], 20, 2 );
		add_action( 'edit_comment', [ $this, 'on_edit_comment' ], 20, 2 );
		add_action( 'delete_comment', [ $this, 'on_delete_comment' ], 10, 1 );
		add_action( 'transition_comment_status', [ $this, 'on_transition_status' ], 20, 3 );
	}

	/**
	 * Search the comment index.
	 *
	 * @param string $query Search query.
	 * @return array<int,array<string,mixed>>
	 */
	public function search( string $query ): array {
		if ( ! $this->loupe ) {
			return [];
		}

		$fields = $this->schema->get_fields( 'comment' );
		if ( empty( $fields ) ) {
			return [];
		}

		$retrievable = [ 'id' ];
		foreach ( $fields as $field_name => $settings ) {
			if ( ! empty( $settings[ 'searchable' ] ) || ! empty( $settings[ 'filterable' ] ) ) {
				$retrievable[] = $field_name;
			}
		}

		try {
			$params = SearchParameters::create()
				->withQuery( $query )
				->withAttributesToRetrieve( array_unique( $retrievable ) )
				->withShowRankingScore( true )
				->withLimit( 1000 );

			$result = $this->loupe->search( $params );
			$arr    = $result->toArray();
			$hits   = isset( $arr[ 'hits' ] ) && is_array( $arr[ 'hits' ] ) ? $arr[ 'hits' ] : [];

			foreach ( $hits as &$hit ) {
				if ( isset( $hit[ '_rankingScore' ] ) && ! isset( $hit[ '_score' ] ) ) {
					$hit[ '_score' ] = $hit[ '_rankingScore' ];
				}
			}
			unset( $hit );

			usort( $hits, static function ( array $a, array $b ): int {
				return ( $b[ '_score' ] ?? 0 ) <=> ( $a[ '_score' ] ?? 0 );
			} );

			return $hits;
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * Index a comment on insert.
	 *
	 * @param int         $comment_id Comment ID.
	 * @param \WP_Comment $comment    Comment object.
	 * @return void
	 */
	public function on_insert_comment( int $comment_id, $comment ): void {
		if ( ! $this->loupe || ! $this->is_indexable_status( $comment->comment_approved ) ) {
			return;
		}
		$this->loupe->addDocument( $this->prepare_document( $comment ) );
	}

	/**
	 * Index a comment on edit.
	 *
	 * @param int         $comment_id Comment ID.
	 * @param array       $data       Comment data.
	 * @return void
	 */
	public function on_edit_comment( int $comment_id, $data = [] ): void {
		$comment = get_comment( $comment_id );
		if ( ! $this->loupe || ! $comment ) {
			return;
		}

		if ( $this->is_indexable_status( $comment->comment_approved ) ) {
			$this->loupe->addDocument( $this->prepare_document( $comment ) );
		} else {
			try {
				$this->loupe->deleteDocument( $comment_id );
			} catch (\Throwable $e) {
				// Ignore.
			}
		}
	}

	/**
	 * Remove a comment from the index on delete.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function on_delete_comment( int $comment_id ): void {
		if ( ! $this->loupe ) {
			return;
		}

		try {
			$this->loupe->deleteDocument( $comment_id );
		} catch (\Throwable $e) {
			// Silently ignore.
		}
	}

	/**
	 * Handle comment status transitions.
	 *
	 * @param string      $new_status New status.
	 * @param string      $old_status Old status.
	 * @param \WP_Comment $comment    Comment object.
	 * @return void
	 */
	public function on_transition_status( string $new_status, string $old_status, $comment ): void {
		if ( ! $this->loupe ) {
			return;
		}

		if ( $this->is_indexable_status( $new_status ) ) {
			$this->loupe->addDocument( $this->prepare_document( $comment ) );
		} else {
			try {
				$this->loupe->deleteDocument( (int) $comment->comment_ID );
			} catch (\Throwable $e) {
				// Ignore.
			}
		}
	}

	/**
	 * Fully rebuild the comment index.
	 *
	 * @return void
	 */
	public function reindex_all(): void {
		$this->init_loupe();

		if ( ! $this->loupe ) {
			return;
		}

		try {
			$this->loupe->deleteAllDocuments();
		} catch (\Throwable $e) {
			$this->delete_index();
			$this->init_loupe();
			if ( ! $this->loupe ) {
				return;
			}
		}

		$comments = get_comments( [
			'number' => 0,
			'status' => 'all',
		] );

		$documents = [];
		foreach ( $comments as $comment ) {
			if ( $this->is_indexable_status( $comment->comment_approved ) ) {
				$documents[] = $this->prepare_document( $comment );
			}
		}

		if ( ! empty( $documents ) ) {
			$this->loupe->addDocuments( $documents );
		}
	}

	/**
	 * Check if the comment index needs initial population.
	 *
	 * @return bool
	 */
	public function needs_initial_index(): bool {
		$sqlite_path = $this->get_db_path() . '/loupe.db';

		if ( ! file_exists( $sqlite_path ) ) {
			return true;
		}

		try {
			$pdo  = new \PDO( 'sqlite:' . $sqlite_path );
			$stmt = $pdo->query( 'SELECT COUNT(*) FROM documents' );
			if ( $stmt && 0 === (int) $stmt->fetchColumn() ) {
				return true;
			}
		} catch (\Throwable $e) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a comment status is indexable.
	 *
	 * WordPress uses '1' for approved, '0' for hold, 'spam', 'trash'.
	 *
	 * @param string $status Comment_approved value or status string.
	 * @return bool
	 */
	private function is_indexable_status( string $status ): bool {
		// WordPress stores approved as '1', hold as '0'.
		return in_array( $status, [ '1', '0', 'approved', 'hold', 'spam' ], true );
	}

	/**
	 * Prepare a document for the Loupe index.
	 *
	 * @param \WP_Comment $comment Comment object.
	 * @return array<string,mixed>
	 */
	private function prepare_document( $comment ): array {
		$document = [ 'id' => (int) $comment->comment_ID ];

		$fields = $this->schema->get_fields( 'comment' );
		foreach ( $fields as $field_name => $settings ) {
			if ( empty( $settings[ 'searchable' ] ) && empty( $settings[ 'filterable' ] ) && empty( $settings[ 'sortable' ] ) ) {
				continue;
			}

			$value = $this->resolve_field_value( $comment, $field_name );
			$value = $this->sanitize_value( $value );

			if ( null !== $value ) {
				$document[ $field_name ] = $value;
			} elseif ( ! empty( $settings[ 'sortable' ] ) ) {
				$document[ $field_name ] = '';
			}
		}

		return $document;
	}

	/**
	 * Resolve a field value from a comment.
	 *
	 * @param \WP_Comment $comment    Comment object.
	 * @param string      $field_name Field name.
	 * @return mixed
	 */
	private function resolve_field_value( $comment, string $field_name ) {
		if ( 'comment_content' === $field_name ) {
			return wp_strip_all_tags( (string) $comment->comment_content );
		}

		if ( isset( $comment->$field_name ) ) {
			return (string) $comment->$field_name;
		}

		return null;
	}

	/**
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private function sanitize_value( $value ) {
		if ( null === $value || '' === $value || false === $value ) {
			return null;
		}
		if ( is_string( $value ) ) {
			$value = trim( $value );
			return '' !== $value ? $value : null;
		}
		return $value;
	}

	/**
	 * @return string
	 */
	private function resolve_admin_db_base(): string {
		$default = defined( 'WP_CONTENT_DIR' ) ? ( WP_CONTENT_DIR . '/wp-loupe-db' ) : '';
		$base    = apply_filters( 'wp_loupe_db_path', $default );
		$base    = is_string( $base ) ? trim( $base ) : '';

		if ( '' === $base ) {
			$base = $default;
		}

		return rtrim( $base, '/' ) . '/admin';
	}

	/**
	 * @return string
	 */
	private function get_db_path(): string {
		$path = $this->admin_db_base . '/comment';

		if ( function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $path );
		} elseif ( ! is_dir( $path ) ) {
			@mkdir( $path, 0755, true );
		}

		return $path;
	}

	/**
	 * @return void
	 */
	private function init_loupe(): void {
		$fields = $this->schema->get_fields( 'comment' );
		if ( empty( $fields ) ) {
			return;
		}

		$searchable = [];
		$filterable = [];
		$sortable   = [];

		foreach ( $fields as $field_name => $settings ) {
			if ( ! empty( $settings[ 'searchable' ] ) ) {
				$searchable[] = $field_name;
			}
			if ( ! empty( $settings[ 'filterable' ] ) ) {
				$filterable[] = $field_name;
			}
			if ( ! empty( $settings[ 'sortable' ] ) ) {
				$sortable[] = $field_name;
			}
		}

		$advanced = (array) get_option( 'wp_loupe_advanced', [] );

		$configuration = Configuration::create()
			->withPrimaryKey( 'id' )
			->withSearchableAttributes( $searchable )
			->withFilterableAttributes( $filterable )
			->withSortableAttributes( $sortable )
			->withMaxQueryTokens( $advanced[ 'max_query_tokens' ] ?? 12 )
			->withMinTokenLengthForPrefixSearch( $advanced[ 'min_prefix_length' ] ?? 3 )
			->withLanguages( $advanced[ 'languages' ] ?? [ 'en' ] )
			->withTypoTolerance( $this->build_typo_tolerance( $advanced ) );

		$factory = new LoupeFactory();

		try {
			$this->loupe = $factory->create( $this->get_db_path(), $configuration );
		} catch (\Throwable $e) {
			$this->delete_index();
			try {
				$this->loupe = $factory->create( $this->get_db_path(), $configuration );
			} catch (\Throwable $e2) {
				$this->loupe = null;
			}
		}
	}

	/**
	 * @param array<string,mixed> $settings Advanced settings.
	 * @return TypoTolerance
	 */
	private function build_typo_tolerance( array $settings ): TypoTolerance {
		if ( empty( $settings[ 'typo_enabled' ] ) ) {
			return TypoTolerance::disabled();
		}

		$typo = TypoTolerance::create();

		if ( ! empty( $settings[ 'alphabet_size' ] ) ) {
			$typo->withAlphabetSize( $settings[ 'alphabet_size' ] );
		}
		if ( ! empty( $settings[ 'index_length' ] ) ) {
			$typo->withIndexLength( $settings[ 'index_length' ] );
		}

		$typo->withFirstCharTypoCountsDouble( ! empty( $settings[ 'first_char_typo_double' ] ) );
		$typo->withEnabledForPrefixSearch( ! empty( $settings[ 'typo_prefix_search' ] ) );

		if ( ! empty( $settings[ 'typo_thresholds' ] ) && is_array( $settings[ 'typo_thresholds' ] ) ) {
			$typo->withTypoThresholds( $settings[ 'typo_thresholds' ] );
		}

		return $typo;
	}

	/**
	 * @return void
	 */
	private function delete_index(): void {
		$path = $this->get_db_path();

		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$fs = new \WP_Filesystem_Direct( false );
		if ( $fs->is_dir( $path ) ) {
			$fs->rmdir( $path, true );
		}

		$this->loupe = null;
	}
}
