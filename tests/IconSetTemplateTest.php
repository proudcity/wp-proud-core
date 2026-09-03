<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the icon-set widget's icon-set.php template.
 *
 * Covers issue #1825 follow-up: the round-one restructure that added the
 * per-item "Open in new tab" support and esc_url() hardening moved the
 * target/rel/class markup around. This locks two things the security review
 * flagged as untested and risky to regress:
 *
 *   - target="_blank"/rel="noopener" must only ever land on the <a>. The
 *     <div> fallback (rendered when link_url is empty) must never receive
 *     them.
 *   - the two <?php if/else/endif ?> blocks that open the <a>/<div> and
 *     later close it must stay balanced -- a future edit could easily
 *     unbalance them since they live in separate template blocks.
 *
 * Also covers wrapping $classname in esc_attr() (both branches), which the
 * security review flagged as unescaped output inside a class="..." attribute.
 */
class IconSetTemplateTest extends TestCase
{
    use AppliesPreKsesFilter;

    private const ICON_SET_TEMPLATE = __DIR__ . '/../modules/proud-widget/widgets/icon-set/templates/icon-set.php';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->applyPreKsesFilter();

        // Realistic esc_url()/esc_attr() stubs, matching the ones used for
        // esc_link_url() itself -- a passthrough stub would hide a missing
        // escaping call. Kept consistent with ImageSetTemplateTest's esc_url()
        // stub, including the "http://" prepending WordPress applies to any
        // schemeless value that doesn't start with "/", "#" or "?" -- without
        // it this stub would not catch icon-set.php reverting to a bare
        // esc_url() call.
        Functions\when('esc_url')->alias(static function ($url) {
            $url = (string) $url;
            if ('' === $url) {
                return '';
            }
            if (preg_match('#^\s*javascript\s*:#i', $url)) {
                return '';
            }
            if (!preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $url) && !in_array($url[0], ['/', '#', '?'], true)) {
                $url = 'http://' . $url;
            }
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        });
        // Faithful to WordPress's real esc_attr(): _wp_specialchars() is
        // called with double_encode = false, which preserves an existing
        // character reference rather than re-encoding its "&". A plain
        // htmlspecialchars() call with default arguments double-encodes and
        // would hide that behaviour.
        Functions\when('esc_attr')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });
        // esc_html() stub, faithful to WordPress: _wp_specialchars() with
        // double_encode = false. The stubs.php default is a passthrough, which
        // would let an unescaped link_title sail through every assertion.
        Functions\when('esc_html')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Render the template with the supplied variables and return its output.
     */
    private function render(array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include self::ICON_SET_TEMPLATE;
        return (string) ob_get_clean();
    }

    private function iconItem(array $overrides = []): array
    {
        return array_merge([
            'link_title' => 'Item title',
            'link_url'   => '',
            'fa_icon'    => '',
        ], $overrides);
    }

    public function test_empty_link_url_renders_div_with_no_target_or_rel(): void
    {
        $output = $this->render([
            'iconset'   => [$this->iconItem()],
            'md_col'    => 4,
            'classname' => '',
        ]);

        $this->assertStringContainsString('<div class="card text-center card-btn card-block', $output);
        $this->assertStringNotContainsString('<a ', $output, 'No link_url means no anchor should render at all.');
        $this->assertStringNotContainsString('target="_blank"', $output, 'target must never land on the <div> fallback.');
        $this->assertStringNotContainsString('rel="noopener"', $output, 'rel must never land on the <div> fallback.');
    }

    public function test_nonempty_link_url_with_external_renders_target_and_rel_on_anchor(): void
    {
        $output = $this->render([
            'iconset'   => [$this->iconItem(['link_url' => 'https://example.com', 'external' => '1'])],
            'md_col'    => 4,
            'classname' => '',
        ]);

        $this->assertMatchesRegularExpression(
            '/<a href="https:\/\/example\.com" target="_blank" rel="noopener"/',
            $output,
            'target/rel must land on the <a>, immediately after href.'
        );
    }

    public function test_nonempty_link_url_without_external_has_no_target_or_rel(): void
    {
        $output = $this->render([
            'iconset'   => [$this->iconItem(['link_url' => 'https://example.com'])],
            'md_col'    => 4,
            'classname' => '',
        ]);

        $this->assertStringContainsString('<a href="https://example.com"', $output);
        $this->assertStringNotContainsString('target="_blank"', $output);
        $this->assertStringNotContainsString('rel="noopener"', $output);
    }

    public function test_empty_link_url_output_is_well_formed(): void
    {
        $output = $this->render([
            'iconset'   => [$this->iconItem()],
            'md_col'    => 4,
            'classname' => '',
        ]);

        $this->assertSame(
            substr_count($output, '<div'),
            substr_count($output, '</div>'),
            'Every <div> opened by the template must be closed -- the <a>/<div> branches live in separate <?php ?> blocks and could be unbalanced by a future edit.'
        );
        $this->assertStringNotContainsString('<a ', $output);
        $this->assertStringNotContainsString('</a>', $output);
    }

    public function test_nonempty_link_url_output_is_well_formed(): void
    {
        $output = $this->render([
            'iconset'   => [$this->iconItem(['link_url' => 'https://example.com'])],
            'md_col'    => 4,
            'classname' => '',
        ]);

        $this->assertSame(1, substr_count($output, '<a '));
        $this->assertSame(1, substr_count($output, '</a>'));
    }

    /**
     * Regression lock for #1825 follow-up: icon-set.php must call
     * esc_link_url(), not a bare esc_url(). A decimal character reference
     * has no literal ":" for esc_url() to strip, so a regression back to
     * esc_url() would let "javascript&#58;alert(1)" through as a live
     * javascript: scheme once the browser decodes the entity. See
     * EscLinkUrlTest.php for direct coverage of esc_link_url() itself.
     */
    public function test_entity_encoded_colon_link_url_is_rendered_via_esc_link_url(): void
    {
        $output = $this->render([
            'iconset'   => [$this->iconItem(['link_url' => 'javascript&#58;alert(1)'])],
            'md_col'    => 4,
            'classname' => '',
        ]);

        $this->assertStringContainsString('&amp;#58;', $output);
    }

    public function test_classname_is_escaped_in_both_branches(): void
    {
        $malicious = '"><script>alert(1)</script>';

        $withUrl = $this->render([
            'iconset'   => [$this->iconItem(['link_url' => 'https://example.com'])],
            'md_col'    => 4,
            'classname' => $malicious,
        ]);
        $withoutUrl = $this->render([
            'iconset'   => [$this->iconItem()],
            'md_col'    => 4,
            'classname' => $malicious,
        ]);

        $this->assertStringNotContainsString('"><script>', $withUrl, 'classname must be escaped on the <a> branch.');
        $this->assertStringNotContainsString('"><script>', $withoutUrl, 'classname must be escaped on the <div> branch.');
    }

    // -----------------------------------------------------------------
    // fa_icon / link_title escaping (#2916)
    // -----------------------------------------------------------------
    //
    // $classname already went through esc_attr() in #1825. $icon['fa_icon']
    // (inside class="...") and $icon['link_title'] (a text node) did not.
    //
    // fa_icon gets \Proud\Core\sanitize_html_classes(), NOT sanitize_html_class().
    // 171 of the 174 distinct fa_icon values on a production database copy are
    // multi-class Font Awesome 6 strings such as "fa-solid fa-leaf";
    // sanitize_html_class() strips the space and collapses that to
    // "fa-solidfa-leaf", which kills the icon. The helper splits on whitespace
    // first, so it sanitizes each name without welding them together. See
    // IconLinkWidgetTest and CtaButtonWidgetTest for the sibling widgets.

    public function test_fa_icon_quote_breakout_is_stripped(): void
    {
        $output = $this->render([
            'iconset'   => [$this->iconItem(['fa_icon' => 'fa-leaf" onmouseover="alert(1)'])],
            'md_col'    => 3,
            'classname' => '',
        ]);

        // sanitize_html_classes() drops every character outside [A-Za-z0-9_-],
        // so the quote and the equals sign are removed outright rather than
        // entity-encoded.
        $this->assertStringNotContainsString('onmouseover=', $output);
        $this->assertStringContainsString('class="fa fa-leaf onmouseoveralert1 fa-3x"', $output);
    }

    /**
     * Regression lock against sanitize_html_class(): a multi-class fa_icon is
     * the norm, not the exception, and must reach the browser intact.
     */
    public function test_multi_class_fa_icon_survives_intact(): void
    {
        $output = $this->render([
            'iconset'   => [$this->iconItem(['fa_icon' => 'fa-solid fa-leaf'])],
            'md_col'    => 3,
            'classname' => '',
        ]);

        $this->assertStringContainsString('class="fa fa-solid fa-leaf fa-3x"', $output);
    }

    public function test_escapes_markup_in_link_title(): void
    {
        $output = $this->render([
            'iconset'   => [$this->iconItem(['link_title' => '<script>alert(1)</script>'])],
            'md_col'    => 3,
            'classname' => '',
        ]);

        // kses removes the disallowed tag and keeps its text, where
        // esc_html() would have shown a literal "&lt;script&gt;". Both are
        // inert in a text node; this asserts the kses behaviour so a
        // silent switch back to esc_html() fails here.
        $this->assertStringNotContainsString('<script', $output);
        $this->assertStringNotContainsString('&lt;script&gt;', $output);
        $this->assertStringContainsString('alert(1)', $output);
    }

    /**
     * "Boards & Commissions" is a real link_title value. The ampersand must be
     * encoded exactly once.
     */
    public function test_ampersand_in_link_title_is_encoded_once(): void
    {
        $output = $this->render([
            'iconset'   => [$this->iconItem(['link_title' => 'Boards & Commissions'])],
            'md_col'    => 3,
            'classname' => '',
        ]);

        $this->assertStringContainsString('Boards &amp; Commissions', $output);
        $this->assertStringNotContainsString('&amp;amp;', $output);
    }
}
