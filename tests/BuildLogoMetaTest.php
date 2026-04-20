<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for build_logo_meta() in proud-navbar.php.
 *
 * Covers the PHP 8.1 deprecation fix: wp_get_attachment_metadata() returns
 * false for attachments with no registered metadata, and the code must not
 * attempt to write into that false value (#2807).
 */
class BuildLogoMetaTest extends TestCase
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

    /**
     * When wp_get_attachment_metadata() returns false (e.g. an SVG or a logo
     * uploaded before image sizes were registered), the meta key is normalised
     * to [] and custom_width falls back to the default 140px.
     *
     * Before the fix: "Deprecated: Automatic conversion of false to array"
     * on line 123 of proud-navbar.php.
     */
    public function test_build_logo_meta_falls_back_to_default_width_when_metadata_is_false(): void
    {
        Functions\when('get_theme_mod')->justReturn(false);
        Functions\when('wp_get_attachment_metadata')->justReturn(false);
        Functions\when('wp_get_attachment_image_url')->justReturn('https://example.com/logo.png');

        // Pass a numeric ID so the $wpdb URL-lookup branch is skipped.
        $result = build_logo_meta(123, 'proud_wide_logo');

        $this->assertSame(140, $result['custom_width'],
            'custom_width must fall back to 140 when attachment metadata is false.');

        $this->assertSame('Home', $result['image_meta']['meta']['image_meta']['alt'],
            'alt tag must still be set to "Home" even when metadata was absent.');
    }

    /**
     * When valid metadata is present the custom_width is calculated from the
     * image dimensions rather than using the 140px fallback.
     *
     * Formula: width / height * 64 + 32, capped at 140.
     * With a 320×64 logo: 320/64 * 64 + 32 = 352 → capped to 140.
     * With a 100×64 logo: 100/64 * 64 + 32 = 132 → under cap, returned as-is.
     */
    public function test_build_logo_meta_calculates_width_from_valid_metadata(): void
    {
        Functions\when('get_theme_mod')->justReturn(false);
        Functions\when('wp_get_attachment_image_url')->justReturn('https://example.com/logo.png');

        // 100×64 logo → 100/64 * 64 + 32 = 132 (below the 140 cap).
        Functions\when('wp_get_attachment_metadata')->justReturn([
            'width'      => 100,
            'height'     => 64,
            'image_meta' => [],
        ]);

        $result = build_logo_meta(456, 'proud_wide_logo');

        $this->assertEqualsWithDelta(132.0, $result['custom_width'], 0.01,
            'custom_width must be calculated from real metadata dimensions.');
    }
}
