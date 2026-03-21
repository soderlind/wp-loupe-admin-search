# WP Loupe – Admin Search

A faster, smarter search experience for the WordPress admin. This add-on replaces the default admin search with [WP Loupe](https://github.com/soderlind/wp-loupe)-powered typo-tolerant, relevance-ranked results.

## Download

[Download the latest release](https://github.com/soderlind/wp-loupe-admin/releases/latest/download/wp-loupe-admin-search.zip) (zip file, install via **Plugins → Add New → Upload Plugin**).

## Features

- **Dashboard search widget** — search your site content right from the dashboard.
- **Admin bar launcher** — press the search icon in the admin bar to open a modal search overlay.
- **Modal search with scope switcher** — search across content, users, plugins, and comments from a single modal.
- **Native list table search** — the standard search boxes on the Posts, Users, and Comments screens are automatically enhanced with Loupe results.
- **Typo-tolerant & ranked** — powered by the Loupe search engine, results are fuzzy-matched and ranked by relevance.
- **All admin post statuses** — indexes publish, draft, pending, private, and future posts (the main WP Loupe plugin only indexes published content).

## Requirements

- WordPress 6.8+
- PHP 8.3+
- [WP Loupe](https://github.com/soderlind/wp-loupe) plugin active

## Getting Started

1. Install and activate [WP Loupe](https://github.com/soderlind/wp-loupe).
2. Install and activate WP Loupe – Admin Search.
3. Search indexes are built automatically on the first admin page load.
4. Start searching — use the dashboard widget, admin bar icon, or any native search box.

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