## 2026.08.28.1112

- Fixed every Gravity Form containing a file upload field fataling on submission on hosts without WP-Stateless. Reported for citydesk.santa-ana.gov, a DigitalOcean site rather than k8s: `Fatal error: Uncaught Error: Call to undefined function Proud\Gform\ud_get_stateless_media() in plugin_override/gravityforms/proud-gravityforms.php:298`, thrown from `gform_secure_file_download_url()` during `GFCommon::send_notification()` → `replace_variables()` → `GF_Field_FileUpload->get_download_url()`, so entries saved but the submitter got a WordPress error page; removing the upload field made the form work. Root cause: `proud_gravityforms_init()` gated our legacy Google Cloud Storage bridge solely on `proud_gform_stateless_active()`, which asks only whether WP-Stateless' own `gravity-form` module is enabled. That returns false in two unrelated states — Stateless installed with the module off (the bridge *should* run) and Stateless absent entirely (nothing here *can* run) — so the bridge was registered on hosts with no bucket and no `ud_get_stateless_media()` at all. The sibling `gform_handle_file_upload()` had carried a `function_exists()` guard since 2026-07-29; `gform_secure_file_download_url()` never got one. Fix: added `proud_gform_stateless_available()`, a wider gate that checks `function_exists('ud_get_stateless_media')` and that `sm.bucket` is a non-empty string, catching `\Throwable` (not `\Exception`) so a half-initialised Stateless raising a `TypeError` also degrades to the local path. Every cloud-dependent hook now sits behind it — `gform_post_export_entries` → `sync_entry_export_file`, the export hijack, `GC_GF_Download::maybe_process()`, `gform_secure_file_download_url`, and `gform_save_field_value` → `gform_handle_file_upload` — with the existing `proud_gform_stateless_active()` check left nested inside so the relative ordering of export versus upload hooks is unchanged. The bucket check is deliberate rather than defensive padding: Stateless installed but unconfigured would otherwise build `https://storage.googleapis.com//…` download URLs instead of falling through cleanly to Gravity Forms' native local handling. The same gating fixes a second, silent bug on those hosts — `remove_all_filters('wp_ajax_gf_download_export', 10)` ran unconditionally and stripped GF's own export handler (registered at the default priority 10, `gravityforms/gravityforms.php:644`) in favour of ours, which `readfile()`s a hardcoded `https://storage.googleapis.com/proudcity/…` URL that does not exist off k8s, so entry CSV export downloaded garbage with no error. `gform_secure_file_download_url()` also gained the same defensive early return `gform_handle_file_upload()` already had, so it cannot fatal if anything else attaches it; `gform_handle_file_upload()`'s bare `function_exists()` check was upgraded to the shared helper. Added 9 PHPUnit cases in `tests/GravityFormsStatelessAvailableTest.php` (plugin absent, missing/empty/whitespace/non-string bucket, `get()` throwing an `Exception` and an `Error`, the configured happy path, and a lock asserting the two helpers stay independent), with a stageable `ud_get_stateless_media()` stub in `tests/gravityforms-stubs.php` that throws the same `Error` PHP raises for an undefined function — PHP cannot undefine a function once declared, so the absent-plugin case has to be modelled rather than arranged. Suite 114/114 passing. Ruled out as a cause: upload directory permissions on the box, where PHP-FPM runs as `saintraproudcitycom`, the owner of `wp-content/uploads/gravity_forms`.

References: https://github.com/proudcity/saintra/issues/55

## 2026.08.25.1338

