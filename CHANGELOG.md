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
