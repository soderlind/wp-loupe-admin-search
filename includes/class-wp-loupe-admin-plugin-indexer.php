<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

use Loupe\Loupe\Config\TypoTolerance;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\SearchParameters;

/**
 * Admin-specific Loupe indexer for installed WordPress plugins.
 *
 * Maintains a separate index at {wp_loupe_db_path}/admin/plugin/
 * using the admin schema's plugin entity definition.
 *
 * Plugins are a non-DB source — the index is always rebuilt from
 * `get_plugins()` rather than incremental hooks.
 */
class WP_Loupe_Admin_Plugin_Indexer {

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
	 * Register hooks for incremental plugin index updates.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'activated_plugin', [ $this, 'on_plugin_status_change' ], 20, 1 );
		add_action( 'deactivated_plugin', [ $this, 'on_plugin_status_change' ], 20, 1 );
		add_action( 'upgrader_process_complete', [ $this, 'on_upgrader_complete' ], 20, 2 );
		add_action( 'deleted_plugin', [ $this, 'on_plugin_deleted' ], 20, 2 );
	}

	/**
	 * Search the plugin index.
	 *
	 * @param string $query Search query.
	 * @return array<int,array<string,mixed>>
	 */
	public function search( string $query ): array {
		if ( ! $this->loupe ) {
			return [];
		}

		$fields = $this->schema->get_fields( 'plugin' );
		if ( empty( $fields ) ) {
			return [];
		}

		$retrievable = [ 'id', 'plugin_file' ];
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
	 * Re-index after plugin activation/deactivation.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return void
	 */
	public function on_plugin_status_change( string $plugin_file ): void {
		$this->reindex_all();
	}

	/**
	 * Re-index after plugin install/update.
	 *
	 * @param object $upgrader Upgrader instance.
	 * @param array  $options  Upgrade options.
	 * @return void
	 */
	public function on_upgrader_complete( $upgrader, array $options ): void {
		if ( isset( $options[ 'type' ] ) && 'plugin' === $options[ 'type' ] ) {
			$this->reindex_all();
		}
	}

	/**
	 * Re-index after plugin deletion.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param bool   $deleted     Whether the plugin was deleted.
	 * @return void
	 */
	public function on_plugin_deleted( string $plugin_file, bool $deleted ): void {
		if ( $deleted ) {
			$this->reindex_all();
		}
	}

	/**
	 * Fully rebuild the plugin index from get_plugins().
	 *
	 * @return void
	 */
	public function reindex_all(): void {
		$this->init_loupe();

		if ( ! $this->loupe ) {
			return;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
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

		$documents = [];
		$counter   = 1;

		foreach ( get_plugins() as $plugin_file => $plugin_data ) {
			$documents[] = $this->prepare_document( $plugin_file, $plugin_data, $counter );
			++$counter;
		}

		if ( ! empty( $documents ) ) {
			$this->loupe->addDocuments( $documents );
		}
	}

	/**
	 * Check if the plugin index needs initial population.
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
	 * Prepare a document for a plugin.
	 *
	 * Loupe requires an integer primary key, so we use a sequential counter.
	 * The real plugin file path is stored in `plugin_file`.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param array  $plugin_data Plugin header data.
	 * @param int    $id          Sequential document ID.
	 * @return array<string,mixed>
	 */
	private function prepare_document( string $plugin_file, array $plugin_data, int $id ): array {
		$is_active = function_exists( 'is_plugin_active' ) ? is_plugin_active( $plugin_file ) : false;

		$document = [
			'id'          => $id,
			'plugin_file' => $plugin_file,
		];

		$field_map = [
			'plugin_name'        => $plugin_data[ 'Name' ] ?? $plugin_file,
			'plugin_description' => wp_strip_all_tags( (string) ( $plugin_data[ 'Description' ] ?? '' ) ),
			'plugin_author'      => wp_strip_all_tags( (string) ( $plugin_data[ 'Author' ] ?? '' ) ),
			'plugin_status'      => $is_active ? 'active' : 'inactive',
		];

		$fields = $this->schema->get_fields( 'plugin' );
		foreach ( $fields as $field_name => $settings ) {
			if ( empty( $settings[ 'searchable' ] ) && empty( $settings[ 'filterable' ] ) && empty( $settings[ 'sortable' ] ) ) {
				continue;
			}

			$value = $field_map[ $field_name ] ?? null;
			if ( is_string( $value ) ) {
				$value = trim( $value );
			}

			if ( null !== $value && '' !== $value ) {
				$document[ $field_name ] = $value;
			} elseif ( ! empty( $settings[ 'sortable' ] ) ) {
				$document[ $field_name ] = '';
			}
		}

		return $document;
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
		$path = $this->admin_db_base . '/plugin';

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
		$fields = $this->schema->get_fields( 'plugin' );
		if ( empty( $fields ) ) {
			return;
		}

		$searchable = [ 'plugin_file' ];
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
