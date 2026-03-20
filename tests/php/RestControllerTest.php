<?php
declare(strict_types=1);

namespace Soderlind\Plugin\WPLoupeAdmin\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_REST;

final class RestControllerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_hooks_and_routes_when_rest_api_is_ready(): void {
		$controller = new WP_Loupe_Admin_REST( [ 'post' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', [ $controller, 'register_routes' ] );

		Functions\expect( 'did_action' )
			->once()
			->with( 'rest_api_init' )
			->andReturn( 1 );

		Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'wp-loupe-admin/v1',
				'/search',
				\Mockery::on(
					static function ( array $args ): bool {
						return isset( $args['callback'], $args['permission_callback'], $args['args']['scope']['default'] )
							&& 'content' === $args['args']['scope']['default'];
					}
				)
			);

		$controller->register();
		self::addToAssertionCount( 1 );
	}

	public function test_can_access_search_for_plugin_scope_uses_plugin_capability(): void {
		$controller = new WP_Loupe_Admin_REST( [ 'post' ] );
		$request    = new \WP_REST_Request();
		$request->set_param( 'scope', 'plugins' );

		Functions\when( 'sanitize_key' )->alias( static fn( string $value ): string => $value );
		Functions\expect( 'is_user_logged_in' )->once()->andReturn( true );
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'activate_plugins' )
			->andReturn( true );

		self::assertTrue( $controller->can_access_search( $request ) );
	}

	public function test_can_access_search_checks_post_type_edit_capabilities(): void {
		$controller = new WP_Loupe_Admin_REST( [ 'page' ] );

		Functions\when( 'sanitize_key' )->alias( static fn( string $value ): string => $value );
		Functions\expect( 'is_user_logged_in' )->once()->andReturn( true );
		Functions\expect( 'current_user_can' )
			->times( 2 )
			->withArgs(
				static function ( string $capability ): bool {
					return in_array( $capability, [ 'manage_options', 'edit_pages' ], true );
				}
			)
			->andReturnUsing(
				static function ( string $capability ): bool {
					return 'edit_pages' === $capability;
				}
			);

		Functions\expect( 'get_post_type_object' )
			->once()
			->with( 'page' )
			->andReturn( (object) [ 'cap' => (object) [ 'edit_posts' => 'edit_pages' ] ] );

		self::assertTrue( $controller->can_access_search() );
	}

	public function test_handle_search_requires_a_non_empty_query(): void {
		$controller = new WP_Loupe_Admin_REST( [ 'post' ] );
		$request    = new \WP_REST_Request();
		$request->set_param( 'q', '   ' );
		$request->set_param( 'scope', 'content' );

		Functions\when( 'sanitize_key' )->alias( static fn( string $value ): string => $value );
		Functions\expect( '__' )
			->once()
			->with( 'Missing or empty query parameter "q".', 'wp-loupe-admin' )
			->andReturn( 'Missing or empty query parameter "q".' );

		$result = $controller->handle_search( $request );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'wp_loupe_admin_search_missing_query', $result->get_error_code() );
	}
}