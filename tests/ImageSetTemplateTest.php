<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the image-set widget's image-cards.php and media-list.php
 * templates.
 *
 * Covers issue #1825: a per-item "Open in new tab" checkbox
 * (target="_blank" rel="noopener") plus esc_link_url() hardening on the
 * link_url output. esc_link_url() (proud-helpers.php) replaces the bare
 * esc_url() from round one -- esc_url() alone would prepend "http://" to
 * a legitimate relative link_url such as "topics/homelessness/", breaking
 * it. See EscLinkUrlTest.php for direct coverage of that helper.
 */

if (!class_exists('ImageSet')) {
    /**
     * Minimal stand-in for the real ImageSet widget class, which extends
     * WP_Widget and cannot be loaded under test. media-list.php only calls
     * these two static helpers.
     */
    class ImageSet
    {
        public static function row_open($current, $columns)
        {
            return $current % $columns === 0
                 ? '<div class="row">'
                 : '';
        }

        public static function row_close($current, $post_count, $columns)
        {
            return (($post_count - 1) === $current) || ($current % $columns === ($columns - 1))
                 ? '</div>'
                 : '';
        }
    }
}

class ImageSetTemplateTest extends TestCase
{
    use AppliesPreKsesFilter;

    private const IMAGE_CARDS_TEMPLATE = __DIR__ . '/../modules/proud-widget/widgets/image-set/templates/image-cards.php';
    private const MEDIA_LIST_TEMPLATE  = __DIR__ . '/../modules/proud-widget/widgets/image-set/templates/media-list.php';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->applyPreKsesFilter();

        // Realistic esc_url() stub: the default test stub is a passthrough,
        // which would let a javascript: URL through and hide a missing
        // esc_url() call. Mimic WordPress's protocol whitelist behaviour
        // closely enough to prove esc_url() is actually wired up -- including
        // the "http://" prepending WordPress applies to any schemeless value
        // that doesn't start with "/", "#" or "?". A passthrough stub would
        // make the relative-path regression tests below prove nothing, since
        // it's exactly that prepending which esc_link_url() must route around.
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

