<?php

/**
 * Minimal WordPress function stubs for testing.
 *
 * Covers calls made at file-include time (before Brain\Monkey per-test mocking
 * takes over). Uses if(!function_exists) guards so Patchwork can still patch
 * these definitions on a per-test basis.
 *
 */

// -------------------------------------------------------------------------
// Global namespace stubs
// -------------------------------------------------------------------------
namespace {
    if (!function_exists('add_action')) {
        function add_action() { return true; }
    }
    if (!function_exists('add_filter')) {
        function add_filter() { return true; }
    }
    if (!function_exists('remove_all_actions')) {
        function remove_all_actions() { return true; }
    }
    if (!function_exists('get_current_user_id')) {
        function get_current_user_id() { return 0; }
    }
    if (!function_exists('add_image_size')) {
        function add_image_size() {}
    }
    if (!function_exists('absint')) {
        function absint($n) { return abs((int) $n); }
    }
    if (!function_exists('wp_get_nav_menus')) {
        function wp_get_nav_menus() { return []; }
    }
    if (!function_exists('wp_get_nav_menu_items')) {
        function wp_get_nav_menu_items() { return []; }
    }
    if (!function_exists('wp_get_attachment_metadata')) {
        function wp_get_attachment_metadata() { return false; }
    }
    if (!function_exists('wp_get_attachment_image_url')) {
        function wp_get_attachment_image_url() { return ''; }
    }
    // wp-includes/kses.php is loaded for real in bootstrap.php rather than
    // modelled (see the note there). These are the only two things it needs
    // that a full WordPress load would otherwise provide.
    //
    // wp_allowed_protocols() is never actually consulted for our br-only
    // allowlist -- protocol checks live in wp_kses_check_attr_val(), which is
    // unreachable when no attribute is allowed -- but wp_kses() passes it
    // through by default, so it has to exist.
    if (!function_exists('wp_allowed_protocols')) {
        function wp_allowed_protocols() {
            return [
                'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs',
                'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel',
                'fax', 'xmpp', 'webcal', 'urn',
            ];
        }
    }

