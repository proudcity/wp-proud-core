<?php

namespace Proud\Gform;

function proud_gform_stateless_active() {
    try {
        $module = \wpCloud\StatelessMedia\Module::get_module('gravity-form');
        return filter_var($module['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    } catch (\Throwable $t) {
        return false;
    }
}

/**
 * Whether WP-Stateless is installed and pointed at a bucket, i.e. whether the
 * Google Cloud Storage bridge in this file can run at all.
 *
 * Distinct from proud_gform_stateless_active(), which asks the narrower question
 * of whether WP-Stateless' own gravity-form module is switched on. That helper
 * returns false both when Stateless is present with the module off (our legacy
 * bridge should run) and when Stateless is absent entirely (nothing here can
 * run). Conflating those two states is what fataled the DigitalOcean sites on
 * every file-upload submission — see issue #55.
 *
 * The bucket check matters as much as the function check: Stateless installed
 * but unconfigured would otherwise build https://storage.googleapis.com//... and
 * hand back a broken download instead of falling through to Gravity Forms'
 * native local file handling.
 */
function proud_gform_stateless_available() {
    if (! function_exists('ud_get_stateless_media')) {
        return false;
    }

    try {
        $bucket = \ud_get_stateless_media()->get('sm.bucket');
    } catch (\Throwable $t) {
        // A half-initialised Stateless can raise a TypeError rather than an
        // Exception here, so catch broadly and degrade to the local path.
        return false;
    }

    return is_string($bucket) && trim($bucket) !== '';
}

/**
 * Whether a Gravity Forms export-id is safe to interpolate into the export
 * download URL. The id is concatenated into a storage.googleapis.com path that
 * is handed to readfile(), so it must not carry path-traversal or scheme
 * characters. Allow only the id charset GF actually uses.
 */
function proud_gform_valid_export_id($export_id) {
    return is_string($export_id) && preg_match('/^[A-Za-z0-9_-]+$/', $export_id) === 1;
}

/**
 * Autocomplete tokens (WCAG 2.1 SC 1.3.5, wp-proudcity#3021).
 *
 * Gravity Forms only prints an autocomplete attribute when a field's "Enable
 * Autocomplete" setting is on, and that is off on nearly every form. Keys are
 * GF field types. 'field' is the field-level token (single-input fields),
 * 'inputs' maps the part of an input id after the dot to its token. Every value
 * is a hard-coded constant: Gravity Forms does not escape the attribute on
 * output, so request or form data must never be used to build one.
 */
function proud_gform_autocomplete_tokens() {
    return [
        'name'    => [
            'inputs' => [
                '2' => 'honorific-prefix',
                '3' => 'given-name',
                '4' => 'additional-name',
                '6' => 'family-name',
                '8' => 'honorific-suffix',
            ],
        ],
        'address' => [
            'inputs' => [
                '1' => 'address-line1',
                '2' => 'address-line2',
                '3' => 'address-level2',
                '4' => 'address-level1',
                '5' => 'postal-code',
                '6' => 'country-name',
            ],
        ],
        // With confirmation on, both the main input and ".2" take the token.
        'email'   => ['field' => 'email', 'all_inputs' => 'email'],
        'phone'   => ['field' => 'tel'],
        'website' => ['field' => 'url'],
    ];
}

/**
 * Resolves the autocomplete value a slot should end up with.
 *
 * Gravity Forms prints the attribute unescaped and explode()s it, so a stored
 * value is only kept when it is a string made of letters, digits, hyphens and
 * spaces (spaces allow multi-token values like "section-a email"; "off" passes).
 * Anything else (array, quotes, markup) is replaced with the mapped constant, or
 * '' when the slot has no mapped token so GF prints nothing. A blank value gets
 * the mapped constant, or stays unset. Only constants from the token map are
 * ever introduced.
 *
 * @param mixed       $existing Stored value, or null if unset.
 * @param string|null $mapped   Token for this slot, if any.
 * @return mixed The value to store.
 */
function proud_gform_autocomplete_resolve($existing, $mapped) {
    if (is_string($existing) && trim($existing) !== '' && preg_match('/^[A-Za-z0-9\- ]+$/', trim($existing)) === 1) {
        return $existing;
    }

    $blank = $existing === null || $existing === false || (is_string($existing) && trim($existing) === '');
    if ($mapped !== null) {
        return $mapped;
    }

    return $blank ? $existing : '';
}

/**
 * Returns a field ready to print autocomplete attributes.
 *
 * Field types outside the allowlist, and name fields in the simple or legacy
 * "normal" format (customers can label those anything, so no token is safe),
 * come back as the same object. Otherwise a clone is returned with
 * enableAutocomplete on and blank tokens filled; tokens an admin set, including
 * "off", are kept. The clone keeps Gravity Forms' in-request form cache clean.
 *
 * @param object $field A GF_Field.
 * @return object
 */
function proud_gform_autocomplete_field($field) {
    if (! is_object($field) || ! isset($field->type) || ! is_string($field->type)) {
        return $field;
    }

    $map = proud_gform_autocomplete_tokens();
    if (! isset($map[ $field->type ])) {
        return $field;
    }
    $tokens = $map[ $field->type ];

    if ($field->type === 'name') {
        $format = isset($field->nameFormat) ? $field->nameFormat : '';
        if ($format !== 'advanced' && $format !== 'extended') {
            return $field;
        }
    }

    $clone = clone $field;
    $clone->enableAutocomplete = true;

    if (isset($clone->autocompleteAttribute) || isset($tokens['field'])) {
        $clone->autocompleteAttribute = proud_gform_autocomplete_resolve(
            isset($clone->autocompleteAttribute) ? $clone->autocompleteAttribute : null,
            isset($tokens['field']) ? $tokens['field'] : null
        );
    }

    $inputs = isset($clone->inputs) ? $clone->inputs : null;
    if (is_array($inputs)) {
        foreach ($inputs as $i => $input) {
            if (! is_array($input) || ! isset($input['id'])) {
                continue;
            }
            if (isset($tokens['all_inputs'])) {
                $mapped = $tokens['all_inputs'];
            } else {
                $parts  = explode('.', (string) $input['id']);
                $suffix = count($parts) > 1 ? end($parts) : '';
                $mapped = isset($tokens['inputs'][ $suffix ]) ? $tokens['inputs'][ $suffix ] : null;
            }
            if (! array_key_exists('autocompleteAttribute', $input) && $mapped === null) {
                continue;
            }
            $inputs[ $i ]['autocompleteAttribute'] = proud_gform_autocomplete_resolve(
                isset($input['autocompleteAttribute']) ? $input['autocompleteAttribute'] : null,
                $mapped
            );
        }
        $clone->inputs = $inputs;
    }

    return $clone;
}

/**
 * gform_pre_render: apply proud_gform_autocomplete_field() to every field.
 *
 * @param array $form
 * @return array
 */
function proud_gform_autocomplete_pre_render($form) {
    if (! is_array($form) || ! isset($form['fields']) || ! is_array($form['fields'])) {
        return $form;
    }

    foreach ($form['fields'] as $i => $field) {
        $form['fields'][ $i ] = proud_gform_autocomplete_field($field);
    }

    return $form;
}

/**
 * Image-compression support for Gravity Forms file upload fields (issue
 * #2912). Users upload multi-MB phone photos that either blow Mailgun's 25MB
 * notification cap or arrive un-attached; these forms do not need full-size
 * originals. Everything below is at the top level, outside the
 * class_exists('GFCommon') guard, so it can be unit tested without a real
 * Gravity Forms/WordPress install -- the guarded block below only registers
 * the hooks.
 */

/**
 * Whether the per-field "Compress uploaded images" toggle is on. Default is
 * on, so existing forms benefit without an edit.
 *
 * Object access only. GF_Field implements ArrayAccess and offsetGet()
 * returns '' for an undeclared key rather than null -- filter_var('',
 * FILTER_VALIDATE_BOOLEAN) is false, so $field['proudCompressImages'] would
 * silently disable compression on every field that has never touched this
 * setting. isset($field->proudCompressImages) has no such trap.
 */
function proud_gform_compress_enabled($field) {
    if (! is_object($field) || ! isset($field->proudCompressImages)) {
        return true;
    }

    return filter_var($field->proudCompressImages, FILTER_VALIDATE_BOOLEAN);
}

/**
 * Normalizes a gform_save_field_value value into a list of URL strings.
 *
 * GF 2.10+ sets storageType=json on every new File Upload field, so even a
 * single-file value is a JSON-encoded array of one URL, not a plain string --
 * branching on $field['multipleFiles'] misses that shape. Parse JSON first
 * and fall back to treating the value as one plain URL.
 *
 * get_prepared_input_value() (via GFFormsModel::create_lead()) also runs this
 * filter, but with a JSON array of arrays (tmp_path/tmp_url keys) rather than
 * strings. Those items are filtered out here rather than treated as URLs, so
 * this helper never reaches into Gravity Forms' tmp upload directory.
 */
function proud_gform_upload_urls($value) {
    if (! is_string($value) || '' === trim($value)) {
        return array();
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return array_values(array_filter($decoded, 'is_string'));
    }

    return array($value);
}

/**
 * Resolves an upload URL to a local file path confined to the Gravity Forms
 * upload root, or null if it is not a safe local path.
 *
 * Rejects anything that isn't under $url_root (this is how a GCS URL -- from
 * either the Stateless addon or our legacy bridge, or a true `stateless`-mode
 * value -- gets skipped: it was never rewritten to a local URL in the first
 * place, so it simply never matches), any ".." traversal, anything under a
 * form's tmp/ upload directory, symlinks, and anything whose resolved real
 * path escapes the real upload root.
 */
function proud_gform_local_upload_path($url, $url_root, $root) {
    if (! is_string($url) || '' === $url || ! is_string($url_root) || '' === $url_root || ! is_string($root) || '' === $root) {
        return null;
    }

    if (0 !== strpos($url, $url_root)) {
        return null;
    }

    $relative = wp_normalize_path(substr($url, strlen($url_root)));
    if (false !== strpos($relative, '..')) {
        return null;
    }

    if (preg_match('#(^|/)tmp/#', $relative) === 1) {
        return null;
    }

    $path = wp_normalize_path(trailingslashit($root) . ltrim($relative, '/'));

    if (is_link($path)) {
        return null;
    }

    $real_root = realpath($root);
    $real_path = realpath($path);
    if (false === $real_root || false === $real_path) {
        return null;
    }

    $real_root = trailingslashit(wp_normalize_path($real_root));
    $real_path = wp_normalize_path($real_path);

    if (0 !== strpos($real_path . '/', $real_root)) {
        return null;
    }

    return $real_path;
}

/**
 * Whether decoding the source image plus the resized output is likely to fit
 * in the memory actually available, after wp_raise_memory_limit('image') has
 * already raised the ceiling as far as it will go. No pixel cap: prod is GD
 * only (no Imagick, so no separate native-memory pool to worry about) with a
 * 256M WP_MAX_MEMORY_LIMIT, so this single estimate is the only guard.
 *
 * ~5 bytes/pixel decoded for a GD truecolor image: the source plus the
 * resized output, plus a second output-sized copy for the EXIF rotate (GD
 * rotate/flip allocate a new image before freeing the old one), plus the
 * raw file bytes WP_Image_Editor_GD::load() holds while decoding, plus 10%
 * headroom. An unlimited memory_limit (-1, or 0) always
 * passes.
 */
function proud_gform_image_fits_memory($width, $height, $max_dimension, $file_bytes = 0) {
    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('image');
    }

    $limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
    if ($limit <= 0) {
        return true;
    }

    $available = $limit - memory_get_usage();

    $long_edge = max($width, $height);
    $scale     = ($long_edge > $max_dimension) ? ($max_dimension / $long_edge) : 1;
    $out_width  = max(1, (int) round($width * $scale));
    $out_height = max(1, (int) round($height * $scale));

    $pixels    = ($width * $height) + (2 * $out_width * $out_height);
    $estimated = (int) round((($pixels * 5) + max(0, (int) $file_bytes)) * 1.1);

    return $estimated <= $available;
}

/**
 * Whether a webp/png at $path is animated. Animated WebP/APNG are out of
 * scope (issue #2912) -- resizing/re-saving through WP_Image_Editor would
 * collapse them to a single frame.
 */
function proud_gform_is_animated_image($path, $mime) {
    if ('image/webp' === $mime) {
        $head = @file_get_contents($path, false, null, 0, 64);
        return is_string($head) && false !== strpos($head, 'ANIM');
    }

    if ('image/png' === $mime) {
        $head = @file_get_contents($path, false, null, 0, 8192);
        if (! is_string($head)) {
            return false;
        }
        $act_pos  = strpos($head, 'acTL');
        $idat_pos = strpos($head, 'IDAT');
        return false !== $act_pos && (false === $idat_pos || $act_pos < $idat_pos);
    }

    return false;
}

function proud_gform_cleanup_temp($path) {
    if (is_string($path) && '' !== $path && file_exists($path)) {
        @unlink($path);
    }
}

/**
 * Claims one slot from the per-request compression budget. Returns false once
 * $max images have been decoded in this request, so a multi-file field (or
 * several fields) full of cheap-to-send, large-to-decode images cannot hold a
 * PHP worker past max_execution_time. The count is per request, across all
 * fields, because gform_save_field_value runs once per field.
 *
 * @param int  $max   Images allowed per request.
 * @param bool $reset Reset the counter (tests only).
 */
function proud_gform_claim_compress_slot($max, $reset = false) {
    static $used = 0;

    if ($reset) {
        $used = 0;
        return true;
    }

    if ($used >= $max) {
        return false;
    }

    $used++;
    return true;
}

function proud_gform_log_debug($message) {
    if (class_exists('GFCommon') && is_callable(array('GFCommon', 'log_debug'))) {
        \GFCommon::log_debug($message);
    }
}

function proud_gform_log_error($message) {
    if (class_exists('GFCommon') && is_callable(array('GFCommon', 'log_error'))) {
        \GFCommon::log_error($message);
    }
}

/**
 * Compresses a single local image file in place. Type is decided from
 * getimagesize() AND the file extension, never the client-supplied MIME type
 * GF stored with the entry. Saves to a sibling temp file first and only
 * renames it over the original once we know the result is smaller, so a
 * failure at any stage leaves the original untouched.
 *
 * Returns a status string rather than throwing: 'resized', one of the
 * 'skipped_*' reasons, or one of the 'failed_*' reasons. Never blocks a
 * submission -- every failure path keeps the original file exactly as it was.
 */
function proud_gform_compress_image_file($path, $max_dimension, $quality) {
    $temp_path = null;

    try {
        if (! is_string($path) || '' === $path || ! is_readable($path)) {
            return 'skipped_not_image';
        }

        $info = @getimagesize($path);
        if (! is_array($info) || empty($info['mime'])) {
            return 'skipped_not_image';
        }

        $allowed_extensions = array(
            'image/jpeg' => array('jpg', 'jpeg'),
            'image/png'  => array('png'),
            'image/webp' => array('webp'),
        );

        if (! isset($allowed_extensions[$info['mime']])) {
            return 'skipped_not_image';
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, $allowed_extensions[$info['mime']], true)) {
            return 'skipped_not_image';
        }

        $width  = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        if ($width < 1 || $height < 1) {
            return 'skipped_not_image';
        }

        if (proud_gform_is_animated_image($path, $info['mime'])) {
            return 'skipped_animated';
        }

        if (max($width, $height) <= $max_dimension) {
            return 'skipped_small';
        }

        if (! proud_gform_image_fits_memory($width, $height, $max_dimension, (int) @filesize($path))) {
            proud_gform_log_debug("proud_gform_compress_image_file(): skipping {$path}, does not fit available memory.");
            return 'skipped_memory';
        }

        // Counted only for images that will actually be decoded: PDFs, small
        // images and skips above do not use up the budget.
        $max_per_request = max(1, (int) apply_filters('proud_gform_image_max_per_request', 10));
        if (! proud_gform_claim_compress_slot($max_per_request)) {
            proud_gform_log_debug("proud_gform_compress_image_file(): skipping {$path}, per-request limit of {$max_per_request} images reached.");
            return 'skipped_request_limit';
        }

        $editor = wp_get_image_editor($path);
        if (is_wp_error($editor)) {
            proud_gform_log_error('proud_gform_compress_image_file(): wp_get_image_editor() failed for ' . $path . ': ' . $editor->get_error_message());
            return 'failed_editor';
        }

        $editor->set_quality($quality);

        $resized = $editor->resize($max_dimension, $max_dimension, false);
        if (is_wp_error($resized)) {
            proud_gform_log_error('proud_gform_compress_image_file(): resize() failed for ' . $path . ': ' . $resized->get_error_message());
            return 'failed_resize';
        }

        // Rotate after the resize so GD copies a ~4MP image, not the full-size
        // original (a full-size rotate doubles peak memory and can fatal past
        // the guard above). The resize box is square, so the result is the same.
        $editor->maybe_exif_rotate();

        // The temp name must end in the original image extension.
        // WP_Image_Editor::get_output_format() replaces any extension that
        // does not match the output MIME type, so "photo.jpg.<id>.tmp" would
        // be written as "photo.jpg.<id>.jpg" and fail the path check below.
        $temp_path = trailingslashit(dirname($path)) . pathinfo($path, PATHINFO_FILENAME) . '.' . uniqid('proudgf') . '.' . $ext;

        // A memory-limit fatal or timeout inside save() skips the catch block
        // below and would leave this copy of the upload in the public uploads
        // folder. Shutdown functions still run after a fatal. After a
        // successful rename() the temp path no longer exists, so this is a
        // no-op.
        register_shutdown_function(__NAMESPACE__ . '\\proud_gform_cleanup_temp', $temp_path);

        $saved = $editor->save($temp_path, $info['mime']);
        if (is_wp_error($saved)) {
            proud_gform_log_error('proud_gform_compress_image_file(): save() failed for ' . $path . ': ' . $saved->get_error_message());
            proud_gform_cleanup_temp($temp_path);
            return 'failed_save';
        }

        $saved_path = is_array($saved) && isset($saved['path']) ? $saved['path'] : null;
        if ($saved_path !== $temp_path) {
            proud_gform_log_error('proud_gform_compress_image_file(): save() wrote to an unexpected path for ' . $path);
            proud_gform_cleanup_temp($saved_path);
            proud_gform_cleanup_temp($temp_path);
            return 'failed_output_path';
        }

        clearstatcache(true, $temp_path);
        $original_size = @filesize($path);
        $new_size      = @filesize($temp_path);

        if (false === $original_size || false === $new_size || $new_size >= $original_size) {
            proud_gform_cleanup_temp($temp_path);
            return 'skipped_not_smaller';
        }

        if (! @rename($temp_path, $path)) {
            proud_gform_log_error('proud_gform_compress_image_file(): rename() failed for ' . $path);
            proud_gform_cleanup_temp($temp_path);
            return 'failed_rename';
        }

        if (is_callable(array('GFFormsModel', 'set_permissions'))) {
            \GFFormsModel::set_permissions($path);
        }

        return 'resized';
    } catch (\Throwable $t) {
        proud_gform_cleanup_temp($temp_path);
        proud_gform_log_error('proud_gform_compress_image_file(): exception for ' . $path . ': ' . $t->getMessage());
        return 'failed_exception';
    }
}

