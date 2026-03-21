<?php
declare(strict_types=1);

namespace Soderlind\Plugin\WPLoupeAdmin\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Soderlind\Plugin\WPLoupeAdmin\WP_Loupe_Admin_Schema;

final class AdminSchemaTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_post_type_fields_include_core_fields_and_author(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$schema = new WP_Loupe_Admin_Schema();
		$fields = $schema->get_fields( 'post' );

		// Core fields.
		self::assertArrayHasKey( 'post_title', $fields );
		self::assertArrayHasKey( 'post_content', $fields );
		self::assertArrayHasKey( 'post_excerpt', $fields );
		self::assertArrayHasKey( 'post_name', $fields );
		self::assertArrayHasKey( 'post_status', $fields );
		self::assertArrayHasKey( 'post_date', $fields );
		self::assertArrayHasKey( 'author_name', $fields );

		// Verify searchable/filterable/sortable/weight shape.
		self::assertTrue( $fields['post_title']['searchable'] );
		self::assertTrue( $fields['post_title']['filterable'] );
		self::assertTrue( $fields['post_title']['sortable'] );
		self::assertSame( 3.0, $fields['post_title']['weight'] );

		// author_name is searchable + filterable + sortable.
		self::assertTrue( $fields['author_name']['searchable'] );
		self::assertTrue( $fields['author_name']['filterable'] );
		self::assertTrue( $fields['author_name']['sortable'] );
		self::assertSame( 2.0, $fields['author_name']['weight'] );

		// post_status is filterable only.
		self::assertFalse( $fields['post_status']['searchable'] );
		self::assertTrue( $fields['post_status']['filterable'] );
		self::assertFalse( $fields['post_status']['sortable'] );
	}

	public function test_post_type_includes_taxonomy_fields_for_post(): void {
		// get_object_taxonomies not available => fallback returns category + post_tag for 'post'.
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$schema = new WP_Loupe_Admin_Schema();
		$fields = $schema->get_fields( 'post' );

		self::assertArrayHasKey( 'taxonomy_category', $fields );
		self::assertArrayHasKey( 'taxonomy_post_tag', $fields );
		self::assertTrue( $fields['taxonomy_category']['searchable'] );
		self::assertTrue( $fields['taxonomy_category']['filterable'] );
		self::assertSame( 1.5, $fields['taxonomy_category']['weight'] );
	}

	public function test_custom_post_type_has_no_taxonomy_fallback(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$schema = new WP_Loupe_Admin_Schema();
		$fields = $schema->get_fields( 'book' );

		// book has no fallback taxonomies (only 'post' does).
		$tax_fields = array_filter(
			array_keys( $fields ),
			static fn( string $k ): bool => 0 === strpos( $k, 'taxonomy_' )
		);
		self::assertEmpty( $tax_fields );
	}

	public function test_user_entity_returns_user_fields(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$schema = new WP_Loupe_Admin_Schema();
		$fields = $schema->get_fields( 'user' );

		self::assertArrayHasKey( 'display_name', $fields );
		self::assertArrayHasKey( 'user_login', $fields );
		self::assertArrayHasKey( 'user_email', $fields );
		self::assertArrayHasKey( 'user_role', $fields );
		self::assertFalse( $fields['user_role']['searchable'] );
		self::assertTrue( $fields['user_role']['filterable'] );
	}

	public function test_comment_entity_returns_comment_fields(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$schema = new WP_Loupe_Admin_Schema();
		$fields = $schema->get_fields( 'comment' );

		self::assertArrayHasKey( 'comment_content', $fields );
		self::assertArrayHasKey( 'comment_author', $fields );
		self::assertArrayHasKey( 'comment_author_email', $fields );
		self::assertArrayHasKey( 'comment_date', $fields );
	}

	public function test_plugin_entity_returns_plugin_fields(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$schema = new WP_Loupe_Admin_Schema();
		$fields = $schema->get_fields( 'plugin' );

		self::assertArrayHasKey( 'plugin_name', $fields );
		self::assertArrayHasKey( 'plugin_description', $fields );
		self::assertArrayHasKey( 'plugin_author', $fields );
		self::assertArrayHasKey( 'plugin_status', $fields );
	}

	public function test_schema_is_filterable(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'wp_loupe_admin_schema', \Mockery::type( 'array' ), 'post' )
			->andReturnUsing(
				static function ( string $hook, array $fields ): array {
					$fields['my_custom'] = [
						'searchable' => true,
						'filterable' => false,
						'sortable'   => false,
						'weight'     => 1.0,
					];
					return $fields;
				}
			);

		$schema = new WP_Loupe_Admin_Schema();
		$fields = $schema->get_fields( 'post' );

		self::assertArrayHasKey( 'my_custom', $fields );
		self::assertTrue( $fields['my_custom']['searchable'] );
	}
}
