<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

use Loupe\Loupe\Config\TypoTolerance;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\SearchParameters;

/**
 * Admin-specific Loupe indexer and search engine.
 *
 * Maintains separate indexes stored under {wp_loupe_db_path}/admin/{entity_type}/
 * that include all admin-relevant post statuses (not just publish).
 *
 * Uses its own schema defined by WP_Loupe_Admin_Schema, independent from the
 * main WP Loupe plugin's wp_loupe_fields option.
 */
class WP_Loupe_Admin_Indexer {
	/** Post statuses to include in admin indexes. */
	private const ADMIN_STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future' ];

	/** @var array<int,string> */
	private $post_types;

	/** @var WP_Loupe_Admin_Schema */
	private $schema;

	/** @var string */
	private $admin_db_base;

	/** @var array<string,\Loupe\Loupe\Loupe> */
	private $loupe = [];

	/**
	 * @param array<int,string>     $post_types Indexed post types.
	 * @param WP_Loupe_Admin_Schema $schema     Admin schema provider.
	 */
	public function __construct( array $post_types, WP_Loupe_Admin_Schema $schema ) {
		$this->post_types    = $post_types;
		$this->schema        = $schema;
		$this->admin_db_base = $this->resolve_admin_db_base();
		$this->init_loupe_instances();
	}

