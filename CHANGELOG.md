## 2026-07-20

- Added a provider-neutral `_proud_html_preview` endpoint for durable Document and Meeting HTML previews. It validates provider state, preview token, current source identity, artifact paths, and exact trusted WordPress uploads/GCS base URLs; restores missing local artifacts from trusted storage; serves HTML with restrictive security headers; and falls back to the original document when an artifact is unavailable. A verified cleanup queue retries remote non-media deletion after provider uninstall. Conversion-provider code is not required on public requests.

References: https://github.com/FileToWeb/filetoweb-integration/issues/12

## 2026-07-15

- Fixed fatal error when opening the SiteOrigin Live Page Editor (`?so_live_editor=1`) on any page. `ProudWidget::update()` called `$this->form->updateGroupsWeight()`, but `$this->form` is only assigned inside `attachAdminForm()`, which is gated by `is_admin()`. The Live Page Editor renders through the front-end template stack where `is_admin()` is false; SiteOrigin's `generate_post_content` filter (hooked to `the_content`) calls `process_raw_widgets()` → `ProudWidget->update()` during that render, so `$this->form` was null and caused the fatal. A recent SiteOrigin hardening change made `update()` always run when the method exists (previously the client `raw` flag could skip it), which newly exposed this null-form path. Fix: lazily instantiate `FormHelper` inside `update()` when `$this->form` is not yet set, so the method no longer depends on `attachAdminForm()` having been called. `FormHelper::updateGroupsWeight()` is argument-only; the constructor's only side effect (admin library registration) is inert on front-end requests.

References: https://github.com/proudcity/wp-proudcity/issues/2865

## 2026-07-09

- Fixed authenticated path traversal / SSRF in `gf_hijack_download_export()` (`plugin_override/gravityforms/proud-gravityforms.php`). `rgget('export-id')` was passed only through `esc_attr()` (HTML escaping, not path validation) before being interpolated into a `storage.googleapis.com` URL and handed to `readfile()`, allowing an editor-or-above user to read arbitrary GCS bucket objects or trigger SSRF via traversal sequences or stream wrappers. Fix: added `Proud\Gform\proud_gform_valid_export_id()` (allowlist regex `^[A-Za-z0-9_-]+$`), called immediately after the capability check, exits with `error_log` on rejection. Also replaced `esc_attr($name)` with `rawurlencode($name)` for the db-name URL segment. The handler remains gated by `check_ajax_referer('gform_download_export')` + `current_user_can('edit_posts')`; legitimate export IDs are unaffected. Added 14 PHPUnit cases covering valid IDs, traversal sequences, scheme injection, null bytes, and array injection; suite 63/63 passing.

References: https://github.com/proudcity/wp-proudcity/issues/2859

## 2026-07-09

- Fixed PHP warning "Trying to access array offset on false" in `plugin_override/gravityforms/proud-gravityforms.php` firing on every `init` request when the WP-Stateless gravity-form module is not registered. Extracted a `Proud\Gform\proud_gform_stateless_active()` helper (defined outside the `GFCommon` class-exists guard so it is directly testable) that null-coalesces `$module['enabled'] ?? false` before passing to `filter_var()`; replaced the inline try/catch expression in `proud_gravityforms_init()` with a single call to the helper. Behavior unchanged: module absent or disabled keeps ProudCity's own download-gating path active. Added 5 PHPUnit cases in `tests/GravityFormsStatelessModuleTest.php` (false/enabled/disabled/missing-key/truthy-string) including an error-handler assertion confirming no PHP warning is emitted; new stubs in `tests/gravityforms-stubs.php`; `tests/bootstrap.php` updated to load both. Suite: 49/49 passing (was 44).

References: https://github.com/proudcity/wp-proudcity/issues/2857

## 2026-06-10

- Added two action hooks for plugins that need to render UI alongside the file/image upload buttons in `ProudMetaBox` metaboxes: `proud_form_after_file_upload` and `proud_form_after_image_upload`, both passed `($media_id, $url, $field)`. To make `$field` reachable inside the templates, `FormHelper::printFileUpload()` and `printImageUpload()` gained an optional 4th `$field = []` argument (back-compat preserved for any caller that omits it). Driven by the FileToWeb rollout, which needs to inject a "publish to FileToWeb" control next to Meeting Agenda/Minutes upload buttons without forking metabox classes. The hook signature deviates from the original issue text (`$name, $field`) — `$name` is reachable as `$field['#name']`, and passing `$media_id` + `$url` lets callbacks check upload state without a second query. Callbacks must scope themselves by inspecting `$field['#name']` or post type; the hook fires for every `media`/`select_image`/`select_file` field site-wide.
- Bundled fix for pre-existing unescaped output in `file-upload.php` and `image-upload.php`: wrapped href/src in `esc_url()`, link text from `basename($url)` in `esc_html()`, and the translated button labels (`Change File`/`Select File`/`Change Image`/`Select Image`) in `esc_attr()` since they render inside HTML attributes. Closes a low-severity finding from the `wp-proud-core` security audit.
- Added PHPUnit tests in `tests/UploadTemplatesTest.php`: 6 tests / 16 assertions covering attribute-breakout payloads for href and src, script-tag payloads in link text, the empty-URL no-link path, and hook firing with the expected `$media_id, $url, $field` args for both templates. Full suite: 37 tests / 68 assertions passing.

