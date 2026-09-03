<?php

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Proud\Core\esc_widget_title() -- issue #2916.
 *
 * Widget "Link title" fields were originally going to get esc_html(). One
 * published page on the San Rafael database copy stores markup in one:
 *
 *   #10131 how-were-solving-homelessness -- "Report illegal <br> camping "
 *
 * esc_html() renders that as the literal text "Report illegal <br> camping".
 * That is the correct long-term answer for a field that was never meant to
 * take HTML, but it is not a safe assumption across 100+ sites and thousands
 * of customer-authored pages -- one local database is not the fleet, and a
 * <br> in a title is exactly the kind of thing an editor reaches for. So the
 * titles keep line breaks and lose everything else.
 *
 * wp_kses() with ['br' => []] is the whole mechanism: <br> survives, its
 * attributes are dropped, every other tag is removed (its text kept), and a
 * bare ampersand is encoded exactly once. All eight call sites are HTML text
 * nodes, so kses leaving quotes unencoded is safe -- there is no attribute to
 * break out of. The helper docblock lists them.
 *
 * Known cost, accepted: kses treats a bare "<" as a tag start and consumes
 * through the next ">", so a title like "Wait < 5 minutes > call us" silently
 * loses text where esc_html() would have shown it. That is inherent to kses
 * and is how wp_kses_post() behaves everywhere else in WordPress.
 *
 * The stub these run against is pinned to real WordPress output by
 * KsesStubFidelityTest.
 */
class EscWidgetTitleTest extends TestCase
{
    use AppliesPreKsesFilter;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->applyPreKsesFilter();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * The value that drove this decision, from page #10131.
     */
    public function test_the_stored_br_on_page_10131_survives(): void
    {
        $this->assertSame(
            'Report illegal <br> camping ',
            \Proud\Core\esc_widget_title('Report illegal <br> camping ')
        );
    }

    public function test_self_closing_br_survives(): void
    {
        $this->assertSame(
            'Line one<br /> line two',
            \Proud\Core\esc_widget_title('Line one<br /> line two')
        );
    }

    public function test_script_tag_is_removed(): void
    {
        $out = \Proud\Core\esc_widget_title('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $out);
        $this->assertSame('alert(1)', $out);
    }

    public function test_anchor_is_removed_but_its_text_kept(): void
    {
        $this->assertSame(
            'click',
            \Proud\Core\esc_widget_title('<a href="https://evil.tld">click</a>')
        );
    }

    /**
     * An allowed <br> must not be a smuggling route for an event handler.
     */
    public function test_br_attributes_are_stripped(): void
    {
        $out = \Proud\Core\esc_widget_title('<br onclick="alert(1)">Report');

        $this->assertStringNotContainsString('onclick', $out);
        $this->assertSame('<br>Report', $out);
    }

    public function test_image_payload_is_removed_entirely(): void
    {
        $this->assertSame('', \Proud\Core\esc_widget_title('<img src=x onerror=alert(1)>'));
    }

    public function test_attribute_breakout_attempt_is_inert(): void
    {
        $out = \Proud\Core\esc_widget_title('"><img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertSame('"&gt;', $out);
    }

    /**
     * "Boards & Commissions" and "Finance & tax" are real stored titles. The
     * ampersand must encode once -- a double-encoded "&amp;amp;" would show a
     * literal "&amp;" on the page.
     */
    public function test_bare_ampersand_is_encoded_once(): void
    {
        $this->assertSame(
            'Boards &amp; Commissions',
            \Proud\Core\esc_widget_title('Boards & Commissions')
        );
    }

    public function test_an_existing_entity_is_not_double_encoded(): void
    {
        $this->assertSame(
            'Finance &amp; tax',
            \Proud\Core\esc_widget_title('Finance &amp; tax')
        );
    }

    public function test_plain_titles_are_untouched(): void
    {
        $this->assertSame('Pay a bill', \Proud\Core\esc_widget_title('Pay a bill'));
    }

    /**
     * Legacy widget instances can be missing the key entirely; the call sites
     * pass null rather than guarding each one.
     */
    public function test_null_is_handled_as_an_empty_string(): void
    {
        $this->assertSame('', \Proud\Core\esc_widget_title(null));
    }

    /**
     * Documents the accepted content-loss case so it is a deliberate,
     * test-locked decision rather than a surprise. See the class docblock.
     */
    public function test_bare_angle_brackets_lose_text_as_kses_does(): void
    {
        $this->assertSame('a  d', \Proud\Core\esc_widget_title('a < b and c > d'));
    }

    /**
     * Widget instance values can be arrays after an import or a migration. A
     * bare (string) cast warns on an array and is a fatal Error on an object
     * with no __toString().
     */
    public function test_an_array_value_yields_an_empty_string(): void
    {
        set_error_handler(static function ($severity, $message) {
            throw new \ErrorException($message, 0, $severity);
        }, E_ALL);

        try {
            $out = \Proud\Core\esc_widget_title(['<script>alert(1)</script>']);
        } finally {
            restore_error_handler();
        }

        $this->assertSame('', $out);
    }

    public function test_an_object_value_yields_an_empty_string(): void
    {
        $this->assertSame('', \Proud\Core\esc_widget_title(new \stdClass()));
    }

    /**
     * An object that CAN stringify is still handled, and its output escaped.
     */
    public function test_a_stringable_object_is_escaped(): void
    {
        $obj = new class {
            public function __toString(): string
            {
                return '<script>alert(1)</script>';
            }
        };

        $this->assertSame('', \Proud\Core\esc_widget_title($obj));
    }
}
