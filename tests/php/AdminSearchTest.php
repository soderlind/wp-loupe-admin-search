<?php
declare(strict_types=1);

namespace Soderlind\Plugin\WPLoupeAdmin\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_Search;

final class AdminSearchTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_dashboard_widget_when_user_can_access_search(): void {
		$search = new WP_Loupe_Admin_Search( [ 'post' ] );

		Functions\expect( 'is_user_logged_in' )->once()->andReturn( true );
		Functions\expect( 'current_user_can' )
			->times( 1 )
			->with( 'manage_options' )
			->andReturn( true );
		Functions\expect( 'is_blog_admin' )->once()->andReturn( true );
		Functions\expect( '__' )
			->once()
			->with( 'Loupe Search', 'wp-loupe-admin-search' )
			->andReturn( 'Loupe Search' );
		Functions\expect( 'wp_add_dashboard_widget' )
			->once()
			->with( 'wp_loupe_admin_search', 'Loupe Search', [ $search, 'render_dashboard_widget' ] );

		$search->register_dashboard_widget();
		self::addToAssertionCount( 1 );
	}

	public function test_enqueue_assets_localizes_command_palette_config_when_commands_are_registered(): void {
		$search = new WP_Loupe_Admin_Search( [ 'post' ] );

		Functions\expect( 'is_user_logged_in' )->once()->andReturn( true );
		Functions\when( 'current_user_can' )->alias(
			static function ( string $capability ): bool {
				return in_array( $capability, [ 'manage_options', 'activate_plugins', 'list_users', 'moderate_comments' ], true );
			}
		);
		Functions\expect( 'is_admin' )->once()->andReturn( true );
		Functions\expect( 'wp_script_is' )
			->once()
			->with( 'wp-commands', 'registered' )
			->andReturn( true );

		Functions\expect( 'wp_enqueue_style' )->once();
		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'wp-loupe-admin-addon',
				\Mockery::type( 'string' ),
				\Mockery::on(
					static function ( array $dependencies ): bool {
						return [ 'wp-api-fetch', 'wp-i18n', 'wp-commands', 'wp-data', 'wp-element' ] === $dependencies;
					}
				),
				\Mockery::type( 'string' ),
				true
			);
		Functions\expect( 'wp_create_nonce' )->once()->with( 'wp_rest' )->andReturn( 'test-nonce' );

		Functions\when( '__' )->alias( static fn( string $text ): string => $text );

		Functions\expect( 'wp_localize_script' )
			->once()
			->with(
				'wp-loupe-admin-addon',
				'wpLoupeAdminSearch',
				\Mockery::on(
					static function ( array $payload ): bool {
						$scope_names = array_column( $payload[ 'commandPalette' ][ 'scopes' ] ?? [], 'name' );

						return '/loupe-admin-search/v1/search' === $payload[ 'path' ]
							&& 'test-nonce' === $payload[ 'nonce' ]
							&& isset( $payload[ 'labels' ][ 'content' ], $payload[ 'labels' ][ 'users' ], $payload[ 'labels' ][ 'plugins' ], $payload[ 'labels' ][ 'comments' ] )
							&& 8 === $payload[ 'perPage' ]
							&& true === $payload[ 'commandPalette' ][ 'enabled' ]
							&& 2 === $payload[ 'commandPalette' ][ 'minQueryLength' ]
							&& 3 === $payload[ 'commandPalette' ][ 'perScopeLimit' ]
							&& [ 'content', 'users', 'plugins', 'comments' ] === $scope_names
							&& 'Loupe' === $payload[ 'commandPalette' ][ 'labels' ][ 'category' ];
					}
				)
			);

		$search->enqueue_assets( 'index.php' );
		self::addToAssertionCount( 1 );
	}
}