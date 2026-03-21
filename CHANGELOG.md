# Changelog

All notable changes to this project will be documented in this file.

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