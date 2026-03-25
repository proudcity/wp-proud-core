## 2026-03-25

### Topic subpage sidebar/breadcrumb support
References: https://github.com/proudcity/wp-proudcity/issues/2665

- `wp-proud-core.php` — fixed `getPageInfo()` to use `get_post_type()` instead of hardcoding `'agency'`; added fallback lookup for `proud-topic` posts whose slug matches the menu slug
- `modules/proud-layout/proud-layout.php` — added `'proud-topic'` case to `page_parent_info()` so topic subpages trigger the topic sidebar and breadcrumb
