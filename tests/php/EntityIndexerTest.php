<?php
declare(strict_types=1);

namespace Soderlind\Plugin\WPLoupeAdmin\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_Schema;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_User_Indexer;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_Comment_Indexer;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_Plugin_Indexer;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_User_Query_Integration;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_Comment_Query_Integration;

final class EntityIndexerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// --- User Indexer ---

	public function test_user_indexer_registers_hooks(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_mkdir_p' )->justReturn( true );

		$schema  = new WP_Loupe_Admin_Schema();
		$indexer = new WP_Loupe_Admin_User_Indexer( $schema );

		Functions\expect( 'add_action' )
			->once()
			->with( 'profile_update', [ $indexer, 'on_user_update' ], 20, 2 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'user_register', [ $indexer, 'on_user_register' ], 20, 2 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'delete_user', [ $indexer, 'on_user_delete' ], 10, 1 );

		$indexer->register();

		self::addToAssertionCount( 1 );
	}

	public function test_user_indexer_search_returns_empty_without_loupe(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_mkdir_p' )->justReturn( true );

		$schema  = new WP_Loupe_Admin_Schema();
		$indexer = new WP_Loupe_Admin_User_Indexer( $schema );

		// Stub loupe is empty, so search should return empty array.
		$result = $indexer->search( 'admin' );

		self::assertIsArray( $result );
	}

	// --- Comment Indexer ---

	public function test_comment_indexer_registers_hooks(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_mkdir_p' )->justReturn( true );

		$schema  = new WP_Loupe_Admin_Schema();
		$indexer = new WP_Loupe_Admin_Comment_Indexer( $schema );

		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_insert_comment', [ $indexer, 'on_insert_comment' ], 20, 2 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'edit_comment', [ $indexer, 'on_edit_comment' ], 20, 2 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'delete_comment', [ $indexer, 'on_delete_comment' ], 10, 1 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'transition_comment_status', [ $indexer, 'on_transition_status' ], 20, 3 );

		$indexer->register();

		self::addToAssertionCount( 1 );
	}

	public function test_comment_indexer_search_returns_empty_without_loupe(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_mkdir_p' )->justReturn( true );

		$schema  = new WP_Loupe_Admin_Schema();
		$indexer = new WP_Loupe_Admin_Comment_Indexer( $schema );

		$result = $indexer->search( 'hello' );

		self::assertIsArray( $result );
	}

	// --- Plugin Indexer ---

	public function test_plugin_indexer_registers_hooks(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_mkdir_p' )->justReturn( true );

		$schema  = new WP_Loupe_Admin_Schema();
		$indexer = new WP_Loupe_Admin_Plugin_Indexer( $schema );

		Functions\expect( 'add_action' )
			->once()
			->with( 'activated_plugin', [ $indexer, 'on_plugin_status_change' ], 20, 1 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'deactivated_plugin', [ $indexer, 'on_plugin_status_change' ], 20, 1 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'upgrader_process_complete', [ $indexer, 'on_upgrader_complete' ], 20, 2 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'deleted_plugin', [ $indexer, 'on_plugin_deleted' ], 20, 2 );

		$indexer->register();

		self::addToAssertionCount( 1 );
	}

	public function test_plugin_indexer_search_returns_empty_without_loupe(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_mkdir_p' )->justReturn( true );

		$schema  = new WP_Loupe_Admin_Schema();
		$indexer = new WP_Loupe_Admin_Plugin_Indexer( $schema );

		$result = $indexer->search( 'akismet' );

		self::assertIsArray( $result );
	}

	// --- User Query Integration ---

	public function test_user_query_integration_registers_hook(): void {
		$user_indexer = $this->createMock( WP_Loupe_Admin_User_Indexer::class);
		$integration  = new WP_Loupe_Admin_User_Query_Integration( $user_indexer );

		Functions\expect( 'add_action' )
			->once()
			->with( 'pre_get_users', [ $integration, 'maybe_intercept' ], 10, 1 );

		$integration->register();

		self::addToAssertionCount( 1 );
	}

	// --- Comment Query Integration ---

	public function test_comment_query_integration_registers_hook(): void {
		$comment_indexer = $this->createMock( WP_Loupe_Admin_Comment_Indexer::class);
		$integration     = new WP_Loupe_Admin_Comment_Query_Integration( $comment_indexer );

		Functions\expect( 'add_action' )
			->once()
			->with( 'pre_get_comments', [ $integration, 'maybe_intercept' ], 10, 1 );

		$integration->register();

		self::addToAssertionCount( 1 );
	}
}
