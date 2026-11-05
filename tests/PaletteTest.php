<?php

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

use function Proud\Core\proud_dark_palette;
use function Proud\Core\proud_legacy_palette_map;
use function Proud\Core\proud_normalize_palette_color;

/**
 * Tests for the central dark palette helpers in proud-helpers.php -- issue
 * #2768 round 2.
 *
 * The palette feeds meeting category colors (white text on the calendar) and
 * location marker colors, and the stored value is written into an inline
 * style by FullCalendar, so normalization has to be a strict allowlist.
 */
class PaletteTest extends TestCase
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

    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $out = array();
        foreach (array(0, 2, 4) as $i) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            $out[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
    }

    private function contrastAgainstWhite(string $hex): float
    {
        return 1.05 / ($this->luminance($hex) + 0.05);
    }

    public function test_palette_has_eight_colors(): void
    {
        $this->assertCount(8, proud_dark_palette());
    }

    public function test_every_palette_color_has_aa_contrast_against_white(): void
    {
        foreach (array_keys(proud_dark_palette()) as $hex) {
            $this->assertGreaterThanOrEqual(4.5, $this->contrastAgainstWhite($hex), $hex . ' fails WCAG AA against white.');
        }
    }

    public function test_palette_colors_are_uppercase_six_digit_hex(): void
    {
        foreach (array_keys(proud_dark_palette()) as $hex) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $hex);
        }
    }

    public function test_palette_colors_normalize_to_themselves(): void
    {
        foreach (array_keys(proud_dark_palette()) as $hex) {
            $this->assertSame($hex, proud_normalize_palette_color($hex));
            $this->assertSame($hex, proud_normalize_palette_color(strtolower($hex)));
        }
    }

    public function test_legacy_colors_map_to_dark_equivalents(): void
    {
        $expected = array(
            '#ED9356' => '#A84B14',
            '#456D9C' => '#456D9C',
            '#E76C6D' => '#BF0711',
            '#5A97C4' => '#0071BC',
            '#4DC3FF' => '#0071BC',
            '#9BBF6A' => '#108043',
        );
        foreach ($expected as $old => $new) {
            $this->assertSame($new, proud_normalize_palette_color($old));
            $this->assertSame($new, proud_normalize_palette_color(strtolower($old)));
        }
    }

    public function test_every_legacy_target_is_in_the_palette(): void
    {
        foreach (proud_legacy_palette_map() as $new) {
            $this->assertArrayHasKey($new, proud_dark_palette());
        }
    }

    /**
     * @dataProvider invalidValues
     */
    public function test_invalid_values_return_empty_string($value): void
    {
        $this->assertSame('', proud_normalize_palette_color($value));
    }

    public static function invalidValues(): array
    {
        return array(
            'empty'            => array(''),
            'null'             => array(null),
            'array'            => array(array('#BF0711')),
            'int'              => array(123),
            'valid hex off palette' => array('#123456'),
            'short hex'        => array('#fff'),
            'no hash'          => array('BF0711'),
            'name'             => array('red'),
            'css injection'    => array('#BF0711; background:url(https://x.test/l)'),
            'css breakout'     => array('red; background:url(x)'),
            'js scheme'        => array('javascript:alert(1)'),
            'style breakout'   => array('#BF0711"></style><script>alert(1)</script>'),
            'trailing newline' => array("#BF0711\n;x"),
        );
    }
}
