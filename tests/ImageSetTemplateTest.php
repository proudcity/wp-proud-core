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
    private const CORE_PLUGIN_FILE     = __DIR__ . '/../wp-proud-core.php';

    /**
     * Attachment resolution, driven per test (#2934).
     *
     * Defaults reproduce the original fixed stubs: no src, so
     * print_responsive_image() emits nothing and the escaping tests below
     * stay focused on the anchors. A test that needs to inspect the <img>
     * sets these in its own body before calling render().
     *
     * @var array|false
     */
    private $attachmentSrc = false;
    private string $attachmentSrcset = '';
    private string $attachmentSizes = '';

    /**
     * The registered image size each helper was asked for, captured so the
     * srcset/sizes tests can prove the template still requests 'card-thumb'
     * rather than silently switching to another size.
     */
    private $srcsetRequestedSize = null;
    private $sizesRequestedSize = null;

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
        //
        // #2934 added the srcset/sizes assertions, which need a real <img> to
        // inspect. The three stubs read from instance properties rather than
        // returning a fixed value so an individual test can opt into a
        // resolvable attachment without redefining the stub. The defaults are
        // unchanged from before, so every pre-existing test still renders no
        // <img> at all.
        Functions\when('wp_get_attachment_image_src')->alias(function () {
            return $this->attachmentSrc;
        });
        Functions\when('wp_get_attachment_image_srcset')->alias(function ($id, $size) {
            $this->srcsetRequestedSize = $size;
            return $this->attachmentSrcset;
        });
        Functions\when('wp_get_attachment_image_sizes')->alias(function ($id, $size) {
            $this->sizesRequestedSize = $size;
            return $this->attachmentSizes;
        });
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
        // #2934: the thumbnail anchor now renders only when the attachment
        // resolves, so this test supplies one. It is about target/rel landing
        // on both anchors, not about what happens to a deleted attachment --
        // that case has its own coverage below.
        $this->resolvableAttachment();

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
        // #2934: the thumbnail anchor now renders only when the attachment
        // resolves, so this test supplies one. It is about target/rel landing
        // on both anchors, not about what happens to a deleted attachment --
        // that case has its own coverage below.
        $this->resolvableAttachment();

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
        // #2934: the thumbnail anchor now renders only when the attachment
        // resolves, so this test supplies one. It is about target/rel landing
        // on both anchors, not about what happens to a deleted attachment --
        // that case has its own coverage below.
        $this->resolvableAttachment();

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
    // -----------------------------------------------------------------
    // Full-bleed card display (#2934)
    // -----------------------------------------------------------------
    //
    // The issue asks for the image to fill the card edge to edge and for the
    // card border to go away. The border, the inset box-shadow and the
    // centred 300px image all come from proudcity-patterns CSS that every
    // other card on the site shares, so the styling cannot simply be edited
    // there. The template instead opts in by name and the theme scopes the
    // overrides to that hook class.
    //
    // These templates are NOT child-theme overridable -- printWidget() builds
    // the path with plugin_dir_path(__FILE__) rather than locate_template() --
    // so this one file is the only place the class can come from.

    public function test_cards_wrapper_carries_the_image_set_hook_class(): void
    {
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        $this->assertStringContainsString(
            'image-set-cards',
            $output,
            'The theme scopes the full-bleed overrides to .image-set-cards; without this class on the wrapper the CSS matches nothing.'
        );
    }

    /**
     * The hook class must not displace the column classes -- those drive the
     * 2-up/3-up layout and are shared with every other card grid.
     */
    public function test_cards_hook_class_does_not_replace_the_column_classes(): void
    {
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        foreach (['card-columns', 'card-columns-xs-2', 'card-columns-sm-2', 'card-columns-md-3', 'card-columns-equalize'] as $class) {
            $this->assertStringContainsString($class, $output, "Layout class {$class} must survive.");
        }
    }

    public function test_cards_across_two_still_selects_the_two_column_class(): void
    {
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '2',
        ]);

        $this->assertStringContainsString('card-columns-md-2', $output);
        $this->assertStringNotContainsString('card-columns-md-3', $output);
        $this->assertStringContainsString('image-set-cards', $output);
    }

    /**
     * The hook class belongs on the grid wrapper, not on each card, so the
     * theme can scope with two classes and still beat the child themes'
     * later-loaded patterns copy.
     */
    public function test_cards_hook_class_appears_once_regardless_of_item_count(): void
    {
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [
                $this->imageItem(['link_title' => 'A']),
                $this->imageItem(['link_title' => 'B']),
                $this->imageItem(['link_title' => 'C']),
            ],
            'across'   => '3',
        ]);

        $this->assertSame(1, substr_count($output, 'image-set-cards'));
    }

    /**
     * media-list.php is the widget's other display mode and is not part of
     * this issue -- it must not pick the class up.
     */
    public function test_list_display_does_not_carry_the_cards_hook_class(): void
    {
        $output = $this->render(self::MEDIA_LIST_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('image-set-cards', $output);
    }

    // -----------------------------------------------------------------
    // Responsive image sizing (#2934 tier 2)
    // -----------------------------------------------------------------
    //
    // Going full bleed makes the rendered image wider than it used to be: it
    // now fills the whole card rather than sitting at its intrinsic 300px
    // inside it. WordPress's generated sizes attribute for 'card-thumb' is
    // "(max-width: 300px) 100vw, 300px", which tells the browser the image
    // never exceeds 300 CSS px. That understates the new layout, so the
    // template declares the real one.

    private function resolvableAttachment(): void
    {
        $this->attachmentSrc    = ['https://example.com/wp-content/uploads/pic-300x170.jpg', 300, 170, true];
        $this->attachmentSrcset = 'https://example.com/wp-content/uploads/pic-300x170.jpg 300w, https://example.com/wp-content/uploads/pic-600x340.jpg 600w';
        $this->attachmentSizes  = '(max-width: 300px) 100vw, 300px';
    }

    public function test_cards_declares_the_full_bleed_sizes_rather_than_the_wordpress_default(): void
    {
        $this->resolvableAttachment();

        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        $this->assertStringContainsString(
            'sizes="(max-width: 779px) 50vw, 340px"',
            $output,
            'The template must override the generated sizes attribute now that the image fills the card.'
        );
        $this->assertStringNotContainsString(
            'sizes="(max-width: 300px) 100vw, 300px"',
            $output,
            'The WordPress-generated value understates the rendered width and would keep the browser on the 300w candidate.'
        );
    }

    /**
     * Overriding sizes must not disturb srcset -- that is what actually
     * offers the browser the larger candidate.
     */
    public function test_cards_passes_the_srcset_through_untouched(): void
    {
        $this->resolvableAttachment();

        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        $this->assertStringContainsString('pic-600x340.jpg 600w', $output);
        $this->assertStringContainsString('pic-300x170.jpg 300w', $output);
    }

    /**
     * The srcset is still built from the 'card-thumb' family. A candidate only
     * joins a srcset if it shares the requested size's aspect ratio, which is
     * why card-thumb-lg is registered at exactly 2x card-thumb.
     */
    public function test_cards_still_requests_the_card_thumb_size(): void
    {
        $this->resolvableAttachment();

        $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        $this->assertSame('card-thumb', $this->srcsetRequestedSize);
    }

    /**
     * An attachment that resolves to nothing must not gain a stray sizes
     * attribute -- print_responsive_image() emits no <img> at all in that
     * case, and the override must not change that.
     */
    public function test_cards_unresolvable_attachment_emits_no_img(): void
    {
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringNotContainsString('sizes=', $output);
    }

    // -----------------------------------------------------------------
    // card-thumb-lg registration (#2934 tier 2)
    // -----------------------------------------------------------------
    //
    // wp_calculate_image_srcset() only offers a candidate whose aspect ratio
    // matches the requested size, within a one-pixel rounding allowance. Today
    // card-thumb (300x170) is the only registered size at 1.7647, so the image
    // set has no second candidate and its srcset comes back empty. card-thumb-lg
    // must therefore be exactly 2x card-thumb, and both must be hard crops --
    // a soft crop would let a portrait original produce an off-ratio file that
    // is then excluded anyway.
    //
    // This reads the plugin source rather than calling addImageSizes(): the
    // method lives on Proudcore, whose file instantiates the plugin and pulls
    // in every module at include time. Loading that into the unit harness to
    // assert two numbers is not worth it. The invariant being locked is the
    // arithmetic relationship between the two registrations, and that is
    // visible in the source.

    private function registeredImageSize(string $name): array
    {
        $source = file_get_contents(self::CORE_PLUGIN_FILE);
        $this->assertNotFalse($source, 'Cannot read wp-proud-core.php.');

        $pattern = '/add_image_size\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(true|false)\s*\)/';
        $this->assertSame(1, preg_match($pattern, $source, $m), "Expected exactly one add_image_size() call for '{$name}'.");

        return [
            'width'  => (int) $m[1],
            'height' => (int) $m[2],
            'crop'   => $m[3] === 'true',
        ];
    }

    public function test_card_thumb_lg_is_registered(): void
    {
        $lg = $this->registeredImageSize('card-thumb-lg');

        $this->assertSame(600, $lg['width']);
        $this->assertSame(340, $lg['height']);
    }

    public function test_card_thumb_lg_matches_card_thumb_aspect_ratio_exactly(): void
    {
        $base = $this->registeredImageSize('card-thumb');
        $lg   = $this->registeredImageSize('card-thumb-lg');

        $this->assertSame(
            $base['width'] * $lg['height'],
            $base['height'] * $lg['width'],
            'card-thumb-lg must share card-thumb\'s aspect ratio or wp_calculate_image_srcset() drops it from the srcset.'
        );
        $this->assertGreaterThan(
            $base['width'],
            $lg['width'],
            'card-thumb-lg exists to be the larger candidate.'
        );
    }

    public function test_both_card_thumb_sizes_are_hard_crops(): void
    {
        $this->assertTrue($this->registeredImageSize('card-thumb')['crop']);
        $this->assertTrue($this->registeredImageSize('card-thumb-lg')['crop'], 'A soft crop would produce an off-ratio file that srcset then excludes.');
    }
    // -----------------------------------------------------------------
    // Unresolvable attachment must not reserve a ratio box (#2934)
    // -----------------------------------------------------------------
    //
    // Before #2934 the .card-img-top wrapper was emitted whenever the stored
    // image value was numeric, whether or not the attachment still resolved.
    // With no <img> inside it the div collapsed to zero height and nobody
    // noticed. The full-bleed CSS gives that wrapper padding-top: 56.6667%,
    // which would turn a deleted attachment into a visible blank band above
    // the title on every card. The template now gates the wrapper on a
    // resolved src instead.

    public function test_cards_unresolvable_attachment_emits_no_ratio_box(): void
    {
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString(
            'card-img-top',
            $output,
            'An unresolved attachment must not leave a wrapper the CSS will give height to.'
        );
    }

    /**
     * The card itself must survive -- losing the image is not losing the link.
     */
    public function test_cards_unresolvable_attachment_still_renders_the_title_link(): void
    {
        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem(['link_title' => 'Still here'])],
            'across'   => '3',
        ]);

        $this->assertSame(1, substr_count($output, '<a href'), 'Only the title anchor should remain.');
        $this->assertStringContainsString('Still here', $output);
        $this->assertStringContainsString('card-block', $output);
    }

    public function test_cards_resolved_attachment_emits_the_ratio_box(): void
    {
        $this->resolvableAttachment();

        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        $this->assertStringContainsString('card-img-top', $output);
        $this->assertSame(2, substr_count($output, '<a href'), 'Thumbnail anchor plus title anchor.');
    }

    /**
     * A mixed set is the realistic case: one deleted attachment among several
     * good ones must cost only its own image, not the whole row.
     */
    public function test_cards_mixed_resolvable_and_unresolvable_items(): void
    {
        $this->attachmentSrc = false;

        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [
                $this->imageItem(['link_title' => 'A']),
                $this->imageItem(['link_title' => 'B']),
            ],
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('card-img-top', $output);
        $this->assertSame(2, substr_count($output, 'card-wrap'), 'Both cards still render.');
    }

    /**
     * A non-numeric stored image value took the same branch as a missing one
     * before this change and must keep doing so.
     */
    public function test_cards_non_numeric_image_value_emits_no_ratio_box(): void
    {
        $this->resolvableAttachment();

        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem(['image' => 'not-an-id'])],
            'across'   => '3',
        ]);

        $this->assertStringNotContainsString('card-img-top', $output);
    }

    // -----------------------------------------------------------------
    // sizes tracks the real breakpoint and the real column count (#2934)
    // -----------------------------------------------------------------
    //
    // The grid switches from card-columns-xs-2 to card-columns-sm-2 at
    // $screen-sm, which proudcity-patterns sets to 780px -- not Bootstrap's
    // stock 768px. The compiled rule is
    // "@media screen and (min-width:780px){.card-columns-sm-2>*{width:50%}}".
    // A 767px condition leaves 768-779px claiming a desktop width while the
    // layout is still two up.
    //
    // Above that breakpoint the card width depends on how many are across:
    // with $container-lg at 1064px and a 1.25rem column gap, three across is
    // roughly 330px of card and two across roughly 500px. A sidebar narrows
    // both, so these are upper bounds -- deliberately, because overstating
    // sizes costs bandwidth while understating it renders blurry.

    public function test_cards_sizes_breakpoint_matches_the_patterns_screen_sm(): void
    {
        $this->resolvableAttachment();

        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        $this->assertStringContainsString('(max-width: 779px) 50vw', $output);
        $this->assertStringNotContainsString('767px', $output, 'The grid breaks at 780px, not Bootstrap stock 768px.');
    }

    public function test_cards_sizes_reflects_three_across(): void
    {
        $this->resolvableAttachment();

        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '3',
        ]);

        $this->assertStringContainsString('sizes="(max-width: 779px) 50vw, 340px"', $output);
    }

    public function test_cards_sizes_reflects_two_across(): void
    {
        $this->resolvableAttachment();

        $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
            'imageset' => [$this->imageItem()],
            'across'   => '2',
        ]);

        $this->assertStringContainsString('sizes="(max-width: 779px) 50vw, 500px"', $output);
    }

    /**
     * $across drives both the column class and the declared width, so they
     * must never disagree.
     */
    public function test_cards_sizes_and_column_class_agree(): void
    {
        $this->resolvableAttachment();

        foreach ([['3', 'card-columns-md-3', '340px'], ['2', 'card-columns-md-2', '500px']] as [$across, $columnClass, $width]) {
            $output = $this->render(self::IMAGE_CARDS_TEMPLATE, [
                'imageset' => [$this->imageItem()],
                'across'   => $across,
            ]);

            $this->assertStringContainsString($columnClass, $output);
            $this->assertStringContainsString(', ' . $width . '"', $output);
        }
    }
}
