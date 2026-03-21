# Developer Documentation

Technical reference for developers extending or contributing to WP Loupe – Admin Search.

## Admin Index Architecture

WP Loupe Admin maintains its own search indexes, separate from the main WP Loupe plugin. This allows the admin to index all post statuses relevant to editorial workflows (publish, draft, pending, private, future), whereas the main plugin only indexes published content.

Indexes are stored at `{wp_loupe_db_path}/admin/{entity_type}/` as SQLite databases. They are created automatically on the first admin page load and kept in sync incrementally.

## Indexed Fields

### Post Types

Every indexed post type gets these core fields:

| Field | Searchable | Filterable | Sortable | Weight |
|---|---|---|---|---|
| `post_title` | ✓ | ✓ | ✓ | 3.0 |
| `post_content` | ✓ | | | 1.0 |
| `post_excerpt` | ✓ | | | 1.5 |
| `post_name` | ✓ | | | 1.0 |
| `post_status` | | ✓ | | — |
| `post_date` | | ✓ | ✓ | — |
| `author_name` | ✓ | ✓ | ✓ | 2.0 |

Taxonomy fields are added automatically for each `show_ui` taxonomy attached to the post type (e.g. `taxonomy_category`, `taxonomy_post_tag`):

| Field | Searchable | Filterable | Sortable | Weight |
|---|---|---|---|---|
| `taxonomy_{name}` | ✓ | ✓ | | 1.5 |

Weights control how much each field contributes to search relevance. A weight of 3.0 means a match in `post_title` ranks three times higher than a match in `post_content`.

Any field not matched to a `WP_Post` property, virtual field, or taxonomy falls back to `get_post_meta()`.

### Users

| Field | Searchable | Filterable | Sortable | Weight |
|---|---|---|---|---|
| `display_name` | ✓ | ✓ | ✓ | 3.0 |
| `user_login` | ✓ | ✓ | | 2.0 |
| `user_email` | ✓ | ✓ | | 2.0 |
| `user_role` | | ✓ | | — |

### Comments

| Field | Searchable | Filterable | Sortable | Weight |
|---|---|---|---|---|
| `comment_content` | ✓ | | | 1.0 |
| `comment_author` | ✓ | ✓ | ✓ | 2.0 |
| `comment_author_email` | ✓ | ✓ | | 1.0 |
| `comment_date` | | ✓ | ✓ | — |

### Plugins

| Field | Searchable | Filterable | Sortable | Weight |
|---|---|---|---|---|
| `plugin_name` | ✓ | ✓ | ✓ | 3.0 |
| `plugin_description` | ✓ | | | 1.0 |
| `plugin_author` | ✓ | ✓ | | 2.0 |
| `plugin_status` | | ✓ | | — |

## Hooks

### `wp_loupe_admin_schema`

Filter the indexed fields for any entity type. Use this to add custom meta fields to the search index.

```php
add_filter( 'wp_loupe_admin_schema', function ( array $fields, string $entity_type ): array {
    if ( 'post' === $entity_type ) {
        $fields['my_custom_meta'] = [
            'searchable' => true,
            'filterable' => false,
            'sortable'   => false,
            'weight'     => 1.0,
        ];
    }
    return $fields;
}, 10, 2 );
```

After changing the schema, delete the admin index folder (`wp-loupe-db/admin/`) to trigger a full rebuild on the next admin page load.

## REST API

### `wp-loupe-admin/v1/search`

Search endpoint with scope parameter.

**Method:** `GET`

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `s` | string | yes | Search query |
| `scope` | string | no | Search scope: `content` (default), `users`, `plugins`, `comments` |
| `page` | integer | no | Page number (default `1`) |

**Capability requirements per scope:**

| Scope | Required Capability |
|---|---|
| `content` | `edit_posts` |
| `users` | `list_users` |
| `plugins` | `activate_plugins` |
| `comments` | `moderate_comments` |

## Native Search Interception

The plugin intercepts native WordPress admin search in three places:

- **Posts list table** — via `posts_pre_query` filter in `WP_Loupe_Admin_Query_Integration`
- **Users list table** — via `pre_get_users` in `WP_Loupe_Admin_User_Query_Integration`
- **Comments list table** — via `pre_get_comments` in `WP_Loupe_Admin_Comment_Query_Integration`

Each integration includes a re-entrancy guard to prevent infinite loops.

## WP-CLI Commands

```sh
# Rebuild all admin indexes
wp loupe-admin reindex

# Show index status
wp loupe-admin status
```

## Key Classes

| Class | Responsibility |
|---|---|
| `WP_Loupe_Admin_Loader` | Request-scoped singleton bootstrap |
| `WP_Loupe_Admin_Schema` | Field definitions per entity type |
| `WP_Loupe_Admin_Indexer` | Post type indexing |
| `WP_Loupe_Admin_User_Indexer` | User indexing |
| `WP_Loupe_Admin_Comment_Indexer` | Comment indexing |
| `WP_Loupe_Admin_Plugin_Indexer` | Plugin indexing (non-DB source) |
| `WP_Loupe_Admin_Query_Integration` | `posts_pre_query` interception |
| `WP_Loupe_Admin_User_Query_Integration` | `pre_get_users` interception |
| `WP_Loupe_Admin_Comment_Query_Integration` | `pre_get_comments` interception |
| `WP_Loupe_Admin_Rest` | REST API endpoint |
| `WP_Loupe_Admin_Search` | Dashboard widget, admin bar, modal UI |
| `WP_Loupe_Admin_CLI` | WP-CLI commands |
| `WP_Loupe_Admin_Notice` | Admin notice for stale/missing indexes |

## Local Development

Install dependencies:

```sh
composer install
npm install
```

Build JavaScript:

```sh
npm run build
```

Watch during development:

```sh
npm run dev:js
```

Run tests:

```sh
./vendor/bin/phpunit
npm run test:js
```

Generate translation files:

```sh
npm run i18n
```

### Project Structure

- `src/js/` — Source JavaScript
- `lib/js/` — Built JavaScript
- `lib/css/` — Built CSS
- `includes/` — PHP classes
- `tests/` — PHPUnit and Vitest tests
- `scripts/` — Build scripts
- `languages/` — Translation files
