=== WP Loupe – Admin Search ===
Contributors: PerS
Tags: admin search, search, wp loupe, dashboard search, typo-tolerant
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.1.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

A faster, smarter search experience for the WordPress admin — typo-tolerant and relevance-ranked.

== Description ==

WP Loupe – Admin Search replaces the default WordPress admin search with fast, typo-tolerant, relevance-ranked results powered by the [WP Loupe](https://github.com/soderlind/wp-loupe) search engine.

**Features:**

* Dashboard search widget for quick access to site content.
* Admin bar launcher that opens a modal search overlay.
* Modal search with scope switcher — search content, users, plugins, and comments from one place.
* Native list table enhancement — the standard search boxes on the Posts, Users, and Comments screens are automatically powered by Loupe.
* Indexes all admin post statuses: publish, draft, pending, private, and future.

**Requirements:**

* WordPress 6.8 or higher
* PHP 8.3 or higher
* [WP Loupe](https://github.com/soderlind/wp-loupe) plugin active

== Installation ==

1. Install and activate the [WP Loupe](https://github.com/soderlind/wp-loupe) plugin.
2. Upload the plugin files to `/wp-content/plugins/wp-loupe-admin-search/`, or install through the WordPress plugins screen.
3. Activate WP Loupe – Admin Search through the 'Plugins' screen in WordPress.
4. Search indexes are built automatically on the first admin page load — no configuration needed.

== Frequently Asked Questions ==

= Does this replace the main WP Loupe plugin? =

No. This is an add-on that enhances admin search only. The main WP Loupe plugin handles front-end search and the search engine itself.

= How do I rebuild the search indexes? =

Indexes rebuild automatically when needed. You can also use WP-CLI: `wp loupe-admin reindex`.

= Can I add custom fields to the search index? =

Yes — use the `wp_loupe_admin_schema` filter. See the [developer documentation](https://github.com/soderlind/wp-loupe-admin/blob/main/docs/developer.md) for details.

= Where do I report bugs or request features? =

Open an issue on GitHub: https://github.com/soderlind/wp-loupe-admin-search/issues

== Changelog ==

= 1.1.0 =
* Admin bar search modal now available on the frontend when the admin bar is visible.
* Self-contained CSS — the search modal renders correctly without wp-admin stylesheets.
* Fixed fatal error when enqueuing assets on the frontend.

= 1.0.0 =
* User indexing with native Users list table search interception.
* Comment indexing with native Comments list table search interception.
* Plugin indexing (non-DB source, full rebuild on change).
* Comments REST search scope with capability check.
* Admin notice when indexes are stale or missing.
* GitHub plugin updater with release asset workflows.
* User-friendly README with developer docs split into `docs/developer.md`.
* REST search scopes now use Loupe-first with fallback.
* Release zip renamed from `wp-loupe-admin.zip` to `wp-loupe-admin-search.zip`.

= 0.3.0 =
* Admin-owned schema with `wp_loupe_admin_schema` filter.
* Separate admin indexes at `wp-loupe-db/admin/{post_type}/`.
* Indexes all admin post statuses (publish, draft, pending, private, future).
* Author name virtual field, dynamic taxonomy fields, post meta fallback.
* Native list table search interception via `posts_pre_query`.
* Uninstall cleanup for admin index folder.
* WP-CLI commands: `wp loupe-admin reindex` and `wp loupe-admin status`.

= 0.1.0 =
* Initial release.
* Dashboard search widget, admin bar launcher, and modal search overlay.
* Admin REST search endpoint with content, users, and plugin scopes.
* PHP and JavaScript test setup.
* JavaScript build and i18n tooling.