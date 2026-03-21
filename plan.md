# WP Loupe Admin Plan

## Goal

Keep `wp-loupe-admin` focused on admin search surfaces for `wp-loupe`, while keeping the search engine, indexing, public/runtime search behavior, and the main WP Loupe settings UI in the main plugin.

## Current State

- `wp-loupe-admin` exists as a bootstrap plugin and already declares `wp-loupe` as a dependency.
- The add-on now includes `includes/class-wp-loupe-admin-loader.php`, plus separate admin search UI and admin REST search classes.
- The request-scoped loader is in place: REST routes register on REST requests, while admin UI stays on admin requests.
- Dashboard search and modal search are working.
- The admin search UX now includes pagination, modal focus management, and reduced layout shift during paging.
- Admin search now supports multiple scopes: indexed content, users, and installed plugins.
- Native plugin search and user search are intercepted and routed into the enhanced admin search flow.
- `wp-loupe` still contains the admin settings page, admin assets, and several admin-only REST endpoints.
- Front-end query interception already lives separately in `WP_Loupe_Search_Hooks`, which makes the split cleaner.

## Scope Decision

- `wp-loupe-admin` is only for admin search.
- The main WP Loupe settings page, reindex UI, and settings-oriented admin REST routes stay in `wp-loupe`.
- Only settings that are local to admin search belong in this plugin.

## Target Split

### Keep in `wp-loupe`

- Search engine and schema/index management internals.
- Front-end search interception and result hydration.
- Public REST search endpoint(s).
- MCP functionality and token/runtime services.
- Shared domain classes used by both plugins.

### Move to `wp-loupe-admin`

- Admin search UI surfaces such as the dashboard widget, modal launcher, and related UX.
- Admin-search-specific assets and browser behavior.
- Admin-search-specific REST endpoints.
- Optional admin-search-specific settings if they are local to this plugin.

## Proposed Implementation

### Phase 1: Stabilize the add-on bootstrap

- Keep the loader request-scoped and lightweight.
- Fail gracefully when `wp-loupe` is inactive or its required classes are unavailable.
- Avoid loading admin UI code on front-end requests.

### Phase 2: Expand admin search UI

- Keep the dashboard search and modal search in this plugin.
- Keep pagination, scope switching, and native admin-search interception in this plugin.
- Add admin-search-specific controls only if they do not overlap with core WP Loupe settings.
- Avoid taking ownership of the main WP Loupe settings page.

### Phase 3: Keep assets and browser behavior local

- Keep `admin-search.css` and `admin-search.js` in this plugin and evolve them independently.
- Do not migrate the main WP Loupe settings assets unless they become admin-search-specific.
- Keep the browser-facing JS API stable unless there is a clear reason to change it.
- Keep source and built admin-search assets in sync with the local build pipeline.

### Phase 4: Split REST responsibilities

- Leave public search routes in `wp-loupe`.
- Keep core WP Loupe admin/settings routes in `wp-loupe`.
- Only add or move admin-search-specific routes into `wp-loupe-admin`.
- Ensure every admin route keeps `manage_options` or an equivalent capability check.

### Phase 5: Expand natural admin search surfaces

- Cover the most natural WordPress admin search entry points with the enhanced search UI.
- Keep plugin search and user search integrated with the add-on experience.
- Review whether there are other high-value admin search surfaces worth intercepting without creating overlap or confusion.

### Phase 6: Test the split

- Verify both plugins activate cleanly in this order:
  1. `wp-loupe`
  2. `wp-loupe-admin`
- Verify dependency notice behavior when `wp-loupe-admin` is active without `wp-loupe`.
- Verify dashboard and modal search continue to work across all supported scopes.
- Verify native plugin/user search interception still works after asset or REST changes.
- Verify front-end search still works when `wp-loupe-admin` is deactivated.
- Keep PHP and JS test coverage aligned with new admin-search behavior.

## Candidate Source Areas

These are the main places to reference for search-related integration in `wp-loupe`:

- Search engine and shared services used by admin search.
- Public and shared REST behavior that admin search may call into.
- Loader wiring only when needed to understand shared dependencies.

## Design Constraints

- Do not move or redefine the main WP Loupe settings page in this plugin.
- Do not change saved option keys unless a migration is added.
- Do not break existing REST consumers of the public search endpoint.
- Keep admin code out of front-end requests.
- Avoid circular bootstrapping between the two plugins.
- Prefer incremental extraction over a large rewrite.

## Risks

- Some REST methods may be used by both admin UI and public flows, so the split must be method-level, not file-level.
- Script handles like `wp-loupe-admin` already exist in the main plugin, so handle naming must be reviewed during extraction.
- Search interception can become confusing if too many native admin search boxes are overridden without matching user expectations.

## Next Safe Deliverable

The next safe milestone is:

1. Keep the request-scoped loader and multi-scope admin search stable.
2. Continue improving dashboard and modal admin search UX.
3. Expand or refine only the most natural admin search entry points.
4. Add only admin-search-specific settings if they are needed.
5. Leave the main WP Loupe settings and reindex/settings UI in `wp-loupe`.

This reduces risk and gives a working add-on early.

---

## Admin Index Schema

`wp-loupe-admin` maintains its own search indexes, separate from the main `wp-loupe` plugin. Indexes are stored at `{wp_loupe_db_path}/admin/{post_type}/` and include all admin-relevant post statuses: publish, draft, pending, private, future.

The schema is defined in `WP_Loupe_Admin_Schema` and is filterable via the `wp_loupe_admin_schema` hook.

### Post Types

