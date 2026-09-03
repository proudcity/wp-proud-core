<?php

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

use function Proud\Core\sanitize_html_classes;

/**
 * Tests for Proud\Core\sanitize_html_classes() -- issue #2916.
 *
 * WordPress's sanitize_html_class() sanitizes ONE class name: it strips every
 * character outside [A-Za-z0-9_-], whitespace included. Feeding it a
 * multi-class string welds the names together, so "fa-solid fa-leaf" comes
 * back as "fa-solidfa-leaf" and the icon disappears. 171 of the 174 distinct
 * fa_icon values on a production database copy are multi-class Font Awesome 6
 * strings, so that is the normal case for this field.
 *
 * sanitize_html_classes() splits on whitespace first, sanitizes each name, and
 * reassembles. The result can only contain [A-Za-z0-9_-] and single spaces,
 * which is safe to drop straight into a class="..." attribute.
 *
 * The real sanitize_html_class() is stubbed in tests/stubs.php with a faithful
 * copy of core's implementation (minus the filter), so these assertions
 * exercise the same character class WordPress does.
 */
class SanitizeHtmlClassesTest extends TestCase
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
     * The regression this helper exists to prevent.
     */
    public function test_multi_class_value_keeps_its_spaces(): void
    {
        $this->assertSame('fa-solid fa-leaf', sanitize_html_classes('fa-solid fa-leaf'));
        $this->assertSame('fa-brands fa-facebook', sanitize_html_classes('fa-brands fa-facebook'));
        $this->assertSame('fas fa-users', sanitize_html_classes('fas fa-users'));
    }

    public function test_single_class_value_is_unchanged(): void
    {
        $this->assertSame('action', sanitize_html_classes('action'));
        $this->assertSame('card-inverse', sanitize_html_classes('card-inverse'));
    }

    public function test_empty_and_whitespace_only_values_return_empty_string(): void
    {
        $this->assertSame('', sanitize_html_classes(''));
        $this->assertSame('', sanitize_html_classes('   '));
        $this->assertSame('', sanitize_html_classes(null));
    }

    /**
     * The attribute-breakout case. Both the quote and the equals sign fall
     * outside [A-Za-z0-9_-], so nothing usable survives -- but the leftover
     * letters are still emitted as an (inert) class name, which is why the
     * assertion checks for the absence of the dangerous characters rather
     * than for a specific harmless string.
     */
    public function test_attribute_breakout_characters_are_stripped(): void
    {
        $out = sanitize_html_classes('fa-leaf" onmouseover="alert(1)');

        $this->assertStringNotContainsString('"', $out);
        $this->assertStringNotContainsString('=', $out);
        $this->assertStringNotContainsString('(', $out);
        $this->assertSame('fa-leaf onmouseoveralert1', $out);
    }

    public function test_angle_brackets_are_stripped(): void
    {
        $out = sanitize_html_classes('fa-leaf <script>alert(1)</script>');

        $this->assertStringNotContainsString('<', $out);
        $this->assertStringNotContainsString('>', $out);
    }

    public function test_percent_encoded_octets_are_stripped(): void
    {
        $this->assertSame('faleaf', sanitize_html_classes('fa%2Dleaf'));
    }

    /**
     * Runs of whitespace (including tabs and newlines, which arrive from
     * copy-pasted values) collapse to a single space, and names that sanitize
     * away entirely are dropped rather than left as empty strings. Otherwise
     * the attribute picks up double spaces or a trailing space.
     */
    public function test_whitespace_runs_collapse_and_empty_names_are_dropped(): void
    {
        $this->assertSame('fa-solid fa-leaf', sanitize_html_classes("  fa-solid \t\n fa-leaf  "));
        $this->assertSame('fa-solid fa-leaf', sanitize_html_classes('fa-solid %2F fa-leaf'));
    }

    public function test_output_contains_only_safe_characters(): void
    {
        $out = sanitize_html_classes('fa-solid fa-leaf" onclick="x" <b> \'q\' &amp;');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+( [A-Za-z0-9_-]+)*$/', $out);
    }
}
