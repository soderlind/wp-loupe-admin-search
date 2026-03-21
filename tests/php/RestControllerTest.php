<?php
declare(strict_types=1);

namespace Soderlind\Plugin\WPLoupeAdmin\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_Indexer;
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

	public function test_handle_search_returns_content_context_fields(): void {
		$indexer = $this->createMock( WP_Loupe_Admin_Indexer::class );
		$indexer->method( 'search' )
			->with( 'hello' )
			->willReturn( [
				[
					'id'        => 42,
					'post_type' => 'post',
					'_score'    => 9.5,
				],
			] );

		$controller = new WP_Loupe_Admin_REST( [ 'post' ], $indexer );
		$request    = new \WP_REST_Request();
		$request->set_param( 'q', 'hello' );
		$request->set_param( 'scope', 'content' );

		Functions\when( 'sanitize_key' )->alias( static fn( string $value ): string => $value );
		Functions\when( '__' )->alias( static fn( string $text ): string => $text );
		Functions\when( 'get_option' )->alias( static fn( string $name ): string => 'date_format' === $name ? 'Y-m-d' : '' );
		Functions\when( 'rest_ensure_response' )->alias( static fn( array $data ): \WP_REST_Response => new \WP_REST_Response( $data ) );
		Functions\expect( 'get_post' )
			->once()
			->with( 42 )
			->andReturn( (object) [
				'ID'          => 42,
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => 7,
			] );
		Functions\expect( 'get_edit_post_link' )
			->once()
			->with( 42, 'raw' )
			->andReturn( 'https://plugins.local/wp-admin/post.php?post=42&action=edit' );
		Functions\expect( 'get_post_type_object' )
			->once()
			->with( 'post' )
			->andReturn( (object) [ 'labels' => (object) [ 'singular_name' => 'Post' ] ] );
		Functions\expect( 'get_post_status_object' )
			->once()
			->with( 'publish' )
			->andReturn( (object) [ 'label' => 'Published' ] );
		Functions\expect( 'get_the_title' )->once()->with( 42 )->andReturn( 'Hello World' );
		Functions\expect( 'get_permalink' )->once()->with( 42 )->andReturn( 'https://plugins.local/hello-world/' );
		Functions\expect( 'get_the_excerpt' )->once()->with( 42 )->andReturn( 'Excerpt body for admin search results.' );
		Functions\expect( 'wp_strip_all_tags' )
			->once()
			->with( 'Excerpt body for admin search results.' )
			->andReturn( 'Excerpt body for admin search results.' );
		Functions\expect( 'wp_trim_words' )
			->once()
			->with( 'Excerpt body for admin search results.', 24, '...' )
			->andReturn( 'Excerpt body for admin search results.' );
		Functions\expect( 'get_userdata' )
			->once()
			->with( 7 )
			->andReturn( (object) [ 'display_name' => 'Per' ] );
		Functions\expect( 'get_the_date' )
			->once()
			->with( 'Y-m-d', 42 )
			->andReturn( '2026-03-21' );

		$result = $controller->handle_search( $request );

		self::assertInstanceOf( \WP_REST_Response::class, $result );
		self::assertSame( 'Excerpt body for admin search results.', $result->get_data()['hits'][0]['excerpt'] );
		self::assertSame( 'Per', $result->get_data()['hits'][0]['authorName'] );
		self::assertSame( '2026-03-21', $result->get_data()['hits'][0]['dateLabel'] );
	}
}