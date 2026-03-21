<?php
declare(strict_types=1);

namespace Soderlind\Plugin\WPLoupeAdmin\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_Indexer;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_Query_Integration;

final class AdminQueryIntegrationTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_registers_posts_pre_query_hook(): void {
		$indexer     = $this->createMock( WP_Loupe_Admin_Indexer::class );
		$integration = new WP_Loupe_Admin_Query_Integration( [ 'post' ], $indexer );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'posts_pre_query', [ $integration, 'maybe_short_circuit_posts_query' ], 10, 2 );

		$integration->register();
		self::addToAssertionCount( 1 );
	}

	public function test_short_circuits_supported_admin_post_searches_with_wp_loupe_hits(): void {
		$indexer = $this->createMock( WP_Loupe_Admin_Indexer::class );
		$indexer->method( 'search' )
			->with( 'hello', [ 'post' ] )
			->willReturn( [
				[ 'id' => 10, 'post_type' => 'post', '_score' => 5.0 ],
				[ 'id' => 11, 'post_type' => 'post', '_score' => 4.0 ],
				[ 'id' => 12, 'post_type' => 'page', '_score' => 3.0 ],
			] );

		$integration = new WP_Loupe_Admin_Query_Integration( [ 'post', 'page' ], $indexer );
		$query       = new \WP_Query( [
			's'              => 'hello',
			'post_type'      => 'post',
			'posts_per_page' => 2,
			'paged'          => 1,
		] );

		global $pagenow;
		$pagenow = 'edit.php';

		Functions\expect( 'is_admin' )->once()->andReturn( true );
		Functions\expect( 'get_post' )
			->twice()
			->andReturnUsing(
				static function ( int $post_id ): \WP_Post {
					return new \WP_Post( $post_id, 'post', 'publish' );
				}
			);

		$result = $integration->maybe_short_circuit_posts_query( null, $query );

		self::assertCount( 2, $result );
		self::assertSame( 2, $query->found_posts );
		self::assertSame( 1, $query->max_num_pages );
		self::assertSame( 10, $result[0]->ID );
	}
}