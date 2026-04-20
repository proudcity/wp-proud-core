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
        function apply_filters($tag, $value) { return $value; }
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
    if (!function_exists('wp_kses_post')) {
        function wp_kses_post($content) { return $content; }
    }
    if (!function_exists('get_post_meta')) {
        function get_post_meta() { return ''; }
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
    if (!function_exists('__')) {
        function __($text, $domain = '') { return $text; }
    }
    if (!function_exists('add_rewrite_rule')) {
        function add_rewrite_rule() {}
    }
}

// Proud\Core functions (pc_get_yoast_meta_or_excerpt, build_retina_image_meta,
// etc.) are provided by proud-helpers.php, which bootstrap.php loads after
// this file. No stubs needed here.
