# WP Loupe – Admin Search

A faster, smarter search experience for the WordPress admin. This add-on replaces the default admin search with [WP Loupe](https://github.com/soderlind/wp-loupe)-powered typo-tolerant, relevance-ranked results.

## Download

[Download the latest release](https://github.com/soderlind/wp-loupe-admin/releases/latest/download/wp-loupe-admin-search.zip) (zip file, install via **Plugins → Add New → Upload Plugin**).

## Features

- **Dashboard search widget** — search your site content right from the dashboard.
- **Admin bar launcher** — press the search icon in the admin bar to open a modal search overlay.
- **Modal search with scope switcher** — search across content, users, plugins, and comments from a single modal.
- **Native list table search** — the standard search boxes on Posts, Pages, custom post types, Media, Users, and Comments screens are automatically enhanced with Loupe results.
- **Typo-tolerant & ranked** — powered by the Loupe search engine, results are fuzzy-matched and ranked by relevance.
- **All admin post statuses** — indexes publish, draft, pending, private, and future posts (the main WP Loupe plugin only indexes published content).
- **Separate admin indexes** — maintains its own search indexes independent of the main WP Loupe plugin, stored as lightweight SQLite databases.
- **Automatic index management** — indexes are built on first admin load and kept in sync incrementally when content changes.
- **Extensible schema** — add custom meta fields to the search index via the `wp_loupe_admin_schema` filter.
- **Auto-updates** — receives updates directly from GitHub releases.

## Requirements

- WordPress 6.8+
- PHP 8.3+
- [WP Loupe](https://github.com/soderlind/wp-loupe) plugin active

## Installation

1. Download the latest [`wp-loupe-admin-search.zip`](https://github.com/soderlind/wp-loupe-admin-search/releases/latest/download/wp-loupe-admin-search.zip).
2. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate the plugin.

The plugin updates itself automatically via GitHub releases using [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker).


## WP-CLI

```sh
# Rebuild all admin indexes
wp loupe-admin reindex

# Show index status
wp loupe-admin status
```

## Developer Documentation

Hooks, REST API reference, indexed field schemas, class reference, and local development setup are documented in [docs/developer.md](docs/developer.md).

## License

GPL-2.0+