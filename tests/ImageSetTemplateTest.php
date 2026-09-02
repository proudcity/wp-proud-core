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
    private const IMAGE_CARDS_TEMPLATE = __DIR__ . '/../modules/proud-widget/widgets/image-set/templates/image-cards.php';
    private const MEDIA_LIST_TEMPLATE  = __DIR__ . '/../modules/proud-widget/widgets/image-set/templates/media-list.php';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

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
}
