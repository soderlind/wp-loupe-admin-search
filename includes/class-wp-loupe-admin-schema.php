<?php
namespace Soderlind\Plugin\WPLoupeAdmin;

/**
 * Schema provider for admin search indexes.
 *
 * Defines its own field schemas per entity type, independent of the main
 * WP Loupe plugin's wp_loupe_fields option. Designed to support post types
 * now, and users/comments/plugins in a later phase.
 *
 * Each schema entry follows the shape:
 *   field_name => [ 'searchable' => bool, 'filterable' => bool, 'sortable' => bool, 'weight' => float ]
 */
class WP_Loupe_Admin_Schema {

	/**
	 * Get the field schema for a given entity type.
	 *
	 * @param string $entity_type Entity type key (post type slug, or 'user', 'comment', 'plugin').
	 * @return array<string, array{ searchable: bool, filterable: bool, sortable: bool, weight: float }>
	 */
	public function get_fields( string $entity_type ): array {
		$fields = $this->get_default_fields( $entity_type );

		/**
		 * Filter the admin search schema for a given entity type.
		 *
		 * @param array  $fields      Field definitions.
		 * @param string $entity_type Entity type key.
		 */
		return (array) apply_filters( 'wp_loupe_admin_schema', $fields, $entity_type );
	}

	/**
	 * Get the default field definitions for an entity type.
	 *
	 * @param string $entity_type Entity type key.
	 * @return array<string, array{ searchable: bool, filterable: bool, sortable: bool, weight: float }>
	 */
	private function get_default_fields( string $entity_type ): array {
		// Future entity types.
		if ( 'user' === $entity_type ) {
			return $this->get_user_fields();
		}

		if ( 'comment' === $entity_type ) {
			return $this->get_comment_fields();
		}

		if ( 'plugin' === $entity_type ) {
			return $this->get_plugin_fields();
		}

		// All other entity types are post types.
		return $this->get_post_type_fields( $entity_type );
	}

	/**
	 * Post type fields — includes meta like author display name, categories, tags, status.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<string, array{ searchable: bool, filterable: bool, sortable: bool, weight: float }>
	 */
	private function get_post_type_fields( string $post_type ): array {
		$fields = [
			'post_title'   => [
				'searchable' => true,
				'filterable' => true,
				'sortable'   => true,
				'weight'     => 3.0,
			],
			'post_content' => [
				'searchable' => true,
				'filterable' => false,
				'sortable'   => false,
				'weight'     => 1.0,
			],
			'post_excerpt' => [
				'searchable' => true,
				'filterable' => false,
				'sortable'   => false,
				'weight'     => 1.5,
			],
			'post_name'    => [
				'searchable' => true,
				'filterable' => false,
				'sortable'   => false,
				'weight'     => 1.0,
			],
			'post_status'  => [
				'searchable' => false,
				'filterable' => true,
				'sortable'   => false,
				'weight'     => 0.0,
			],
			'post_date'    => [
				'searchable' => false,
				'filterable' => true,
				'sortable'   => true,
				'weight'     => 0.0,
			],
			'author_name'  => [
				'searchable' => true,
				'filterable' => true,
				'sortable'   => true,
				'weight'     => 2.0,
			],
		];

		// Add taxonomy fields for this post type.
		$taxonomies = $this->get_post_type_taxonomies( $post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$fields[ 'taxonomy_' . $taxonomy ] = [
				'searchable' => true,
				'filterable' => true,
				'sortable'   => false,
				'weight'     => 1.5,
			];
		}

		return $fields;
	}

	/**
	 * User fields (future phase).
	 *
	 * @return array<string, array{ searchable: bool, filterable: bool, sortable: bool, weight: float }>
	 */
	private function get_user_fields(): array {
		return [
			'display_name' => [
				'searchable' => true,
				'filterable' => true,
				'sortable'   => true,
				'weight'     => 3.0,
			],
			'user_login'   => [
				'searchable' => true,
				'filterable' => true,
				'sortable'   => false,
				'weight'     => 2.0,
			],
			'user_email'   => [
				'searchable' => true,
				'filterable' => true,
				'sortable'   => false,
				'weight'     => 2.0,
			],
			'user_role'    => [
				'searchable' => false,
				'filterable' => true,
				'sortable'   => false,
				'weight'     => 0.0,
			],
		];
	}

	/**
	 * Comment fields (future phase).
	 *
	 * @return array<string, array{ searchable: bool, filterable: bool, sortable: bool, weight: float }>
	 */
	private function get_comment_fields(): array {
		return [
			'comment_content'      => [
				'searchable' => true,
				'filterable' => false,
				'sortable'   => false,
				'weight'     => 1.0,
			],
			'comment_author'       => [
				'searchable' => true,
				'filterable' => true,
				'sortable'   => true,
				'weight'     => 2.0,
			],
			'comment_author_email' => [
				'searchable' => true,
				'filterable' => true,
				'sortable'   => false,
				'weight'     => 1.0,
			],
			'comment_date'         => [
				'searchable' => false,
				'filterable' => true,
				'sortable'   => true,
				'weight'     => 0.0,
			],
		];
	}

	/**
	 * Plugin fields (future phase).
	 *
	 * @return array<string, array{ searchable: bool, filterable: bool, sortable: bool, weight: float }>
	 */
	private function get_plugin_fields(): array {
		return [
			'plugin_name'        => [
				'searchable' => true,
				'filterable' => true,
				'sortable'   => true,
				'weight'     => 3.0,
			],
			'plugin_description' => [
				'searchable' => true,
				'filterable' => false,
				'sortable'   => false,
				'weight'     => 1.0,
			],
			'plugin_author'      => [
				'searchable' => true,
				'filterable' => true,
				'sortable'   => false,
				'weight'     => 2.0,
			],
			'plugin_status'      => [
				'searchable' => false,
				'filterable' => true,
				'sortable'   => false,
				'weight'     => 0.0,
			],
		];
	}

	/**
	 * Get public taxonomies attached to a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<int, string>
	 */
	private function get_post_type_taxonomies( string $post_type ): array {
		if ( ! function_exists( 'get_object_taxonomies' ) ) {
			// Fallback for common post types when WP isn't fully loaded.
			if ( 'post' === $post_type ) {
				return [ 'category', 'post_tag' ];
			}
			return [];
		}

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$result     = [];

		foreach ( $taxonomies as $tax_name => $tax_obj ) {
			if ( $tax_obj->show_ui ) {
				$result[] = $tax_name;
			}
		}

		return $result;
	}
}
