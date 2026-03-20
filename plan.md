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