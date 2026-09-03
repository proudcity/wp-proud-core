<?php

use Brain\Monkey\Functions;

/**
 * Makes wp_kses() behave the way it does on a real site.
 *
 * bootstrap.php loads the real wp-includes/kses.php, but wp_kses() runs its
 * input through wp_kses_hook() first, which applies the `pre_kses` filter.
 * WordPress registers two callbacks there in default-filters.php:
 *
 *   wp_pre_kses_less_than        -- escapes a "<" that never becomes a tag
 *   wp_pre_kses_block_attributes -- no-op here; it only rewrites block markup
 *                                   when $allowed_html is a context string,
 *                                   and esc_widget_title() always passes an array
 *
 * Without the first one, "<br" comes out of wp_kses() as a real <br> tag
 * instead of "&lt;br", so the tests would be measuring kses-without-its-filters
 * -- a configuration no site actually runs.
 *
 * Brain Monkey defines apply_filters() itself, before tests/stubs.php gets a
 * chance to, so the wiring has to go through Brain Monkey rather than a
 * function_exists() guard. Call this from setUp() after Monkey\setUp().
 */
trait AppliesPreKsesFilter
{
    protected function applyPreKsesFilter(): void
    {
        // wp_pre_kses_less_than_callback() escapes via esc_html(). The default
        // stub in stubs.php is a passthrough, which silently turns the whole
        // filter into a no-op -- "<br" would stay "<br" and then kses would
        // parse it into a real <br> tag. Alias a faithful esc_html() first.
        // Test classes that need their own esc_html() alias override this
        // afterwards; theirs are faithful too.
        Functions\when('esc_html')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });

        Functions\when('apply_filters')->alias(static function ($tag, $value = null) {
            if ('pre_kses' === $tag && function_exists('wp_pre_kses_less_than')) {
                return wp_pre_kses_less_than($value);
            }
            return $value;
        });
    }
}