- Stopped Events Manager compounding every prior occurrence date into recurring event URLs, and repaired the slugs it had already written. Reported for williamsnd.com, where a twelve-occurrence monthly meeting produced `/event/vector-control-board-meeting-5-2026-01-08-2026-02-12-…-2026-08-13/`. Root cause is upstream in EM 7.4.2 (current wordpress.org release, unfixed): `Recurrence_Set::save_recurrences()` loads the recurring template's post row once before the occurrence loop (`classes/recurrences/recurrence-set.php:983`), then assigns each occurrence's date-suffixed slug back into that same `$post_fields` array from inside the loop (`:1017`). The array is never reset per iteration, so iteration N reads what N-1 wrote and appends another date; `update_recurrence()` repeats the pattern at `:1341`. EM's own `sanitize_recurrence_slug()` (`:1678`) does not catch it — it only truncates past 200 characters, capping the runaway rather than preventing it, which additionally risks silent slug truncation and collisions on long titles since EM inserts occurrence posts with a raw `$wpdb->insert()` and never calls `wp_unique_post_slug()`. Fix (`modules/events-manager-recurrence-slug.php`): `proudcity_em_strip_accumulated_recurrence_dates()` hooks `em_event_save_events_slug`, the last filter on both the create and update paths, and rebuilds the slug as the recurring template's own `post_name` plus the single trailing date, reading the template via `get_post( $EM_Event->post_id )` rather than `$EM_Event->post_name` (only populated during the publish flow, `em-event.php:1898`) or the polluted `$post_fields`. Deliberately conservative: the pattern is anchored to the template slug and a `Y-m-d` trailing date, so a site filtering `em_event_save_events_format` falls through to upstream behaviour instead of getting a mangled slug, and an already-correct slug matches with zero repeated groups and returns unchanged — making the module a no-op once upstream fixes this. Added `wp proud fix-em-slugs` (`bin/wp-cli.php`, `ProudCore_CLI`) to repair existing damage: it rewrites `wp_posts.post_name` and `wp_em_events.event_slug` in place, leaving post IDs, meta, comments and bookings untouched, files a 301 in the Safe Redirect Manager `redirect_rule` CPT for every changed URL so editors can see and adjust them, and writes `_wp_old_slug` as a fallback. Guards: `--dry-run` previews, a bare run prompts via `WP_CLI::confirm()`, `--yes` skips the prompt for unattended fleet runs; an existing redirect whose notes are not ours is never clobbered; `srm_max_redirects_reached()` is checked before each create; and it is idempotent. Critically, candidates are selected on `post_name` alone (`REGEXP '(-[0-9]{4}-[0-9]{2}-[0-9]{2}){2,}$'`) rather than by joining through `wp_em_event_recurrences` — editing a single occurrence in wp-admin detaches it (`recurrence_set_id` NULL, `event_type` `single`) while leaving the accumulated slug, so a recurrence-set join silently omits those rows; the first production run missed 17 of them that way. For detached rows the base is recovered by stripping trailing dates and only trusted when a recurring template still carries it or more than one event post was built from it, which is what rescues series whose template has since been deleted. Redirect creation is skipped for non-`publish` posts, whose `get_permalink()` returns a `?p=` query string. 17 PHPUnit cases in `tests/EventsManagerRecurrenceSlugTest.php` (accumulation, idempotency, partial-prefix rejection, regex metacharacters, non-`Y-m-d` suffixes, `\z` anchoring, short argument lists, null/empty template `post_name`, the documented long-slug fallthrough); suite 105/105 passing. Security review passed with no Critical, High or Medium findings; three Low findings were folded in (`\z` rather than `$` so a trailing newline cannot slip the anchor, default parameter values so a shim re-firing this deprecated hook with a shorter signature cannot throw `ArgumentCountError`, and `is_string()` on `post_name` so a null cannot reach `preg_quote()`). Verified end-to-end on williamscountynd: 150 occurrences repaired across two runs, 149 redirects created, 0 accumulated slugs remaining, 0 `post_name`/`event_slug` drift, and the originally reported URL now 301s to `/event/vector-control-board-meeting-5-2026-08-13/`. Reported upstream at https://wordpress.org/support/topic/recurring-event-slugs-accumulate-every-previous-occurrence-date/.

References: https://github.com/proudcity/wp-proudcity/issues/2893

## 2026.08.19.1042