	/**
	 * Register indexing hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		foreach ( $this->post_types as $post_type ) {
			add_action( "save_post_{$post_type}", [ $this, 'on_save_post' ], 20, 3 );
		}
		add_action( 'wp_trash_post', [ $this, 'on_trash_post' ], 10, 2 );
		add_filter( 'wp_loupe_field_post_content', 'wp_strip_all_tags' );
	}

	/**
	 * Search admin indexes.
	 *
	 * @param string           $query      Search query.
	 * @param array<int,string> $post_types Post types to search (subset of indexed types).
	 * @return array<int,array<string,mixed>>
	 */
	public function search( string $query, array $post_types = [] ): array {
		$search_types = ! empty( $post_types )
			? array_intersect( $this->post_types, $post_types )
			: $this->post_types;

		$hits = [];

		foreach ( $search_types as $post_type ) {
			if ( ! isset( $this->loupe[ $post_type ] ) ) {
				continue;
			}

			$fields = $this->schema->get_fields( $post_type );
			if ( empty( $fields ) ) {
				continue;
			}

			$retrievable = [ 'id' ];
			foreach ( $fields as $field_name => $settings ) {
				if ( ! empty( $settings['searchable'] ) || ! empty( $settings['filterable'] ) ) {
					$retrievable[] = $field_name;
				}
			}

			try {
				$params = SearchParameters::create()
					->withQuery( $query )
					->withAttributesToRetrieve( array_unique( $retrievable ) )
					->withShowRankingScore( true )
					->withLimit( 1000 );

				$result   = $this->loupe[ $post_type ]->search( $params );
				$arr      = $result->toArray();
				$tmp_hits = isset( $arr['hits'] ) && is_array( $arr['hits'] ) ? $arr['hits'] : [];

				foreach ( $tmp_hits as $hit ) {
					if ( ! is_array( $hit ) ) {
						continue;
					}
					if ( isset( $hit['_rankingScore'] ) && ! isset( $hit['_score'] ) ) {
						$hit['_score'] = $hit['_rankingScore'];
					}
					$hit['post_type'] = $post_type;
					$hits[]           = $hit;
				}
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		usort( $hits, static function ( array $a, array $b ): int {
			return ( $b['_score'] ?? 0 ) <=> ( $a['_score'] ?? 0 );
		} );

		return $hits;
	}

	/**
	 * Index a post on save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 * @return void
	 */
	public function on_save_post( int $post_id, \WP_Post $post, bool $update ): void {
		if ( ! $this->is_admin_indexable( $post_id, $post ) ) {
			return;
		}

		$loupe = $this->loupe[ $post->post_type ] ?? null;
		if ( ! $loupe ) {
			return;
		}

		$document = $this->prepare_document( $post );
		$loupe->addDocument( $document );
	}

	/**
	 * Remove a post from admin index on trash.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $previous_status Previous post status.
	 * @return void
	 */
	public function on_trash_post( int $post_id, string $previous_status ): void {
		if ( ! in_array( $previous_status, self::ADMIN_STATUSES, true ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, $this->post_types, true ) ) {
			return;
		}

		$loupe = $this->loupe[ $post_type ] ?? null;
		if ( ! $loupe ) {
			return;
		}

		try {
			$loupe->deleteDocument( $post_id );
		} catch ( \Throwable $e ) {
			// Silently ignore — document may not exist in admin index.
		}
	}

	/**
	 * Fully rebuild admin indexes for all configured post types.
	 *
	 * @return void
	 */
	public function reindex_all(): void {
		$this->init_loupe_instances();

		foreach ( $this->post_types as $post_type ) {
			$loupe = $this->loupe[ $post_type ] ?? null;
			if ( ! $loupe ) {
				continue;
			}

			try {
				$loupe->deleteAllDocuments();
			} catch ( \Throwable $e ) {
				// If schema mismatch, delete on-disk and recreate.
				$this->delete_admin_index_for_post_type( $post_type );
				$this->init_loupe_instances();
				$loupe = $this->loupe[ $post_type ] ?? null;
				if ( ! $loupe ) {
					continue;
				}
			}

			$posts = get_posts( [
				'post_type'      => $post_type,
				'posts_per_page' => -1,
				'post_status'    => self::ADMIN_STATUSES,
			] );

			$documents = [];
			foreach ( $posts as $post ) {
				$documents[] = $this->prepare_document( $post );
			}

			if ( ! empty( $documents ) ) {
				$loupe->addDocuments( $documents );
			}
		}
	}

	/**
	 * Check whether admin indexes need an initial population.
	 *
	 * Returns true if any post type's admin index has zero documents.
	 *
	 * @return bool
	 */
	public function needs_initial_index(): bool {
		foreach ( $this->post_types as $post_type ) {
			$sqlite_path = $this->get_admin_db_path( $post_type ) . '/loupe.db';

			if ( ! file_exists( $sqlite_path ) ) {
				return true;
			}

			try {
				$pdo  = new \PDO( 'sqlite:' . $sqlite_path );
				$stmt = $pdo->query( 'SELECT COUNT(*) FROM documents' );
				if ( $stmt && 0 === (int) $stmt->fetchColumn() ) {
					return true;
				}
			} catch ( \Throwable $e ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve the admin DB base path.
	 *
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
	 * Get admin index path for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private function get_admin_db_path( string $post_type ): string {
		$path = $this->admin_db_base . '/' . ltrim( $post_type, '/' );

		if ( function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $path );
		} elseif ( ! is_dir( $path ) ) {
			@mkdir( $path, 0755, true );
		}

		return $path;
	}

	/**
	 * Initialize admin Loupe instances directly (bypassing WP_Loupe_Factory cache).
	 *
	 * @return void
	 */
	private function init_loupe_instances(): void {
		$advanced = (array) get_option( 'wp_loupe_advanced', [] );

		foreach ( $this->post_types as $post_type ) {
			$fields = $this->schema->get_fields( $post_type );
			if ( empty( $fields ) ) {
				continue;
			}

			$searchable  = [];
			$filterable  = [];
			$sortable    = [];

			foreach ( $fields as $field_name => $settings ) {
				if ( ! empty( $settings['searchable'] ) ) {
					$searchable[] = $field_name;
				}
				if ( ! empty( $settings['filterable'] ) ) {
					$filterable[] = $field_name;
				}
				if ( ! empty( $settings['sortable'] ) ) {
					$sortable[] = $field_name;
				}
			}

			$configuration = Configuration::create()
				->withPrimaryKey( 'id' )
				->withSearchableAttributes( $searchable )
				->withFilterableAttributes( $filterable )
				->withSortableAttributes( $sortable )
				->withMaxQueryTokens( $advanced['max_query_tokens'] ?? 12 )
				->withMinTokenLengthForPrefixSearch( $advanced['min_prefix_length'] ?? 3 )
				->withLanguages( $advanced['languages'] ?? [ 'en' ] )
				->withTypoTolerance( $this->build_typo_tolerance( $advanced ) );

			$factory = new LoupeFactory();

			try {
				$this->loupe[ $post_type ] = $factory->create(
					$this->get_admin_db_path( $post_type ),
					$configuration
				);
			} catch ( \Throwable $e ) {
				// Index may be corrupt — delete and retry once.
				$this->delete_admin_index_for_post_type( $post_type );

				try {
					$this->loupe[ $post_type ] = $factory->create(
						$this->get_admin_db_path( $post_type ),
						$configuration
					);
				} catch ( \Throwable $e2 ) {
					// Give up for this post type.
				}
			}
		}
	}

	/**
	 * Build TypoTolerance from advanced settings.
	 *
	 * @param array<string,mixed> $settings Advanced settings.
	 * @return TypoTolerance
	 */
	private function build_typo_tolerance( array $settings ): TypoTolerance {
		if ( empty( $settings['typo_enabled'] ) ) {
			return TypoTolerance::disabled();
		}

		$typo = TypoTolerance::create();

		if ( ! empty( $settings['alphabet_size'] ) ) {
			$typo->withAlphabetSize( $settings['alphabet_size'] );
		}
		if ( ! empty( $settings['index_length'] ) ) {
			$typo->withIndexLength( $settings['index_length'] );
		}

		$typo->withFirstCharTypoCountsDouble( ! empty( $settings['first_char_typo_double'] ) );
		$typo->withEnabledForPrefixSearch( ! empty( $settings['typo_prefix_search'] ) );

		if ( ! empty( $settings['typo_thresholds'] ) && is_array( $settings['typo_thresholds'] ) ) {
			$typo->withTypoThresholds( $settings['typo_thresholds'] );
		}

		return $typo;
	}

	/**
	 * Check if a post is indexable for admin search.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return bool
	 */
	private function is_admin_indexable( int $post_id, \WP_Post $post ): bool {
		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}
		if ( wp_is_post_autosave( $post_id ) ) {
			return false;
		}
		if ( ! in_array( $post->post_type, $this->post_types, true ) ) {
			return false;
		}
		if ( ! in_array( $post->post_status, self::ADMIN_STATUSES, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Prepare a document for the admin Loupe index.
	 *
	 * Uses the admin schema to determine which fields to include.
	 * Resolves virtual fields like author_name and taxonomy_* automatically.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string,mixed>
	 */
	private function prepare_document( \WP_Post $post ): array {
		$fields   = $this->schema->get_fields( $post->post_type );
		$document = [ 'id' => $post->ID ];

		foreach ( $fields as $field_name => $settings ) {
			if ( empty( $settings['searchable'] ) && empty( $settings['filterable'] ) && empty( $settings['sortable'] ) ) {
				continue;
			}

			$value = $this->resolve_field_value( $post, $field_name );
			$value = $this->sanitize_field_value( $value );

			if ( null !== $value ) {
				$document[ $field_name ] = $value;
			} elseif ( ! empty( $settings['sortable'] ) ) {
				$document[ $field_name ] = '';
			}
		}

		return $document;
	}

	/**
	 * Resolve a single field value from a post.
	 *
	 * Handles WP_Post properties, virtual fields (author_name), taxonomies,
	 * and falls back to post meta.
	 *
	 * @param \WP_Post $post       Post object.
	 * @param string   $field_name Field name from the schema.
	 * @return mixed Raw field value.
	 */
	private function resolve_field_value( \WP_Post $post, string $field_name ) {
		// Virtual: author display name.
		if ( 'author_name' === $field_name ) {
			$author = get_userdata( (int) $post->post_author );
			return $author && isset( $author->display_name ) ? (string) $author->display_name : '';
		}

		// Taxonomy fields (taxonomy_category, taxonomy_post_tag, etc.).
		if ( 0 === strpos( $field_name, 'taxonomy_' ) ) {
			$taxonomy = substr( $field_name, 9 );
			$terms    = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'names' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				return $terms;
			}
			return null;
		}

		// Direct WP_Post property.
		if ( property_exists( $post, $field_name ) ) {
			$value = $post->{$field_name};
			// Strip HTML from content-like fields.
			if ( in_array( $field_name, [ 'post_content', 'post_excerpt' ], true ) ) {
				$value = wp_strip_all_tags( (string) $value );
			}
			return $value;
		}

		// Post meta fallback.
		$meta = get_post_meta( $post->ID, $field_name, true );
		if ( '' !== $meta && false !== $meta ) {
			return $meta;
		}

		return null;
	}

	/**
	 * Sanitize a field value for Loupe.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed Sanitized value or null.
	 */
	private function sanitize_field_value( $value ) {
		if ( null === $value || '' === $value || [] === $value || false === $value ) {
			return null;
		}
		if ( is_numeric( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			$value = trim( $value );
			return '' !== $value ? $value : null;
		}
		if ( is_array( $value ) ) {
			if ( isset( $value['lat'] ) && ( isset( $value['lng'] ) || isset( $value['lon'] ) ) ) {
				$lat = $value['lat'];
				$lng = $value['lng'] ?? $value['lon'];
				return is_numeric( $lat ) && is_numeric( $lng )
					? [ 'lat' => (float) $lat, 'lng' => (float) $lng ]
					: null;
			}

			$sanitized = [];
			foreach ( $value as $item ) {
				if ( is_string( $item ) && '' !== trim( $item ) ) {
					$sanitized[] = trim( $item );
				}
			}
			return ! empty( $sanitized ) ? $sanitized : null;
		}

		return null;
	}

	/**
	 * Delete on-disk admin index for a single post type.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	private function delete_admin_index_for_post_type( string $post_type ): void {
		$path = $this->get_admin_db_path( $post_type );

		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$fs = new \WP_Filesystem_Direct( false );
		if ( $fs->is_dir( $path ) ) {
			$fs->rmdir( $path, true );
		}

		unset( $this->loupe[ $post_type ] );
	}
}