/**
 * gform_save_field_value callback. Must run before both GCS handlers upload
 * the original bytes (registered at priority 5 in proud_gravityforms_init(),
 * see the comment there), so the value is still a local URL when this runs.
 * Always returns $value unchanged -- we rewrite bytes on disk, not the URL,
 * so whichever handler runs after us uploads/rewrites exactly as it does
 * today. The whole body is wrapped in try/catch(\Throwable) because this must
 * never block a submission.
 */
function gform_compress_image_upload($value, $lead, $field, $form) {
    try {
        if (empty($value) || ! isset($field->type) || 'fileupload' !== $field->type) {
            return $value;
        }

        if (! proud_gform_compress_enabled($field)) {
            return $value;
        }

        $urls = proud_gform_upload_urls($value);
        if (empty($urls)) {
            return $value;
        }

        $upload_root     = \GFFormsModel::get_upload_root();
        $upload_url_root = \GFFormsModel::get_upload_url_root();

        $max_dimension = (int) apply_filters('proud_gform_image_max_dimension', 2000, $field, $form);
        $max_dimension = max(1, $max_dimension);

        $quality = (int) apply_filters('proud_gform_image_quality', 82, $field, $form);
        $quality = min(100, max(1, $quality));

        foreach ($urls as $url) {
            $path = proud_gform_local_upload_path($url, $upload_url_root, $upload_root);
            if (null === $path) {
                proud_gform_log_debug('gform_compress_image_upload(): skipping non-local or unsafe upload url: ' . $url);
                continue;
            }

            $status = proud_gform_compress_image_file($path, $max_dimension, $quality);
            proud_gform_log_debug('gform_compress_image_upload(): ' . $status . ' for ' . $path);
        }
    } catch (\Throwable $t) {
        proud_gform_log_error('gform_compress_image_upload() exception: ' . $t->getMessage());
    }

    return $value;
}

