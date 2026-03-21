# WP Loupe - Admin Search

Admin search add-on for WP Loupe.

This plugin keeps admin-search-specific UI, browser behavior, and REST endpoints separate from the main `wp-loupe` plugin while leaving the search engine, indexing, and main settings UI in `wp-loupe`.

## Scope

- Dashboard and modal admin search UI
- Admin-search-specific REST endpoints
- Search across indexed content, users, and installed plugins
- Native plugin/user admin search interception
- Server-side `posts_pre_query` interception for native list table search

## How It Works

### Admin Indexes

WP Loupe Admin maintains its own search indexes, separate from the main WP Loupe plugin. This allows the admin to index all post statuses relevant to editorial workflows (publish, draft, pending, private, future), whereas the main plugin only indexes published content.

Indexes are stored at `{wp_loupe_db_path}/admin/{post_type}/` as SQLite databases. They are created automatically on the first admin page load and kept in sync when posts are created, updated, or deleted.

### Native List Table Search

When you search from the standard WordPress edit screen (e.g. Posts → search box), WP Loupe Admin intercepts the query via the `posts_pre_query` filter and returns results from its own indexes. Results are displayed in the native `WP_List_Table` — no custom UI, just faster and more relevant results.

### Indexed Fields

Every post type is indexed with the following fields:

| Field | Searchable | Filterable | Sortable | Weight |
|---|---|---|---|---|
| `post_title` | ✓ | ✓ | ✓ | 3.0 |
| `post_content` | ✓ | | | 1.0 |
| `post_excerpt` | ✓ | | | 1.5 |
| `post_name` | ✓ | | | 1.0 |
| `post_status` | | ✓ | | — |
| `post_date` | | ✓ | ✓ | — |
| `author_name` | ✓ | ✓ | ✓ | 2.0 |

Taxonomy fields are added automatically for each public taxonomy attached to the post type (e.g. `taxonomy_category`, `taxonomy_post_tag`):

| Field | Searchable | Filterable | Sortable | Weight |
|---|---|---|---|---|
| `taxonomy_{name}` | ✓ | ✓ | | 1.5 |

Weights control how much each field contributes to search relevance. A weight of 3.0 means a match in `post_title` ranks three times higher than a match in `post_content`.

### Customizing the Schema

Use the `wp_loupe_admin_schema` filter to add, remove, or modify indexed fields:

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

## Requirements

- WordPress 6.8+
- PHP 8.3+
- `wp-loupe` active

## Plugin File

Main bootstrap file:

- [wp-loupe-admin-search.php](/Users/persoderlind/Sites/plugins/app/public/wp-content/plugins/wp-loupe-admin/wp-loupe-admin-search.php)

## Development

Install dependencies:

```sh
composer install
npm install
```

Build JavaScript:

```sh
npm run build
```

Watch JavaScript during development:

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

## Repository Notes

- Source JavaScript lives in `src/js/`
- Built JavaScript is written to `lib/js/`
- WordPress.org metadata lives in `readme.txt`
- Project planning notes live in `plan.md`

## License

GPL-2.0+