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
			->with( 'WP Loupe Search', 'wp-loupe-admin' )
			->andReturn( 'WP Loupe Search' );
		Functions\expect( 'wp_add_dashboard_widget' )
			->once()
			->with( 'wp_loupe_admin_search', 'WP Loupe Search', [ $search, 'render_dashboard_widget' ] );

		$search->register_dashboard_widget();
		self::addToAssertionCount( 1 );
	}

	public function test_enqueue_assets_localizes_scope_labels(): void {
		$search = new WP_Loupe_Admin_Search( [ 'post' ] );

		Functions\expect( 'is_user_logged_in' )->once()->andReturn( true );
		Functions\expect( 'current_user_can' )
			->times( 1 )
			->with( 'manage_options' )
			->andReturn( true );
		Functions\expect( 'is_admin' )->once()->andReturn( true );

		Functions\expect( 'wp_register_style' )->once();
		Functions\expect( 'wp_register_script' )->once();
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'wp-loupe-admin-addon' );
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'wp-loupe-admin-addon' );
		Functions\expect( 'wp_create_nonce' )->once()->with( 'wp_rest' )->andReturn( 'test-nonce' );

		Functions\expect( '__' )
			->times( 11 )
			->andReturnUsing( static fn( string $text ): string => $text );

		Functions\expect( 'wp_localize_script' )
			->once()
			->with(
				'wp-loupe-admin-addon',
				'wpLoupeAdminSearch',
				\Mockery::on(
					static function ( array $payload ): bool {
						return '/wp-loupe-admin/v1/search' === $payload[ 'path' ]
							&& 'test-nonce' === $payload[ 'nonce' ]
							&& isset( $payload[ 'labels' ][ 'content' ], $payload[ 'labels' ][ 'users' ], $payload[ 'labels' ][ 'plugins' ] )
							&& 8 === $payload[ 'perPage' ];
					}
				)
			);

		$search->enqueue_assets( 'index.php' );
		self::addToAssertionCount( 1 );
	}
}