        // esc_attr() stub, faithful to WordPress's real behaviour: it calls
        // _wp_specialchars() with double_encode = false, which preserves an
        // existing character reference rather than re-encoding its "&". A
        // plain htmlspecialchars() call with default arguments double-encodes
        // and would hide that behaviour. esc_link_url() itself no longer
        // calls esc_attr() for pure relative paths (it uses htmlspecialchars()
        // directly with double_encode = true -- see EscLinkUrlTest.php), but
        // other template output still goes through esc_attr() directly.
        Functions\when('esc_attr')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });

        // esc_html() stub, faithful to WordPress: _wp_specialchars() with
        // double_encode = false. The stubs.php default is a passthrough, which
        // would let an unescaped link_title sail through every assertion below.
        Functions\when('esc_html')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });

        // print_responsive_image()/build_responsive_image_meta() are loaded
        // via proud-helpers.php in the bootstrap. Returning an empty value
        // from wp_get_attachment_image_src() makes print_responsive_image()
        // output nothing, keeping assertions focused on the anchors.

        Functions\when('wp_get_attachment_image_src')->justReturn(false);
        Functions\when('wp_get_attachment_image_srcset')->justReturn('');
        Functions\when('wp_get_attachment_image_sizes')->justReturn('');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Render a template with the supplied variables and return its output.
     */
    private function render(string $template, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include $template;
        return (string) ob_get_clean();
    }

    private function imageItem(array $overrides = []): array
    {
        return array_merge([
            'link_title' => 'Item title',
            'link_url'   => 'https://example.com/item',
            'image'      => 42,
            'text'       => '',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // image-cards.php
    // -----------------------------------------------------------------

    public function test_cards_external_checked_adds_target_and_rel_to_both_anchors(): void
    {
        $imageset = [$this->imageItem(['external' => '1'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertSame(2, substr_count($output, '<a href'), 'Expected two anchors (thumbnail + title).');
        $this->assertSame(2, substr_count($output, 'target="_blank"'), 'Both anchors must open in a new tab.');
        $this->assertSame(2, substr_count($output, 'rel="noopener"'), 'Both anchors must carry rel="noopener".');
    }

    public function test_cards_external_explicitly_unchecked_renders_no_target(): void
    {
        $imageset = [$this->imageItem(['external' => '0'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('target="_blank"', $output, "'0' must be treated as falsy.");
        $this->assertStringNotContainsString('rel="noopener"', $output);
    }

    public function test_cards_external_key_absent_renders_no_target(): void
    {
        $imageset = [$this->imageItem()];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('target="_blank"', $output, 'Legacy rows saved before this field existed must not warn or render target.');
        $this->assertStringNotContainsString('rel="noopener"', $output);
    }

    public function test_cards_external_is_per_item_not_set_wide(): void
    {
        $imageset = [
            $this->imageItem(['link_title' => 'A', 'external' => '1']),
            $this->imageItem(['link_title' => 'B']),
        ];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertSame(2, substr_count($output, 'target="_blank"'), 'Only item A (two anchors) should open in a new tab.');
        $this->assertSame(2, substr_count($output, 'rel="noopener"'));
    }

    public function test_cards_rel_appears_exactly_where_target_does(): void
    {
        $imageset = [
            $this->imageItem(['link_title' => 'A', 'external' => '1']),
            $this->imageItem(['link_title' => 'B']),
        ];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertSame(
            substr_count($output, 'target="_blank"'),
            substr_count($output, 'rel="noopener"'),
            'rel="noopener" must appear exactly where target="_blank" does.'
        );
        $this->assertMatchesRegularExpression('/target="_blank" rel="noopener"/', $output);
        // No stray rel="noopener" without an adjoining target="_blank".
        $this->assertSame(0, preg_match_all('/rel="noopener"/', $output) - preg_match_all('/target="_blank" rel="noopener"/', $output));
    }

    public function test_cards_escapes_javascript_url_in_href(): void
    {
        $imageset = [$this->imageItem(['link_url' => 'javascript:alert(1)'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('javascript:alert(1)', $output, 'esc_url() must neutralize a javascript: URL.');
    }

    /**
     * Regression lock for #1825 follow-up: image-cards.php must call
     * esc_link_url(), not a bare esc_url(). A decimal character reference
     * has no literal ":" for esc_url() to strip, so a regression back to
     * esc_url() would let "javascript&#58;alert(1)" through as a live
     * javascript: scheme once the browser decodes the entity. See
     * EscLinkUrlTest.php for direct coverage of esc_link_url() itself.
     */
    public function test_cards_entity_encoded_colon_link_url_is_rendered_via_esc_link_url(): void
    {
        $imageset = [$this->imageItem(['link_url' => 'javascript&#58;alert(1)'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString('&amp;#58;', $output);
    }

    // -----------------------------------------------------------------
    // media-list.php
    // -----------------------------------------------------------------

    public function test_list_external_checked_adds_target_and_rel_to_both_anchors(): void
    {
        $imageset = [$this->imageItem(['external' => '1'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertSame(2, substr_count($output, '<a href'), 'Expected two anchors (thumbnail + heading).');
        $this->assertSame(2, substr_count($output, 'target="_blank"'));
        $this->assertSame(2, substr_count($output, 'rel="noopener"'));
    }

    public function test_list_external_explicitly_unchecked_renders_no_target(): void
    {
        $imageset = [$this->imageItem(['external' => '0'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('target="_blank"', $output);
        $this->assertStringNotContainsString('rel="noopener"', $output);
    }

    public function test_list_external_key_absent_renders_no_target(): void
    {
        $imageset = [$this->imageItem()];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('target="_blank"', $output);
        $this->assertStringNotContainsString('rel="noopener"', $output);
    }

    public function test_list_escapes_javascript_url_in_href(): void
    {
        $imageset = [$this->imageItem(['link_url' => 'javascript:alert(1)'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('javascript:alert(1)', $output);
    }

    /**
     * Regression lock for #1825 follow-up: media-list.php must call
     * esc_link_url(), not a bare esc_url(). A decimal character reference
     * has no literal ":" for esc_url() to strip, so a regression back to
     * esc_url() would let "javascript&#58;alert(1)" through as a live
     * javascript: scheme once the browser decodes the entity. See
     * EscLinkUrlTest.php for direct coverage of esc_link_url() itself.
     */
    public function test_list_entity_encoded_colon_link_url_is_rendered_via_esc_link_url(): void
    {
        $imageset = [$this->imageItem(['link_url' => 'javascript&#58;alert(1)'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString('&amp;#58;', $output);
    }

    // -----------------------------------------------------------------
    // Relative link_url regression lock (#1825 follow-up)
    // -----------------------------------------------------------------

    public function test_cards_relative_link_url_renders_unchanged_in_href(): void
    {
        $imageset = [$this->imageItem(['link_url' => 'topics/homelessness/'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString(
            'href="topics/homelessness/"',
            $output,
            'A relative link_url must not gain an http:// prefix.'
        );
        $this->assertStringNotContainsString('http://topics', $output);
    }

    public function test_list_relative_link_url_renders_unchanged_in_href(): void
    {
        $imageset = [$this->imageItem(['link_url' => 'documents/approved-street-trees/'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString(
            'href="documents/approved-street-trees/"',
            $output,
            'A relative link_url must not gain an http:// prefix.'
        );
        $this->assertStringNotContainsString('http://documents', $output);
    }

    // -----------------------------------------------------------------
    // link_title / text escaping (#2916)
    // -----------------------------------------------------------------
    //
    // link_title is a text node one line below an href that #1825 already
    // hardened. It gets esc_html().
    //
    // text gets esc_html() too. It is a single-line "Description (optional)"
    // input (#type => text) holding a card blurb, not page-builder HTML. On a
    // production database copy every one of the 42 distinct values stored
    // across 88 ImageSet widgets / 339 items is plain text -- none contains a
    // tag. wp_kses_post() would therefore change nothing while still allowing
    // <iframe> and friends on a field that has never held markup. esc_html()
    // changes 3 values, all quote encoding that renders identically.
    //
    // Note the scoping. An earlier scan walked every 'text' key anywhere in
    // panels_data and counted 8124 values full of iframes and forms, but those
    // belong to SiteOrigin editor widgets, which these two templates never
    // render.

    public function test_cards_escapes_markup_in_link_title(): void
    {
        $imageset = [$this->imageItem(['link_title' => '<script>alert(1)</script>'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        // kses removes the disallowed tag and keeps its text, where
        // esc_html() would have shown a literal "&lt;script&gt;". Both are
        // inert in a text node; this asserts the kses behaviour so a
        // silent switch back to esc_html() fails here.
        $this->assertStringNotContainsString('<script', $output);
        $this->assertStringNotContainsString('&lt;script&gt;', $output);
        $this->assertStringContainsString('alert(1)', $output);
    }

    public function test_list_escapes_markup_in_link_title(): void
    {
        $imageset = [$this->imageItem(['link_title' => '<script>alert(1)</script>'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
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
     * "Boards & Commissions" and "Finance & tax" are real link_title values.
     * esc_html() must encode the ampersand once so the browser renders it
     * back as "&" -- not double-encode it into a visible "&amp;".
     */
    public function test_cards_ampersand_in_link_title_is_encoded_once(): void
    {
        $imageset = [$this->imageItem(['link_title' => 'Boards & Commissions'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString('Boards &amp; Commissions', $output);
        $this->assertStringNotContainsString('&amp;amp;', $output);
    }

    public function test_list_ampersand_in_link_title_is_encoded_once(): void
    {
        $imageset = [$this->imageItem(['link_title' => 'Finance & tax'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString('Finance &amp; tax', $output);
        $this->assertStringNotContainsString('&amp;amp;', $output);
    }

    public function test_cards_escapes_markup_in_text(): void
    {
        $imageset = [$this->imageItem(['text' => '<script>alert(1)</script>'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function test_list_escapes_markup_in_text(): void
    {
        $imageset = [$this->imageItem(['text' => '<script>alert(1)</script>'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    /**
     * A stored double quote must not be able to close the enclosing element's
     * attribute context or open a tag of its own.
     */
    public function test_cards_text_neutralizes_attribute_breakout(): void
    {
        $imageset = [$this->imageItem(['text' => '"><img src=x onerror=alert(1)>'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $output);
    }

    public function test_list_text_neutralizes_attribute_breakout(): void
    {
        $imageset = [$this->imageItem(['text' => '"><img src=x onerror=alert(1)>'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $output);
    }

    /**
     * The only real-world change esc_html() makes to this field. Three stored
     * values on #12351 carry an apostrophe or a quoted word; both encode to
     * character references the browser renders back as the original glyph.
     * Encoding must happen once -- a double-encoded "&amp;#039;" would be
     * visible on the page.
     */
    public function test_cards_text_encodes_quotes_once(): void
    {
        $imageset = [$this->imageItem([
            'text' => 'They\'re marked with bike symbols called "sharrows."',
        ])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString('They&#039;re marked with bike symbols called &quot;sharrows.&quot;', $output);
        $this->assertStringNotContainsString('&amp;#039;', $output);
    }

    /**
     * Ampersands in the blurb encode once, same contract as link_title.
     */
    public function test_list_text_ampersand_is_encoded_once(): void
    {
        $imageset = [$this->imageItem(['text' => 'Parks & Recreation programs'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString('Parks &amp; Recreation programs', $output);
        $this->assertStringNotContainsString('&amp;amp;', $output);
    }

    /**
     * media-list.php falls back to a literal "&nbsp" when text is empty. That
     * literal is template markup, not stored data -- only the stored value is
     * routed through esc_html(), so the fallback must still render as the raw
     * entity rather than being encoded into a visible "&amp;nbsp".
     */
    public function test_list_empty_text_still_renders_the_nbsp_fallback(): void
    {
        $imageset = [$this->imageItem(['text' => ''])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString('<p>&nbsp</p>', $output);
    }

    /**
     * The reason link_title uses wp_kses() rather than esc_html(): page
     * #10131 stores "Report illegal <br> camping " in this field, and the
     * fleet is 100+ sites of customer-authored content.
     */
    public function test_cards_link_title_keeps_a_line_break(): void
    {
        $imageset = [$this->imageItem(['link_title' => 'Report illegal <br> camping'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString('Report illegal <br> camping', $output);
    }

    public function test_list_link_title_keeps_a_line_break(): void
    {
        $imageset = [$this->imageItem(['link_title' => 'Report illegal <br> camping'])];
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringContainsString('Report illegal <br> camping', $output);
    }

    /**
     * A <br> is allowed; an event handler riding on it is not.
     */
    public function test_cards_link_title_br_carries_no_attributes(): void
    {
        $imageset = [$this->imageItem(['link_title' => 'Report<br onmouseover="alert(1)"> camping'])];
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => $imageset,
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('onmouseover', $output);
        $this->assertStringContainsString('Report<br> camping', $output);
    }
}