- Disabled WP Media Folder's "Upload folder" bulk uploader, which was silently destroying uploaded documents on Stateless sites. WPMF 6.2.6 serves that button from its own chunked pipeline (`wp_ajax_wpmf_upload_folder` → `WpmfMediaFolder::uploadFolder()`, `class/class-main.php:147`) instead of `wp_handle_upload()`, and it gates metadata generation on media type (`class-main.php:3769-3781`) so `wp_update_attachment_metadata()` is only called for image, video and audio. WordPress core calls it for every attachment type (`wp-admin/includes/media.php:446`) and WP Stateless offloads solely on that filter (`wp-stateless/lib/classes/class-bootstrap.php:379`), so PDFs and Office documents uploaded this way never reached the bucket, lived only on the pod's local disk, and were destroyed on the next container restart. The same path also writes `$_FILES['name']` verbatim (`class-main.php:3910`) with no `sanitize_file_name()` and no `wp_unique_filename()`, so names kept raw spaces, skipped our `stateless_skip_cache_busting` suffix, and a repeated upload overwrote the first file while creating a second attachment row pointing at it. Duchesne County lost 338 meeting documents to this on 2026-08-18; the files were readable long enough for FileToWeb to convert all 150 of them, then 404'd after the pod recycled. Fix (`plugin_override/wp-media-folder/proud-wp-media-folder.php`): `proudcity_wpmf_block_folder_upload()` refuses the AJAX action with a 403 in WPMF's own `status => false` response shape and logs the refusal server-side, registered at priority 1 to beat WPMF's 10 and additionally seized on `admin_init` at `PHP_INT_MAX` via `remove_all_actions()` + re-add so a future WPMF release that changes its own priority cannot silently restore the bug (admin-ajax.php fires `admin_init` at line 45, long before it dispatches at line 192; re-adding matters because core `wp_die()`s with a 400 when `has_action()` is false, line 180). `proudcity_wpmf_hide_folder_upload_button()` hides the button on `admin_head`, since WPMF injects it from JavaScript (`assets/js/script.js:184-190`) with no server-side markup to filter and no setting to switch it off. The refusal is logged because WPMF discards it client-side — its `fileError` handler is an empty function (`script.js:1668`) and its `complete` handler shows a success snackbar regardless (`script.js:1698`). The standard "Add New" uploader is untouched and remains the supported path; the uploader has exactly one registration and no `nopriv` variant, so nothing else reaches it. Added 6 PHPUnit cases in `tests/WpMediaFolderFolderUploadTest.php` (response shape, request termination, server-side logging, both hook registrations, the `admin_init` seize order, CSS output); suite 94/94 passing. Mutation-checked: deleting the registration and swapping `wp_send_json()` for a non-terminating `echo` each fail the suite. Security review passed with no Critical, High or Medium findings.

References: https://github.com/proudcity/wp-proudcity/issues/2887

## 2026-07-29

- Fixed hamburger misalignment on mobile-only-toolbar sites (`proud-navbar-topbar-mobile-only-active`, e.g. Wendell, Carnation) when the WordPress admin bar is present. `positionMenuButton()` in `proud-navbar.js` measures `.navbar-header-region` with `getBoundingClientRect()` to self-correct for the real admin-bar + top-bar stack, but its early-return gate only passed for `proud-navbar-topbar-active` (full topbar), so mobile-only sites fell back to a static CSS offset that did not track the real rendered stack. Broadened the gate to also allow `proud-navbar-topbar-mobile-only-active`; the existing `display === 'none'` guard already prevents any effect at desktop.

References: https://github.com/proudcity/wp-proudcity/issues/2757

## 2026-07-29 (2026.07.29.0928)

