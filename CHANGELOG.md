## 2026-03-25

### Topic subpage sidebar/breadcrumb support
References: https://github.com/proudcity/wp-proudcity/issues/2665

- `wp-proud-core.php` — fixed `getPageInfo()` to use `get_post_type()` instead of hardcoding `'agency'`; added fallback lookup for `proud-topic` posts whose slug matches the menu slug; extended `is_page()` gate to `is_singular('proud-topic')` so `$pageInfo` is populated when viewing a proud-topic CPT
- `modules/proud-layout/proud-layout.php` — added `'proud-topic'` case to `page_parent_info()` so topic subpages trigger the topic sidebar and breadcrumb; added dedicated `is_singular('proud-topic')` branch so `page_parent_info('proud-topic')` returns true when viewing a proud-topic directly (bypasses `is_page()` gate)
- `modules/proud-menu/proud-menu.php` — fixed `build_breadcrumb()` to treat `proud-topic` like `agency`: prepend the proud-topic to the active trail instead of falling through to the `else` branch which was overwriting `$pageInfo['parent_post_type']` from `proud-topic` to `page`, causing `page_parent_info('proud-topic')` to always return false