References: https://github.com/proudcity/wp-proudcity/issues/2835

## 2026-05-27

- Added inline search + preview picker to the Embed Document widget. Replaces the paste-an-admin-URL text field with a debounced search input, a results list (icon + filename), a hidden `post_id` field, and a preview pane. Two admin-only AJAX endpoints back the picker (`proud_document_search`, `proud_document_preview`) — both nonce- and `edit_posts`-gated, no `nopriv` registration. Preview reuses the existing `templates/content-embed-document.php` via `ob_start()` so the admin preview matches the frontend embed exactly. Back-compat preserved: legacy widgets storing a pasted edit URL continue to render; JS rewrites them to a clean numeric ID on next save. Hardened pre-existing unescaped outputs in the embed template (`$src`, `$filename` now use `esc_url`/`esc_attr`/`esc_html`). JS result rendering uses `.text()` + jQuery factory calls instead of `.html()` + string concatenation. Asset enqueue scoped to `widgets.php`, `customize.php`, `post.php`, and `post-new.php` (covers SiteOrigin Page Builder, where widgets render inside the post edit screen). JS rebinds on `widget-added`, `widget-updated`, and SiteOrigin's `panelsopen`/`panelsdone` events. PHPUnit tests added: `DocumentWidgetSearchTest.php`, `DocumentWidgetPreviewTest.php` — 8 new tests, 31/31 suite passing.

References: https://github.com/proudcity/wp-proudcity/issues/2744

## 2026-05-01

- Fixed critical error when attaching a file to a Documents page on sites that do not run wp-stateless. `getStatelessFileMeta()` in `proud-helpers.php` called `\ud_get_stateless_media()` unconditionally, which fataled when the wp-stateless plugin was not active. Added a `function_exists('ud_get_stateless_media')` guard that returns `null` early so callers fall back to the standard attachment URL path.

References: https://github.com/proudcity/saintra/issues/45

- Fixed swapped "Older" / "Newer" pagination labels on the /news/ archive. Posts are sorted DESC by date, so the prev URL (page - 1) moves toward newer posts and the next URL (page + 1) moves toward older posts. The `case 'post':` branch in `TeaserList::print_pagination()` had the labels reversed; swapped them so `$prev_text` reads "« Newer" and `$next_text` reads "Older »". Removed the stale comment that flagged the labels as potentially needing a swap.

References: https://github.com/proudcity/wp-proudcity/issues/2817

## 2026-04-21

- Fixed "Don't display image on individual page" checkbox disappearing after setting a featured image, causing it to never be submitted on first publish. Root cause: `hide_featured_image()` relied on `global $post`, which is null inside the WordPress AJAX handler that refreshes the featured image meta box after image selection. Fixed by receiving `$post_id` via the filter's second argument and calling `get_post($post_id)` instead. Also fixed `wp_nonce_field()` echoing directly into the AJAX response (corrupting it) by passing `false` as the 4th argument; added autosave/revision guards and nonce verification to `save_featured_image_meta()`; switched from `$_REQUEST` to `$_POST`; fixed checkbox `value` attribute to always be `"1"` instead of the current meta value
- Added PHPUnit test suite for `proud-layout.php`: 11 tests covering checkbox rendering, AJAX context, nonce output, save guards, and value handling

References: https://github.com/proudcity/wp-proudcity/issues/2804

## 2026-04-17

- Added PHPUnit 11 test suite with Brain\Monkey for WP function mocking; covers breadcrumb edge cases (non-sequential `menu_order`, duplicate menu items, empty trail) and navbar `build_logo_meta()` metadata fallback
- Fixed secondary undefined array key warning on `$image_meta['meta']['height']` when attachment metadata is absent; guarded with `?? 0`

## 2026-04-17

- Fixed PHP 8.1 deprecation notice in `build_logo_meta()`: `wp_get_attachment_metadata()` returns `false` for attachments with no registered metadata (SVGs, pre-sized uploads); added `is_array()` guard in `build_retina_image_meta()` to normalize the `meta` key to an empty array before writing into it, preventing automatic `false`-to-array conversion

References: https://github.com/proudcity/wp-proudcity/issues/2807

## 2026-04-17

- Fixed fatal `TypeError` in `ProudBreadcrumb::build_breadcrumb()` caused by non-sequential `menu_order` leaving trail slots as empty strings; removed fragile early `break`, added `array_filter` to drop unfilled slots, and used `array_key_last()` to mark the active item
- Fixed second fatal `TypeError` in `build_breadcrumb()` when `reset($active_trail)` returned `false` on an empty trail after filtering; added `is_array($firstItem)` guard before accessing `['post_id']`
- Fixed duplicate active-branch bug in `ProudMenuUtil::get_nested_menu()` when the same post appears more than once in a menu; added `$found_active` flag so only the first occurrence is marked active

References: https://github.com/proudcity/wp-proudcity/issues/2806

## 2026-04-16