- Corrected the 2026-07-28 fix for the Gravity Forms → Google Cloud Storage upload collision (#2876), which did **not** resolve the issue in production. The earlier fix only covered our direct `gform_get_gcloud_file()` path; production and beta sites run the **WP-Stateless Gravity Forms add-on**, and when it is active `proud_gravityforms_init()` early-returns, so that path (and its `$GLOBALS['proudcity_gform_upload_context']` flag / `doing_filter('gform_save_field_value')` signal) never runs. The add-on does not call our cache-buster on upload — it syncs the name Gravity Forms already wrote to disk — so no unique suffix was minted and the collision reproduced on beta. The real chokepoint is `GFFormsModel::get_file_upload_path()` (`gravityforms/forms_model.php:6185`), where GF names every on-disk upload via `sanitize_file_name()`; our override (`proudcity_stateless_suffix_cache_bust()`, reached via `randomize_filename` on the `sanitize_file_name` filter) runs there whenever `sm.hashify_file_name` is on — which WP-Stateless auto-forces true in `stateless`/`ephemeral` mode. At that point a `c264795b-`prefixed re-upload hit the `^[a-f0-9]{8}-` idempotency guard and was returned unchanged, and GF's only other uniqueness (`file_exists()` de-dup, line 6198) fails in `stateless`/`ephemeral` mode because the local file is already gone — so identical uploads shared one GCS object. Fix: added `proudcity_stateless_gform_naming_upload()` (`plugin_override/wp-stateless/proud-wp-stateless.php`) which scans the call stack via `debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)` for a `GFFormsModel::get_file_upload_path` frame (with a `GFExport` carve-out so entry exports are untouched) and OR's that into `proudcity_stateless_is_gform_upload()`. In that context both idempotency guards are bypassed and a fresh suffix is appended (`c264795b-employmentappliation-XXXXXXXX.pdf`), so every GF upload gets a unique on-disk name — and therefore a unique GCS object — for **both** stateless integrations and **all** modes. Media-library behavior is unchanged. The prior `$GLOBALS` flag and `doing_filter` signals are kept (still correct for the direct path). Added 5 PHPUnit cases in `tests/StatelessGformCacheBustTest.php` (backtrace detection true/false, GFExport carve-out, injected-frames seam since `debug_backtrace` cannot be stubbed, media-library regression lock); full suite 88/88 passing. Verified locally against the add-on path: two identical `c264795b-employmentappliation.pdf` submissions produced distinct names (`-35c94dc4`, `-b0e27ecb`) while a non-GF `sanitize_file_name()` of the same name was returned unchanged.

References: https://github.com/proudcity/wp-proudcity/issues/2876

## 2026-07-28

- Fixed Gravity Forms file uploads colliding onto a single Google Cloud Storage object, which caused multiple form entries to reference one applicant's file instead of their own (reported for delawarecounty.in.gov, form "Submit Application for Employment"; 7 entries shared one PDF). Root cause: our cache-buster (`plugin_override/wp-stateless/proud-wp-stateless.php`, hooked to `stateless_skip_cache_busting`) returns any already-hashed filename unchanged for idempotency, and the GF upload path (`plugin_override/gravityforms/proud-gravityforms.php` → `gform_get_gcloud_file()` → `Utility::randomize_filename()`) bypasses core `wp_unique_filename()`. Applicants who downloaded the site-hosted blank form (`c264795b-employmentappliation.pdf`, already prefixed) and re-uploaded it kept that name, so every submission resolved to the same GCS path and collided. Fix: added `proudcity_stateless_is_gform_upload()` and a GF-context branch in `proudcity_stateless_suffix_cache_bust()` that bypasses both the prefix and suffix idempotency guards and always mints a fresh unique suffix (`substr(md5(time() . wp_rand()), 0, 8)`) for GF uploads only; media-library behavior is unchanged. GF context is detected via an explicit `$GLOBALS['proudcity_gform_upload_context']` flag set (try/finally) around the `randomize_filename()` call, plus a `doing_filter('gform_save_field_value')` fallback for the WP-Stateless GF add-on path. Added 12 PHPUnit cases in `tests/StatelessGformCacheBustTest.php` (guard bypass in GF context, two same-second uploads differ, retina placement, `$return` short-circuit, `doing_filter` fallback, non-GF regression locks); full suite 83/83 passing.

References: https://github.com/proudcity/wp-proudcity/issues/2876

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