if (class_exists('GFCommon')) {

    // Load our downloading class
    require_once plugin_dir_path(__FILE__) . 'class-gc-gf-download.php';

    function proud_gravityforms_init()
    {
        // Always alter:

        add_filter('gform_confirmation_anchor', __NAMESPACE__ . '\\gform_confirmation_anchor_alter');
        add_filter("gform_init_scripts_footer", __NAMESPACE__ . '\\gform_force_footer_scripts');
        add_action('gform_enqueue_scripts', __NAMESPACE__ . '\\gform_css_dequeue', 100);
        add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\gform_admin_css_dequeue', 100);
        // Enable ability to controll label visibilit
        add_filter('gform_enable_field_label_visibility_settings', '__return_true');

        add_filter('gform_enable_legacy_markup', '__return_false');
        add_filter('gform_form_theme_slug', __NAMESPACE__ . '\\proud_force_orbital_theme', 10, 2);

        add_filter('gform_field_content', __NAMESPACE__ . '\\gf_remove_aria_required', 999, 5);

        // Print autocomplete attributes on name/email/phone/address/website (WCAG 1.3.5).
        // Late priority so fields added by other gform_pre_render callbacks are covered.
        add_filter('gform_pre_render', __NAMESPACE__ . '\\proud_gform_autocomplete_pre_render', 50);

        // GF 3.2 syncs multi-page forms to a ?gf_page_{id} query arg and re-reads it on
        // popstate. The Angular router in wp-proud-actions-app rewrites #gf_{id} to
        // #/gf_{id}, which fires popstate with no query arg, so GF jumps back to page 1
        // after Next on non-AJAX forms. Turn the page query arg off (wp-proudcity#3026).
        add_filter('gform_disable_page_query_arg', '__return_true');

        add_filter('gform_add_field_buttons', __NAMESPACE__ . '\\proud_remove_gf_post_fields', 10, 1);
        add_filter('gform_disable_post_creation', '__return_true'); // stops all post creation

        // Compress file-upload field images before either GCS path below can
        // upload the original bytes: the Stateless addon
        // (wp-stateless-gravity-forms-addon, class-gravity-forms.php) runs at
        // priority 10, and our own legacy bridge (gform_handle_file_upload()
        // below) runs at priority 100. Priority 5 keeps this ahead of both,
        // and it must stay below 10 and 100 -- registering it later would
        // resize a file that has already been rewritten to a GCS URL (or, on
        // the legacy path, already uploaded), which is a silent no-op. Runs
        // unconditionally, above both gates below, since it never touches
        // Google Cloud Storage itself.
        add_filter('gform_save_field_value', __NAMESPACE__ . '\\gform_compress_image_upload', 5, 4);

        // Per-field "Compress uploaded images" toggle in the form editor.
        add_action('gform_field_standard_settings', __NAMESPACE__ . '\\gform_compress_images_field_setting', 10, 2);
        add_action('gform_editor_js', __NAMESPACE__ . '\\gform_compress_images_editor_js');

        // Everything below here bridges Gravity Forms uploads and entry exports
        // to Google Cloud Storage. Without WP-Stateless there is no bucket and
        // none of it can work, so registering it is not merely pointless, it
        // breaks the site two ways: gform_secure_file_download_url() fatals on
        // the undefined ud_get_stateless_media(), and the export hijack below
        // strips Gravity Forms' own handler in favour of a bucket URL that does
        // not exist. Bail and leave GF's native local handling in place.
        if (! proud_gform_stateless_available()) {
            return;
        }

        // dealing with entry export
        add_action('gform_post_export_entries', __NAMESPACE__ . '\\sync_entry_export_file', 10, 5);
        //add_action( 'wp_ajax_gf_download_export', __NAMESPACE__ . '\\gf_hijack_download_export' );
        // GF registers its own handler at the default priority 10
        // (gravityforms.php:644); drop it so ours serves from the bucket instead.
        remove_all_filters('wp_ajax_gf_download_export', 10);
        add_filter('wp_ajax_gf_download_export', __NAMESPACE__ . '\\gf_hijack_download_export', 1);

        // Only alter if gravityforms <> stateless not enabled
        if (proud_gform_stateless_active()) {
            // Let stateless handle
            return;
        }

        \GC_GF_Download::maybe_process();
        add_filter('gform_secure_file_download_url', __NAMESPACE__ . '\\gform_secure_file_download_url', 100, 4);
        add_action('gform_save_field_value', __NAMESPACE__ . '\\gform_handle_file_upload', 100, 4);
    }

    add_action('init', __NAMESPACE__ . '\\proud_gravityforms_init', 11);

    /**
     * Forces the orbital theme on all forms at render time
     *
     * @since 2026.02.09
     * @author Curtis <curtis@proudcity.com>
     *
     * @param string $slug required Slug for the form
     * @param string $form required The form object 
     *
     * @return string The new slug we're forcing
     */
    function proud_force_orbital_theme( $slug, $form ){
        return 'orbital';
    }

    /**
     * Removes the aria-required="true" field as it makes screen readers state "required" twice when the
     * field is also labeled required.
     *
     * Issue: https://github.com/proudcity/wp-proudcity/issues/2527
     * Docs link: https://docs.gravityforms.com/gform_field_content/
     *
     * @since 2024.09.04.0715
     * @author Curtis
     * @access public
     *
     * @param	string			$content		required			The entire printed content of the field
     * @return	string			$content							Our modified content
     */
    function gf_remove_aria_required($content, $field, $value, $lead_id, $form_id)
    {
        /**
         * We get the form first so we can check the setting for the requiredIndicator
         * and if it's text then we'll have the `required` text in the field label
         * and we can remove the `aria-required` field from the input.
         *
         * I think there are too many other options to test for if a user chooses
         * `custom` and then puts any arbitrary text in place for `required` text and thus
         * we're not testing further
         */
        $form = \GFAPI::get_form(absint($form_id));

        if (isset($form['requiredIndicator']) && 'text' === $form['requiredIndicator']) {
            $content = str_replace("aria-required='true'", '', $content);
        }
        return $content;
    }

    /**
     * Removes the fields specified in the $fields_to_remove array
     * from visibility in the GF creation
     *
     * @since 2024.07.24
     * @author Curtis
     *
     * @param	$field_buttons		array			required				The array of available field buttons
     * @return	$field_buttons												Our modified array of buttons
     */
    function proud_remove_gf_post_fields($field_buttons)
    {

        // removes the heading we don't need once we've removed button
        $field_buttons = array_filter($field_buttons, function ($group) {

            return ! in_array($group['name'], ['post_fields']);
        });

        return $field_buttons;


        // if you need to remove individual buttons then you can use this code below
        // clearly you'll need to work it into the filter above which removes an entire section
        foreach ($field_buttons as &$field_group) {

            if (is_array($field_group) && array_key_exists('fields', $field_group)) {

                // removes fields inside the group first
                $field_group['fields'] = array_filter($field_group['fields'], function ($field) {

                    $fields_to_remove = [
                        'post_title',
                        'post_content',
                        'post_excerpt',
                        'post_tags',
                        'post_category',
                        'post_image',
                        'post_custom_field',
                    ];

                    return ! in_array($field['data-type'], $fields_to_remove);
                });
            } // if

        } // foreach

        return $field_buttons;
    }

    /**
     * Renders the "Compress uploaded images" checkbox in the File Upload
     * field's form-editor settings, right after Maximum File Size (position
     * 1275; see form_detail.php in Gravity Forms core).
     *
     * @since 2026.09.24
     *
     * @param int $position Standard-settings position currently rendering.
     * @param int $form_id  Form being edited.
     */
    function gform_compress_images_field_setting($position, $form_id)
    {
        if (1275 !== (int) $position) {
            return;
        }
        ?>
        <li class="compress_images_setting field_setting">
            <input type="checkbox" id="field_compress_images" onclick="SetFieldProperty('proudCompressImages', this.checked);" onkeypress="SetFieldProperty('proudCompressImages', this.checked);" />
            <label for="field_compress_images" class="inline">
                <?php esc_html_e('Compress uploaded images', 'wp-proud-core'); ?>
            </label>
        </li>
        <?php
    }

    /**
     * Wires the compress_images_setting into the fileupload field's setting
     * list, and loads/saves field.proudCompressImages, mirroring Gravity
     * Forms' own gform_load_field_settings pattern.
     *
     * @since 2026.09.24
     */
    function gform_compress_images_editor_js()
    {
        ?>
        <script type="text/javascript">
            fieldSettings.fileupload += ', .compress_images_setting';

            jQuery(document).on('gform_load_field_settings', function (event, field, form) {
                jQuery('#field_compress_images').prop('checked', field.proudCompressImages !== false);
            });
        </script>
        <?php
    }

    /**
     * Hijacks the GF download function and sends it the file from WP Stateless
     *
     * @since 2024.02.21
     * @author Curtis
     *
     * @uses 	check_ajax_referer() 				verifies that the AJAX request is valid
     * @uses 	current_user_can() 					true if the user has required permissions
     * @uses 	getenv() 							gets k8s environment var
     * @uses 	rgget() 							GF function to get form data
     * @uses 	GFAPI::get_form() 					GF - returns form object
     * @uses 	sanitize_title_with_dashes() 		sanitizes title
     * @uses 	esc_attr() 							keeping content safe
     * @uses 	get_option() 						returns data from wp_options
     * @uses 	readfile() 							pushes file download
     */
    function gf_hijack_download_export()
    {

        check_ajax_referer('gform_download_export');

        if (! current_user_can('edit_posts')) {
            error_log('not allow to export gf entries');
            // not allow to export entries
            exit;
        }

        // defining the relative path starting point
        if (getenv('WORDPRESS_DB_NAME')) {
            $name = getenv('WORDPRESS_DB_NAME');
        } else {
            $name = 'wwwproudcity';
        }

        $export_id = rgget('export-id');
        if (! proud_gform_valid_export_id($export_id)) {
            error_log('invalid gf export-id');
            exit;
        }

        $form_id = rgget('form-id');
        $form = \GFAPI::get_form(absint($form_id));
        $form_title = $form['title'];

        $filename =  sanitize_title_with_dashes($form['title']) . '-' . gmdate('Y-m-d', \GFCommon::get_local_timestamp(time())) . '.csv';
        $url = 'https://storage.googleapis.com/proudcity/' . rawurlencode($name) . '/uploads/gravity_forms/export/export-' . $export_id . '.csv';

        $charset = get_option('blog_charset');
        header('Content-Description: File Transfer');
        header("Content-Disposition: attachment; filename=$filename");
        header('Content-Type: text/csv; charset=' . $charset, true);
        $result        = readfile($url);

        /**
         * Logging code if we need to check this in the future
        $logging = array(
            'url' => $url,
            'form_title' => $form_title,
            'export_id' => rgget('export-id'),
            'form_id' => rgget('form-id'),
        );

        error_log( print_r( $logging, true ) );

        update_option( 'sfn_test_download_url', $logging );
         */

        exit;
    }

    /**
     * Pushes the generated entry export file to cloud storage
     *
     * @since 2024.02.21
     * @author Curtis
     *
     * @param 		object 		$form 			optional 			GF form object
     * @param 		string 		$start_date 	optional 			start date for export
     * @param 		string 		$end_date 		optional 			end date for export
     * @param 		array 		$fields 		optional 			fields to include in export
     * @param 		string 		$exort_id 		required 			ID of the export we're dealing with
     * @uses 		wp_upload_dir() 								returns WP upload directory path
     * @uses 		esc_attr() 										keepin content safe
     * @uses 		getenv() 										returns k8s environment var
     * @uses 		sm:sync::syncFile 								hooks in with WP Stateless and syncs the given file
     */
    function sync_entry_export_file($form, $start_date, $end_date, $fields, $export_id)
    {

        $uploads_dir = wp_upload_dir();
        $absolutePath = $uploads_dir['basedir'] . '/gravity_forms/export/export-' . esc_attr($export_id) . '.csv';

        // defining the relative path starting point
        if (getenv('WORDPRESS_DB_NAME')) {
            $name = getenv('WORDPRESS_DB_NAME');
        } else {
            $name = 'wwwproudcity';
        }

        $relativePath = esc_attr($name) . '/uploads/gravity_forms/export/export-' . esc_attr($export_id) . '.csv';
        $relativePath = apply_filters('wp_stateless_filename', $relativePath, 0);

        do_action('sm:sync::syncFile', $relativePath, $absolutePath, true);
    }

    function get_upload_root_url()
    {
        // Get wordpress base;
        $dir = wp_upload_dir();
        if ($dir['error']) {
            return null;
        }

        // WP core upload
        return trailingslashit($dir['baseurl']);
    }

    function get_upload_root_dir()
    {
        // Get wordpress base;
        $dir = wp_upload_dir();
        if ($dir['error']) {
            return null;
        }

        // WP core upload
        return trailingslashit($dir['basedir']);
    }

    // Handle file field uploads to googlestorage
    function gform_secure_file_download_url($file, $form)
    {

        // proud_gravityforms_init() already refuses to register this filter
        // without WP-Stateless. Guard anyway so the function can never fatal if
        // anything else attaches it — gform_handle_file_upload() below has
        // carried the same guard all along (issue #55).
        if (! proud_gform_stateless_available()) {
            return $file;
        }

        $bucketLink = trailingslashit('https://storage.googleapis.com/' . ud_get_stateless_media()->get('sm.bucket'));
        if (strpos($file, $bucketLink) !== false) {
            // Take out google storage
            $file = str_replace($bucketLink, '', $file);
            // Take out stateless root
            $file = str_replace(ud_get_stateless_media()->get('sm.root_dir'), '', $file);
            // WP core upload dir
            $upload_root_dir = get_upload_root_dir();
            // Gform upload dir
            $gform_upload = trailingslashit(str_replace($upload_root_dir, '', \GFFormsModel::get_upload_path($form->formId)));
            $file         = str_replace($gform_upload, '', $file);
            // Build hashed download
            $download_url = site_url('index.php');
            // Build args
            $args = array(
                'gc-gf-download' => urlencode($file),
                'form-id'        => $form->formId,
                'field-id'       => $form->id,
                'hash'           => \GFCommon::generate_download_hash($form->formId, $form->id, $file),
            );
            // @TODO force download?
            // if ( $force_download ) {
            //   $args['dl'] = 1;
            // }
            $file = add_query_arg($args, $download_url);
        }

        return $file;
    }

    function gform_get_gcloud_file($value)
    {

        // WP core upload url
        $upload_root_url = get_upload_root_url();
        if (strpos($value, $upload_root_url) !== false) {
            // Init WP-Stateless client
            $client = ud_get_stateless_media()->get_client();
            // Get file name (/wp-content/uploads/gravity_forms/[hash]/)
            $file = wp_normalize_path(str_replace($upload_root_url, '', $value));
            // Try to randomize filename to avoid conflicts
            $info = pathinfo($file);
            if (! empty($info['basename'])) {
                // Signal the cache-bust filter that this randomize_filename() call
                // is for a GF upload, so it bypasses the idempotency guards and
                // always mints a fresh unique suffix (issue #2876).
                $GLOBALS['proudcity_gform_upload_context'] = true;
                try {
                    $randomized = \wpCloud\StatelessMedia\Utility::randomize_filename($info['basename']);
                } finally {
                    unset( $GLOBALS['proudcity_gform_upload_context'] );
                }
                $file = trailingslashit($info['dirname']) . $randomized;
            }
            // Gform upload dir (/var/www/html/wp-content/uploads/gravity_forms)
            $gform_upload = \GFFormsModel::get_upload_root();
            // Gform upload url (https://thesite.com/wp-content/uploads/gravity_forms)
            $gform_upload_url = \GFFormsModel::get_upload_url_root();
            // Path on WP system
            $absolute = wp_normalize_path(str_replace($gform_upload_url, $gform_upload, $value));
            // Send file to Google
            $media = $client->add_media(array_filter(array(
                'name'         => $file,
                'absolutePath' => $absolute,
                'cacheControl' => \wpCloud\StatelessMedia\Utility::getCacheControl(null, [], null),
            )));
            // Break if we have errors.
            // @note Errors could be due to key being invalid or now having sufficient permissions in which case should notify user.
            if (is_wp_error($media)) {
                return $value;
            }
            // Build our url again
            $bucketLink = 'https://storage.googleapis.com/' . ud_get_stateless_media()->get('sm.bucket');

            return $bucketLink . '/' . (! empty($media['name']) ? $media['name'] : $file);
        }
    }

    // Handle file field uploads to googlestorage
    function gform_handle_file_upload($value, $lead, $field, $form)
    {

        if (! proud_gform_stateless_available()) {
            return $value;
        }

        if (! empty($value) && $field->type === 'fileupload') {
            if ($field['multipleFiles']) {
                try {
                    $values = json_decode($value);
                } catch (\Exception $exception) {
                    // @TODO log this?
                    return $value;
                }

                if (! empty($values)) {
                    foreach ($values as $key => $v) {
                        $values[$key] = gform_get_gcloud_file($v);
                    }
                    return json_encode($values);
                }
            } else {
                return gform_get_gcloud_file($value);
            }
        }

        return $value;
    }


    // On ajax anchors, this adds a offset for the scroll
    function gform_confirmation_anchor_alter()
    {
        return 0;
    }

    function gform_force_footer_scripts()
    {
        return true;
    }


    function gform_css_dequeue()
    {
        wp_deregister_style('gforms_datepicker_css');
        wp_dequeue_style('gforms_datepicker_css');
    }

    function gform_admin_css_dequeue()
    {
        wp_deregister_style('gform_font_awesome');
        wp_dequeue_style('gform_font_awesome');
        global $wp_styles;
        if (! empty($wp_styles->registered['gform_tooltip']->deps)) {
            $wp_styles->registered['gform_tooltip']->deps = [];
        }
    }
}
