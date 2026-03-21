=== WP Loupe – Admin Search ===
Contributors: PerS
Tags: admin search, search, wp loupe, dashboard search, typo-tolerant
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.0.0
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

== Changelog ==

= 1.0.0 =
* Stable release.
* Dashboard search widget, admin bar launcher, and modal search overlay.
* Search scopes: content, users, plugins, and comments.
* Native list table search interception for posts, users, and comments.
* Entity indexing for users, comments, and plugins.
* WP-CLI commands: `wp loupe-admin reindex` and `wp loupe-admin status`.
* Admin notice when indexes are stale or missing.
* Full PHP and JavaScript test coverage.

= 0.1.0 =
* Initial release.