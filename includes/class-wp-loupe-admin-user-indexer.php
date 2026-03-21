<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

use Loupe\Loupe\Config\TypoTolerance;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\SearchParameters;

/**
 * Admin-specific Loupe indexer for WordPress users.
 *
 * Maintains a separate index at {wp_loupe_db_path}/admin/user/
 * using the admin schema's user entity definition.
 */
class WP_Loupe_Admin_User_Indexer {

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
		add_action( 'profile_update', [ $this, 'on_user_update' ], 20, 2 );
		add_action( 'user_register', [ $this, 'on_user_register' ], 20, 2 );
		add_action( 'delete_user', [ $this, 'on_user_delete' ], 10, 1 );
	}

	/**
	 * Search the user index.
	 *
	 * @param string $query Search query.
	 * @return array<int,array<string,mixed>>
	 */
	public function search( string $query ): array {
		if ( ! $this->loupe ) {
			return [];
		}

		$fields = $this->schema->get_fields( 'user' );
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
	 * Index a user on profile update.
	 *
	 * @param int       $user_id       User ID.
	 * @param \WP_User|array $old_user_data Old user data.
	 * @return void
	 */
	public function on_user_update( int $user_id, $old_user_data ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$this->index_user( $user );
	}

	/**
	 * Index a user on registration.
	 *
	 * @param int   $user_id  User ID.
	 * @param array $userdata User data.
	 * @return void
	 */
	public function on_user_register( int $user_id, $userdata = [] ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$this->index_user( $user );
	}

	/**
	 * Remove a user from the index on delete.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_user_delete( int $user_id ): void {
		if ( ! $this->loupe ) {
			return;
		}

		try {
			$this->loupe->deleteDocument( $user_id );
		} catch (\Throwable $e) {
			// Silently ignore.
		}
	}

	/**
	 * Fully rebuild the user index.
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

		$users = get_users( [
			'number' => -1,
			'fields' => 'all',
		] );

		$documents = [];
		foreach ( $users as $user ) {
			$documents[] = $this->prepare_document( $user );
		}

		if ( ! empty( $documents ) ) {
			$this->loupe->addDocuments( $documents );
		}
	}

	/**
	 * Check if the user index needs initial population.
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
	 * Index a single user.
	 *
	 * @param \WP_User $user User object.
	 * @return void
	 */
	private function index_user( $user ): void {
		if ( ! $this->loupe ) {
			return;
		}

		$document = $this->prepare_document( $user );
		$this->loupe->addDocument( $document );
	}

	/**
	 * Prepare a document for the Loupe index.
	 *
	 * @param \WP_User $user User object.
	 * @return array<string,mixed>
	 */
	private function prepare_document( $user ): array {
		$document = [ 'id' => (int) $user->ID ];

		$fields = $this->schema->get_fields( 'user' );
		foreach ( $fields as $field_name => $settings ) {
			if ( empty( $settings[ 'searchable' ] ) && empty( $settings[ 'filterable' ] ) && empty( $settings[ 'sortable' ] ) ) {
				continue;
			}

			$value = $this->resolve_field_value( $user, $field_name );
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
	 * Resolve a field value from a user object.
	 *
	 * @param \WP_User $user       User object.
	 * @param string   $field_name Field name.
	 * @return mixed
	 */
	private function resolve_field_value( $user, string $field_name ) {
		if ( 'user_role' === $field_name ) {
			$roles = (array) ( $user->roles ?? [] );
			return ! empty( $roles ) ? implode( ', ', $roles ) : '';
		}

		if ( isset( $user->$field_name ) ) {
			return (string) $user->$field_name;
		}

		return null;
	}

	/**
	 * Sanitize a value for the Loupe index.
	 *
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
		$path = $this->admin_db_base . '/user';

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
		$fields = $this->schema->get_fields( 'user' );
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
	 * Delete the on-disk user index.
	 *
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
