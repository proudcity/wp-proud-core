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