Every indexed post type gets these core fields:

| Field | Searchable | Filterable | Sortable | Weight | Source |
|---|---|---|---|---|---|
| `post_title` | yes | yes | yes | 3.0 | `WP_Post->post_title` |
| `post_content` | yes | no | no | 1.0 | `WP_Post->post_content` (HTML stripped) |
| `post_excerpt` | yes | no | no | 1.5 | `WP_Post->post_excerpt` (HTML stripped) |
| `post_name` | yes | no | no | 1.0 | `WP_Post->post_name` |
| `post_status` | no | yes | no | — | `WP_Post->post_status` |
| `post_date` | no | yes | yes | — | `WP_Post->post_date` |
| `author_name` | yes | yes | yes | 2.0 | Resolved from `get_userdata( post_author )->display_name` |

Plus dynamic taxonomy fields for each `show_ui` taxonomy attached to the post type:

| Field | Searchable | Filterable | Sortable | Weight | Source |
|---|---|---|---|---|---|
| `taxonomy_{name}` | yes | yes | no | 1.5 | `wp_get_post_terms()` names |

For the built-in `post` type, this adds `taxonomy_category` and `taxonomy_post_tag`. Other post types get their registered taxonomies automatically.

Any field not matched to a `WP_Post` property, virtual field, or taxonomy falls back to `get_post_meta()`.

### Future Entity Types (Not Yet Indexed)

These schemas are defined but not yet wired into the indexer.

#### Users

| Field | Searchable | Filterable | Sortable | Weight |
|---|---|---|---|---|
| `display_name` | yes | yes | yes | 3.0 |
| `user_login` | yes | yes | no | 2.0 |
| `user_email` | yes | yes | no | 2.0 |
| `user_role` | no | yes | no | — |

#### Comments

| Field | Searchable | Filterable | Sortable | Weight |
|---|---|---|---|---|
| `comment_content` | yes | no | no | 1.0 |
| `comment_author` | yes | yes | yes | 2.0 |
| `comment_author_email` | yes | yes | no | 1.0 |
| `comment_date` | no | yes | yes | — |

#### Plugins

| Field | Searchable | Filterable | Sortable | Weight |
|---|---|---|---|---|
| `plugin_name` | yes | yes | yes | 3.0 |
| `plugin_description` | yes | no | no | 1.0 |
| `plugin_author` | yes | yes | no | 2.0 |
| `plugin_status` | no | yes | no | — |

### Extensibility

Filter `wp_loupe_admin_schema` to add, remove, or modify fields for any entity type:

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

---

## Checklist

### Infrastructure

- [x] Bootstrap loader (singleton, request-scoped, `plugins_loaded` priority 20)
- [x] Dependency check for `wp-loupe` active
- [x] Admin-only code behind `is_admin()`

### Admin Indexes

- [x] `WP_Loupe_Admin_Schema` — own field definitions per entity type
- [x] `wp_loupe_admin_schema` filter for extensibility
- [x] `WP_Loupe_Admin_Indexer` — separate indexes at `wp-loupe-db/admin/{post_type}/`
- [x] Index all admin statuses (publish, draft, pending, private, future)
- [x] `author_name` virtual field resolved from `get_userdata()`
- [x] Dynamic taxonomy fields from `get_object_taxonomies()`
- [x] Post meta fallback for custom schema fields
- [x] Auto-rebuild on first admin load via `needs_initial_index()`
- [x] Hooks: `save_post_{type}`, `wp_trash_post` for incremental updates

### Native Search Interception

- [x] `WP_Loupe_Admin_Query_Integration` — `posts_pre_query` filter
- [x] Intercept `edit.php` search with Loupe results in `WP_List_Table`
- [x] Pagination via `found_posts` / `max_num_pages`
- [x] Re-entrancy guard (`$is_handling_posts_query`)

### REST API

- [x] `wp-loupe-admin/v1/search` endpoint with scope parameter
- [x] Content scope wired to admin indexer (not main `WP_Loupe_Search_Engine`)
- [x] Users scope via `WP_User_Query`
- [x] Plugins scope via `get_plugins()` substring match
- [x] Capability checks per scope (`edit_posts`, `list_users`, `activate_plugins`)

### Admin UI

- [x] Dashboard widget
- [x] Admin bar launcher
- [x] Modal search with scope switcher (content / users / plugins)
- [x] Pagination and focus management

### Testing

- [x] AdminSchemaTest (7 tests — all entity types + filter)
- [x] AdminQueryIntegrationTest (2 tests — hook registration + short-circuit)
- [x] RestControllerTest (5 tests — routes, permissions, content, empty query)
- [x] AdminSearchTest (2 tests — dashboard widget, asset localization)
- [x] JS tests (3 tests — form submit, modal, no native interception)

### Documentation

- [x] `plan.md` — architecture, schema tables, extensibility
- [x] `README.md` — user-facing: how it works, indexed fields, filter example

### Future Entity Indexing

- [ ] Index users — schema defined, needs user indexer + `pre_get_users` hook
- [ ] Index comments — schema defined, needs comment indexer + `edit-comments.php` hook
- [ ] Index plugins — schema defined, needs plugin indexer (non-DB source)
- [ ] Wire future entity indexes into REST scopes (replace `WP_User_Query` / `get_plugins()`)

### Other

- [x] Uninstall cleanup — delete `wp-loupe-db/admin/` on plugin uninstall
- [ ] Admin notice when indexes are stale or schema changes detected
- [x] WP-CLI command for manual reindex (`wp loupe-admin reindex` + `wp loupe-admin status`)