### Feature: Mobile menu moved to header region (issue #2757)

On mobile (< 911px), the hamburger button and action toolbar (`.menu-box`) now appear beside the logo in `.navbar-header-region` instead of being pinned to the bottom of the viewport.

Changes in `navbar.php`:
- Added `.header-region-menu-box` div inside `.navbar-header-region` containing a new `#header-menu-button` (hamburger with "Menu" label below it) and a copy of `.menu-box`
- Added `#menu-close-button` (× button) inside `#navbar-external`, shown fixed at top-right when the menu is open

Changes in `proud-navbar.js`:
- Added click handler for `#header-menu-button` to toggle the menu
- Added click handler for `#menu-close-button` to close the menu

References: https://github.com/proudcity/wp-proudcity/issues/2757

## 2026-04-15

### Fix: Menu nesting lost after adding a page via Quick Menu

The `get_nested_menu` algorithm used a depth-stack that assumed menu items were returned in strict depth-first `menu_order` sequence. Items added via Quick Menu received `menu_order = count($menu_items)`, which could collide with existing items and cause MySQL to return some child items after a sibling branch had already been processed. When that happened, those children's parents were no longer on the stack and they silently dropped to root level.

Replaced the stack-based algorithm with a `parent_id → children` lookup map that recursively builds the tree using explicit parent IDs, so nesting is always correct regardless of `menu_order` values.

Also fixed the `active_trail` insertion order: the recursive approach built the trail leaf→root, but `build_breadcrumb` requires root→leaf (it stops when `end($active_trail)` is non-empty, which must be the active item). Added `array_reverse` after the build to correct this — without it the breadcrumb crashed with "Cannot access offset of type string on string".

**Files changed:**
- `modules/proud-menu/proud-menu.php`

**Changes:**
- `get_nested_menu()`: replaced depth-stack loop with `$children_of` map + recursive `$build` closure
- `get_nested_menu()`: added `array_reverse( $active_trail, true )` after build to restore root→leaf order
- Removed dead `insert_deep()` and `attach_link()` methods (no longer called)
- Removed unused `global $proud_menu_util` from `get_nested_menu()`
- `build_recursive()`: removed unused `$key =>` from foreach
- `proud_menu_fix()`: renamed `$menu_id` → `$_menu_id` (required by hook, intentionally unused)

References: https://github.com/proudcity/wp-proudcity/issues/2776

---

## 2026-04-14

### Fix box-shadow and hover state on action buttons
References: https://github.com/proudcity/wp-proudcity/issues/2753

- `modules/proud-widget/widgets/cta-widget/cta-button-widget.class.php` — removed box-shadow from `.card.card-btn.card-btn-action`; added matching border color to `.card.card-btn.action` so the card edge doesn't flash on hover; added `:focus` and `:hover` rules that switch the background to white while preserving the text color

## 2026-03-30

### Jumbotron header class switch for full vs random image headers
References: https://github.com/proudcity/wp-proudcity/issues/2779

- `modules/widgets/jumbotron-header/templates/jumbotron-full.php` — added file label and class switch for full header
- `modules/widgets/jumbotron-header/templates/jumbotron-header.php` — added class switch logic
- `modules/widgets/jumbotron-header/templates/jumbotron-simple.php` — added class switch logic
- `modules/widgets/jumbotron-header/templates/jumbotron-slideshow.php` — added class switch logic

## 2026-03-27

### Deregister wordpress-faq-manager widgets
References: https://github.com/proudcity/wp-proudcity/issues/2777

- `plugin_override/wordpress-faq-manager/proud-wordpress-faq-manager.php` — new file; deregisters all five widgets provided by the wordpress-faq-manager plugin (`Search_FAQ_Widget`, `Random_FAQ_Widget`, `Recent_FAQ_Widget`, `Topics_FAQ_Widget`, `Cloud_FAQ_Widget`) at `widgets_init` priority 20 so they are unavailable on the ProudCity platform without modifying the upstream plugin
- `wp-proud-core.php` — added `require_once` for the new faq-manager override file

## 2026-03-25

### Topic subpage sidebar/breadcrumb support
References: https://github.com/proudcity/wp-proudcity/issues/2665

- `wp-proud-core.php` — fixed `getPageInfo()` to use `get_post_type()` instead of hardcoding `'agency'`; added fallback lookup for `proud-topic` posts whose slug matches the menu slug; extended `is_page()` gate to `is_singular('proud-topic')` so `$pageInfo` is populated when viewing a proud-topic CPT
- `modules/proud-layout/proud-layout.php` — added `'proud-topic'` case to `page_parent_info()` so topic subpages trigger the topic sidebar and breadcrumb; added dedicated `is_singular('proud-topic')` branch so `page_parent_info('proud-topic')` returns true when viewing a proud-topic directly (bypasses `is_page()` gate)
- `modules/proud-menu/proud-menu.php` — fixed `build_breadcrumb()` to treat `proud-topic` like `agency`: prepend the proud-topic to the active trail instead of falling through to the `else` branch which was overwriting `$pageInfo['parent_post_type']` from `proud-topic` to `page`, causing `page_parent_info('proud-topic')` to always return false