    // Verbatim from wp-includes/formatting.php. WordPress registers this on
    // the `pre_kses` filter in default-filters.php, and wp_kses() applies that
    // filter before it splits tags -- so it is part of what production kses
    // does, not an optional extra. Without it, "<br" comes out as a real <br>
    // tag instead of "&lt;br". Confirmed on the running site:
    //   wp eval 'global $wp_filter; print_r($wp_filter["pre_kses"]);'
    //   => wp_pre_kses_less_than, wp_pre_kses_block_attributes
    // The block-attributes one is a no-op here: it only rewrites block markup
    // when $allowed_html is a context string, and we always pass an array.
    if (!function_exists('wp_pre_kses_less_than_callback')) {
        function wp_pre_kses_less_than_callback($matches) {
            if (!str_contains($matches[0], '>')) {
                return esc_html($matches[0]);
            }
            return $matches[0];
        }
    }
    if (!function_exists('wp_pre_kses_less_than')) {
        function wp_pre_kses_less_than($content) {
            return preg_replace_callback('%<[^>]*?((?=<)|>|$)%', 'wp_pre_kses_less_than_callback', $content);
        }
    }
    // Faithful copy of core's sanitize_hex_color() (wp-includes/class-wp-customize-manager.php).
    // Returns null for anything that is not a 3- or 6-digit hex colour with a
    // leading #, and '' for an empty string. Copied rather than stubbed as a
    // passthrough because Proud\Core\action_button_color() turns on exactly that
    // null/'' distinction -- a permissive stub would rubber-stamp the tests.
    if (!function_exists('sanitize_hex_color')) {
        function sanitize_hex_color($color) {
            if ('' === $color) {
                return '';
            }
            if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color)) {
                return $color;
            }
            return null;
        }
    }

    if (!function_exists('get_theme_mod')) {
        function get_theme_mod() { return false; }
    }
    if (!function_exists('get_permalink')) {
        function get_permalink() { return ''; }
    }
    if (!function_exists('get_the_title')) {
        function get_the_title() { return ''; }
    }
    if (!function_exists('apply_filters')) {
        function apply_filters($tag, $value) {
            // Passthrough, with one exception: WordPress registers
            // wp_pre_kses_less_than() on `pre_kses` in default-filters.php, and
            // wp_kses() applies that filter before splitting tags. Since
            // bootstrap.php loads the real kses.php, the filter has to run too
            // or the tests would measure kses-without-its-filters, which is not
            // what any site executes. Verified against the running site.
            if ('pre_kses' === $tag && function_exists('wp_pre_kses_less_than')) {
                return wp_pre_kses_less_than($value);
            }
            return $value;
        }
    }
    if (!function_exists('plugins_url')) {
        function plugins_url() { return ''; }
    }
    if (!function_exists('plugin_dir_path')) {
        function plugin_dir_path() { return ''; }
    }
    if (!function_exists('wp_register_script')) {
        function wp_register_script() {}
    }
    if (!function_exists('wp_enqueue_script')) {
        function wp_enqueue_script() {}
    }
    if (!function_exists('wp_enqueue_style')) {
        function wp_enqueue_style() {}
    }
    if (!function_exists('wp_localize_script')) {
        function wp_localize_script() {}
    }
    if (!function_exists('wp_kses_post')) {
        function wp_kses_post($content) { return $content; }
    }
    if (!function_exists('get_post_meta')) {
        function get_post_meta() { return ''; }
    }
    if (!function_exists('get_post')) {
        function get_post() { return null; }
    }
    if (!function_exists('get_the_excerpt')) {
        function get_the_excerpt() { return ''; }
    }
    if (!function_exists('has_excerpt')) {
        function has_excerpt() { return false; }
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) { return $str; }
    }
    if (!function_exists('current_user_can')) {
        function current_user_can() { return false; }
    }
    if (!function_exists('wp_die')) {
        function wp_die() {}
    }
    if (!function_exists('wp_send_json')) {
        function wp_send_json($data) {}
    }
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data = null, $status_code = null) {}
    }
    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer() { return false; }
    }
    if (!function_exists('wp_create_nonce')) {
        function wp_create_nonce($action = -1) { return ''; }
    }
    if (!function_exists('admin_url')) {
        function admin_url($path = '', $scheme = 'admin') { return ''; }
    }
    if (!function_exists('get_admin_url')) {
        function get_admin_url($blog_id = null, $path = '', $scheme = 'admin') { return ''; }
    }
    if (!function_exists('is_customize_preview')) {
        function is_customize_preview() { return false; }
    }
    if (!function_exists('wp_get_post_terms')) {
        function wp_get_post_terms() { return []; }
    }
    if (!function_exists('esc_url')) {
        function esc_url($url) { return $url; }
    }
    if (!function_exists('esc_url_raw')) {
        function esc_url_raw($url) { return $url; }
    }
    // Faithful enough for the component the callers ask for. WordPress adds
    // scheme-relative handling on top of parse_url(); "//docs.google.com/x.pdf"
    // must report host "docs.google.com" and path "/x.pdf", which bare
    // parse_url() only gets right on PHP 5.4.7+ -- it does, so delegate.
    if (!function_exists('wp_parse_url')) {
        function wp_parse_url($url, $component = -1) {
            return parse_url((string) $url, $component);
        }
    }
    if (!function_exists('__')) {
        function __($text, $domain = '') { return $text; }
    }
    if (!function_exists('add_rewrite_rule')) {
        function add_rewrite_rule() {}
    }
    if (!function_exists('update_post_meta')) {
        function update_post_meta() { return true; }
    }
    if (!function_exists('wp_is_post_autosave')) {
        function wp_is_post_autosave() { return false; }
    }
    if (!function_exists('wp_is_post_revision')) {
        function wp_is_post_revision() { return false; }
    }
    if (!function_exists('wp_rand')) {
        function wp_rand($min = 0, $max = 0) { return rand($min ?: 0, $max ?: PHP_INT_MAX); }
    }
    if (!function_exists('wp_verify_nonce')) {
        function wp_verify_nonce() { return false; }
    }
    if (!function_exists('wp_nonce_field')) {
        function wp_nonce_field() { return ''; }
    }
    if (!function_exists('esc_attr')) {
        function esc_attr($text) { return $text; }
    }
    // Faithful to WordPress: strip %-encoded octets, then everything
    // outside [A-Za-z0-9_-]. Whitespace included -- which is exactly why
    // Proud\Core\sanitize_html_classes() splits before calling it.
    if (!function_exists('sanitize_html_class')) {
        function sanitize_html_class($classname, $fallback = '') {
            $sanitized = preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', (string) $classname);
            $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $sanitized);
            if ('' === $sanitized && $fallback) {
                return sanitize_html_class($fallback);
            }
            return $sanitized;
        }
    }
    if (!function_exists('esc_html')) {
        function esc_html($text) { return $text; }
    }
    if (!function_exists('checked')) {
        function checked($checked, $current = true, $echo = true) {
            $result = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
            if ( $echo ) { echo $result; }
            return $result;
        }
    }

    // WP_Query stub so plugin files can be loaded without a real DB.
    if (!class_exists('WP_Query')) {
        class WP_Query {
            public array $posts = [];
            public function __construct(array $args = []) {}
        }
    }
}

// Proud\Core functions (pc_get_yoast_meta_or_excerpt, build_retina_image_meta,
// etc.) are provided by proud-helpers.php, which bootstrap.php loads after
// this file. No stubs needed here.
