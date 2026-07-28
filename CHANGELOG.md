# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## 1.3.0 - 2026-07-28

Renamed to **Loupe - Admin Search**, following the rename of WP Loupe to Loupe Search.

### Changed
- Plugin name is now "Loupe - Admin Search". The plugin slug, directory and main file are unchanged (`wp-loupe-admin-search`), so updates do not deactivate existing installs.
- Text domain is now `wp-loupe-admin-search`, matching the plugin slug. It was previously `wp-loupe-admin`.
- `Requires Plugins` now declares `loupe-search` instead of `wp-loupe`.
- REST route moved to `loupe-admin-search/v1/search`. The old `wp-loupe-admin/v1/search` route is still registered as a deprecated alias and will be removed in a future major release.
- The `wp_loupe_admin_schema` filter is renamed to `loupe_admin_search_schema`. The old name still fires via `apply_filters_deprecated()`.
- Post type selection now reads Loupe Search's `loupe_search_post_types` filter instead of the deprecated `wp_loupe_post_types`.

### Fixed
- Admin indexes are now stored alongside Loupe Search's index. Path resolution follows `loupe_search_db_path` and defaults to `wp-content/loupe-search-db/admin/`, falling back to `wp-content/wp-loupe-db/admin/` on installs carried over from WP Loupe. Previously the path was hardcoded to the legacy directory, so on a fresh Loupe Search install the admin indexes were written to a directory the search engine did not use.
- The GitHub updater pointed at `soderlind/wp-loupe-admin`, which is not this plugin's repository; it now points at `soderlind/wp-loupe-admin-search`.
- Admin search modal no longer emits a duplicate DOM `id`: the search input/label now use a distinct `-field` id instead of colliding with the modal container's `id`, restoring the label-to-input association for assistive technologies.

## 1.2.0 - 2026-04-27

### Added
- WP Loupe Admin Search in the Command Palette.

## 1.1.0

### Added
- Admin bar search modal available on the frontend when the admin bar is visible.
- Self-contained CSS for the search modal so it renders correctly without wp-admin stylesheets.

### Fixed
- `enqueue_assets()` missing default parameter caused a fatal error on frontend (`wp_enqueue_scripts` passes no arguments).

## 1.0.0

### Added
- User indexing via `WP_Loupe_Admin_User_Indexer` with `pre_get_users` query integration.
- Comment indexing via `WP_Loupe_Admin_Comment_Indexer` with `pre_get_comments` query integration.
- Plugin indexing via `WP_Loupe_Admin_Plugin_Indexer` (non-DB source, full rebuild on change).
- `comments` REST search scope with capability check.
- Admin notice when indexes are stale or missing.
- GitHub plugin updater with release asset workflows.
- User-friendly README with developer docs split into `docs/developer.md`.

### Changed
- Bumped version from 0.1.0 to 1.0.0.
- REST search scopes now use Loupe-first with fallback for users, comments, and plugins.
- Release zip renamed from `wp-loupe-admin.zip` to `wp-loupe-admin-search.zip`.

## 0.3.0

### Added
- `WP_Loupe_Admin_Schema` — admin-owned field definitions per entity type.
- `wp_loupe_admin_schema` filter for extensibility.
- `WP_Loupe_Admin_Indexer` — separate indexes at `wp-loupe-db/admin/{post_type}/`.
- Indexes all admin post statuses (publish, draft, pending, private, future).
- `author_name` virtual field resolved from `get_userdata()`.
- Dynamic taxonomy fields from `get_object_taxonomies()`.
- Post meta fallback for custom schema fields.
- Auto-rebuild on first admin load via `needs_initial_index()`.
- `WP_Loupe_Admin_Query_Integration` — `posts_pre_query` interception for native list table search.
- Uninstall cleanup — deletes `wp-loupe-db/admin/` on plugin uninstall.
- WP-CLI commands: `wp loupe-admin reindex` and `wp loupe-admin status`.

## 0.1.0

- Initial admin-search add-on bootstrap
- Dashboard and modal admin search UI
- Admin REST search endpoint
- Support for content, users, and plugin search scopes
- Native plugin and user search interception
- PHP and JavaScript test setup
- JavaScript build and i18n tooling