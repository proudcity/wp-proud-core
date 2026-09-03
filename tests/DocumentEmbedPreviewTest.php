<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use function Proud\Core\proud_html_preview_is_resolving_source;

/**
 * Tests for the Embed Document widget template (issue #2917).
 *
 * content-embed-document.php hands its document URL to two consumers that both
 * need the real file: the Download button, and a third-party viewer
 * (docs.google.com/gview or view.officeapps.live.com) that fetches the URL
 * server-side. A preview provider replacing `document` meta on the front end
 * broke both.
 *
 * The template is exercised through proud_document_preview_callback(), which
 * includes it inside an output buffer -- the same include the widget itself
 * does at document-widget.class.php:144.
 */
class DocumentEmbedPreviewTest extends TestCase
{
    /**
     * The exact gview iframe the template emitted before #2917. Any change to
     * this string on a site with no preview provider is a regression -- most
     * customers do not run one.
     */
    private const BASELINE_GVIEW_IFRAME = '    <iframe src="//docs.google.com/gview?url=https://example.test/uploads/budget.pdf&embedded=true" id="doc-preview" style="width:100%; max-width:600px; height:400px;;" frameborder="0" ></iframe>';

    /**
     * Same, for the Office viewer branch.
     */
    private const BASELINE_OFFICE_IFRAME = '    <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=https://example.test/uploads/budget.docx" style="width:100%; max-width:600px; height:400px;;" frameborder="0"></iframe>';

    /**
     * Stand-in for a FileToWeb replacement URL. All three shapes the plugin can
     * return -- durable preview endpoint, local fallback endpoint, and an
     * approved native page permalink -- have no file extension in their path.
     */
    private const PREVIEW_URL = 'https://example.test/?proud_html_preview=42&preview_token=abc&preview_source=def';

    /**
     * Registered `proud_document_embed_preview` callback for the current test,
     * or null when no provider is active.
     *
     * @var callable|null
     */
    private $previewFilter;

    /**
     * Meta values the template should see, keyed by meta key.
     *
     * @var array<string, string>
     */
    private array $meta;

    /**
     * Whether a preview provider is replacing `document` meta, the way
     * FileToWeb's Link_Rewriter::filter_document_meta() does.
     */
    private bool $providerReplacesMeta = false;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->previewFilter = null;
        $this->providerReplacesMeta = false;
        $this->meta = [
            'document' => 'https://example.test/uploads/budget.pdf',
            'document_filename' => 'budget.pdf',
            'document_meta' => '',
        ];

        $GLOBALS['proud_html_preview_resolving_source'] = 0;

        $_GET['post_id'] = '42';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_die')->justReturn(null);
        Functions\when('absint')->alias(static fn ($value): int => abs((int) $value));
        Functions\when('wp_get_post_terms')->justReturn([]);
        Functions\when('get_the_title')->justReturn('Budget Report 2024');
        Functions\when('get_permalink')->justReturn('https://example.test/documents/budget/');
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('Proud\Document\get_document_icon')->justReturn('fa-file-pdf-o');

        // wp_kses() runs its input through the `pre_kses` filter, and
        // wp_pre_kses_less_than_callback() escapes via esc_html(). A
        // passthrough esc_html() turns that filter into a no-op, so "<script"
        // would be parsed as a real tag. See tests/AppliesPreKsesFilter.php.
        Functions\when('esc_html')->alias(
            static fn ($text): string => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false)
        );

        Functions\when('apply_filters')->alias(function ($tag, $value = null, ...$args) {
            if ('pre_kses' === $tag && function_exists('wp_pre_kses_less_than')) {
                return wp_pre_kses_less_than($value);
            }

            if ('proud_document_embed_preview' === $tag && null !== $this->previewFilter) {
                return ($this->previewFilter)($value, ...$args);
            }

            return $value;
        });

        // Mirrors FileToWeb's Link_Rewriter::filter_document_meta(), including
        // the guard it needs to add for #2917: stand down while ProudCity is
        // resolving an original source.
        Functions\when('get_post_meta')->alias(function ($post_id, $key = '', $single = false) {
            if ('document' === $key
                && $this->providerReplacesMeta
                && !proud_html_preview_is_resolving_source()) {
                return self::PREVIEW_URL;
            }

            return $this->meta[$key] ?? '';
        });
    }

    protected function tearDown(): void
    {
        $GLOBALS['proud_html_preview_resolving_source'] = 0;

        unset($_GET['post_id']);

        \Patchwork\restoreAll();
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Render the template the way the widget does and return its output.
     */
    private function render(string $filetype = 'pdf'): string
    {
        Functions\when('Proud\Document\get_document_type')->justReturn($filetype);

        $payload = [];
        Functions\when('wp_send_json')->alias(static function (array $data) use (&$payload): void {
            $payload = $data;
        });

        proud_document_preview_callback();

        $this->assertArrayHasKey('html', $payload, 'The callback must return rendered template HTML.');

        return $payload['html'];
    }

    /**
     * Render the template the way DocumentWidget::printWidget() does -- by
     * including it directly with whatever $id the widget instance produced.
     *
     * The AJAX endpoint casts $_GET['post_id'] with (int), so driving every
     * test through render() would never exercise what the widget itself
     * passes. printWidget() assigns $id from preg_replace(), which returns a
     * string even for a bare numeric instance value.
     *
     * @param mixed $id Post ID exactly as the widget would supply it.
     */
    private function renderTemplateDirectly($id): string
    {
        Functions\when('Proud\Document\get_document_type')->justReturn('pdf');

        ob_start();
        include __DIR__ . '/../modules/proud-widget/widgets/document/templates/content-embed-document.php';

        return (string) ob_get_clean();
    }

    // ---------------------------------------------------------------- Phase 1

    /**
     * The unchanged case, and the one that covers most customers: no preview
     * provider, a real PDF URL, and the same iframe the template has always
     * emitted.
     */
    public function test_pdf_document_renders_the_unchanged_google_viewer_iframe(): void
    {
        $html = $this->render('pdf');

        $this->assertStringContainsString(self::BASELINE_GVIEW_IFRAME, $html);
    }

    /**
     * Same for the Office viewer branch.
     */
    public function test_office_document_renders_the_unchanged_office_viewer_iframe(): void
    {
        $this->meta['document'] = 'https://example.test/uploads/budget.docx';
        $this->meta['document_filename'] = 'budget.docx';

        $html = $this->render('docx');

        $this->assertStringContainsString(self::BASELINE_OFFICE_IFRAME, $html);
    }

    /**
     * The reported bug. A provider replacement URL has no extension, so it can
     * never be the PDF Google Viewer is being asked to render. Show no preview
     * rather than "No preview available".
     *
     * This is why the guard is a positive match rather than "both present and
     * different": every replacement shape is extensionless, so a truthiness
     * check on the URL extension would never fire.
     */
    public function test_extensionless_url_is_not_sent_to_google_viewer(): void
    {
        $this->meta['document'] = self::PREVIEW_URL;

        $html = $this->render('pdf');

        $this->assertStringNotContainsString('docs.google.com/gview', $html);
    }

    /**
     * Same guard on the Office viewer, which fetches the URL the same way.
     */
    public function test_extensionless_url_is_not_sent_to_office_viewer(): void
    {
        $this->meta['document'] = self::PREVIEW_URL;
        $this->meta['document_filename'] = 'budget.docx';

        $html = $this->render('docx');

        $this->assertStringNotContainsString('view.officeapps.live.com', $html);
    }

    /**
     * A URL whose extension disagrees with the advertised type is equally
     * unusable to the viewer.
     */
    public function test_mismatched_extension_is_not_sent_to_google_viewer(): void
    {
        $this->meta['document'] = 'https://example.test/uploads/budget.html';

        $html = $this->render('pdf');

        $this->assertStringNotContainsString('docs.google.com/gview', $html);
    }

    /**
     * Suppressing the preview must not suppress the download, which is the
     * part that still works.
     */
    public function test_download_link_still_renders_when_the_preview_is_suppressed(): void
    {
        $this->meta['document'] = self::PREVIEW_URL;

        $html = $this->render('pdf');

        $this->assertStringNotContainsString('docs.google.com/gview', $html);
        $this->assertStringContainsString('href="' . self::PREVIEW_URL . '"', $html);
        $this->assertStringContainsString('Download', $html);
    }

    /**
     * Extensions are compared case-insensitively. get_document_type() already
     * lowercases the filename side; the URL side has to match.
     */
    public function test_uppercase_url_extension_still_previews(): void
    {
        $this->meta['document'] = 'https://example.test/uploads/BUDGET.PDF';

        $html = $this->render('pdf');

        $this->assertStringContainsString('docs.google.com/gview', $html);
    }

    /**
     * Query strings and fragments are not part of the path and must not defeat
     * the comparison. WP-Stateless appends a cache-busting query arg.
     */
    public function test_query_string_does_not_defeat_the_extension_check(): void
    {
        $this->meta['document'] = 'https://storage.googleapis.com/proudcity/site/uploads/budget.pdf?v=1738';

        $html = $this->render('pdf');

        $this->assertStringContainsString('docs.google.com/gview', $html);
    }

    // ---------------------------------------------------------------- Phase 2

    /**
     * The core of the fix. With a provider replacing `document` meta, both the
     * Download href and the viewer URL must still be the original PDF.
     */
    public function test_provider_replacement_does_not_reach_the_download_link_or_the_viewer(): void
    {
        $this->providerReplacesMeta = true;

        $html = $this->render('pdf');

        $this->assertStringNotContainsString(
            self::PREVIEW_URL,
            $html,
            'The provider replacement URL must not reach the template at all.'
        );
        $this->assertStringContainsString(
            'href="https://example.test/uploads/budget.pdf"',
            $html,
            'Download must point at the original file.'
        );
        $this->assertStringContainsString(self::BASELINE_GVIEW_IFRAME, $html);
    }

    /**
     * What happens against a provider that ignores the resolving-source flag.
     *
     * FileToWeb 0.1.53 and earlier checked the flag in filter_attachment_url()
     * but not in filter_document_meta(); 0.1.54 added it. Until that release is
     * pinned and rolled out, sites still run the unguarded version, so this is
     * live behaviour and not a hypothetical.
     *
     * Phase 1 protects the viewer either way -- the extension check does not
     * care why the URL is wrong. The Download link cannot be fixed from our
     * side, because nothing here can stand down a filter that never looks at
     * the flag. Keep this test: it is the contract we depend on a provider to
     * honour, and it should keep passing for any provider that does not.
     *
     * @see https://github.com/proudcity/wp-proudcity/issues/2917
     */
    public function test_provider_without_the_guard_still_breaks_the_download_link(): void
    {
        // Same replacement, but ignoring proud_html_preview_is_resolving_source().
        Functions\when('get_post_meta')->alias(function ($post_id, $key = '', $single = false) {
            if ('document' === $key) {
                return self::PREVIEW_URL;
            }

            return $this->meta[$key] ?? '';
        });

        $html = $this->render('pdf');

        $this->assertStringNotContainsString(
            'docs.google.com/gview',
            $html,
            'Phase 1 must protect the viewer even against an unguarded provider.'
        );
        $this->assertStringContainsString(
            'href="' . self::PREVIEW_URL . '"',
            $html,
            'Known gap: the Download link stays wrong until FileToWeb honours the guard.'
        );
    }

    /**
     * The flag must not be left raised after the template renders, or provider
     * replacement stays disabled for the rest of the request.
     */
    public function test_rendering_does_not_leak_the_resolving_source_flag(): void
    {
        $this->providerReplacesMeta = true;

        $this->render('pdf');

        $this->assertSame(0, $GLOBALS['proud_html_preview_resolving_source']);
    }

    // ---------------------------------------------------------------- Phase 3

    /**
     * With no provider filter registered, output is byte-identical to the
     * pre-change template. This is the regression guard for the majority of
     * sites, which run no preview provider at all.
     */
    public function test_output_is_unchanged_when_no_provider_filters_the_preview(): void
    {
        $html = $this->render('pdf');

        $this->assertStringContainsString(self::BASELINE_GVIEW_IFRAME . "\n", $html);
    }

    /**
     * The filter gets the default markup, the document ID, and the original
     * URL -- everything a provider needs to resolve its own preview without
     * core knowing how it stores one.
     */
    public function test_filter_receives_the_default_markup_the_id_and_the_original_url(): void
    {
        $received = [];

        $this->previewFilter = static function ($default, $id = null, $src = null) use (&$received) {
            $received = ['default' => $default, 'id' => $id, 'src' => $src];

            return $default;
        };

        $this->render('pdf');

        $this->assertStringContainsString('docs.google.com/gview', $received['default']);
        $this->assertSame(42, $received['id']);
        $this->assertSame('https://example.test/uploads/budget.pdf', $received['src']);
    }

    /**
     * The filter's documented contract says the second argument is an int, so
     * it has to be one on the path the widget actually uses.
     *
     * DocumentWidget::printWidget() sets $id from preg_replace(), which returns
     * a string even when the stored instance value is already numeric, so
     * without normalisation a provider receives '42' from a real page render
     * and 42 from the admin AJAX preview. Any provider using === or is_int()
     * would see them differently.
     */
    public function test_filter_receives_an_int_id_even_when_the_widget_passes_a_string(): void
    {
        $received = null;

        $this->previewFilter = static function ($default, $id = null) use (&$received) {
            $received = $id;

            return $default;
        };

        $this->renderTemplateDirectly('42');

        $this->assertSame(42, $received, 'The widget path must normalise $id before the filter runs.');
    }

    /**
     * The same normalisation must reach the document lookups themselves, not
     * only the filter argument.
     */
    public function test_string_id_from_the_widget_still_renders_the_document(): void
    {
        $html = $this->renderTemplateDirectly('42');

        $this->assertStringContainsString(self::BASELINE_GVIEW_IFRAME, $html);
        $this->assertStringContainsString('href="https://example.test/uploads/budget.pdf"', $html);
    }

    /**
     * A provider substituting its own preview replaces the third-party viewer
     * rather than rendering alongside it.
     */
    public function test_filter_replacement_wins_over_the_default_iframe(): void
    {
        $this->previewFilter = static fn (): string =>
            '<iframe src="https://example.test/?proud_html_preview=42" id="doc-preview"></iframe>';

        $html = $this->render('pdf');

        $this->assertStringContainsString('proud_html_preview=42', $html);
        $this->assertStringNotContainsString('docs.google.com/gview', $html);
    }

    /**
     * Returning an empty string is how a provider says "no preview here".
     */
    public function test_filter_returning_empty_string_suppresses_the_preview(): void
    {
        $this->previewFilter = static fn (): string => '';

        $html = $this->render('pdf');

        $this->assertStringNotContainsString('<iframe', $html);
    }

    /**
     * The filter fires even when we would show no preview, so a provider can
     * offer one for a type we do not handle.
     */
    public function test_filter_fires_when_there_is_no_default_preview(): void
    {
        $this->meta['document'] = 'https://example.test/uploads/notes.txt';
        $this->meta['document_filename'] = 'notes.txt';

        $received = 'not called';

        $this->previewFilter = static function ($default) use (&$received): string {
            $received = $default;

            return '<iframe src="https://example.test/?proud_html_preview=42"></iframe>';
        };

        $html = $this->render('txt');

        $this->assertSame('', $received, 'The default must be an empty string when no preview is shown.');
        $this->assertStringContainsString('proud_html_preview=42', $html);
    }

    /**
     * A filter is third-party code and this widget renders on public pages.
     * Provider markup is escaped; ours is not re-escaped.
     */
    public function test_filter_output_is_escaped(): void
    {
        $this->previewFilter = static fn (): string =>
            '<iframe src="https://example.test/preview" onload="alert(1)"></iframe><script>alert(2)</script>';

        $html = $this->render('pdf');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onload', $html);
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('https://example.test/preview', $html);
    }

    /**
     * The iframe attributes the template itself uses have to survive the kses
     * pass, or a provider returning the same shape we do would come out broken.
     */
    public function test_escaping_keeps_the_attributes_a_preview_iframe_needs(): void
    {
        $this->previewFilter = static fn (): string =>
            '<iframe src="https://example.test/preview" id="doc-preview" title="Budget" '
            . 'style="width:100%; height:400px;" frameborder="0" loading="lazy" sandbox="allow-scripts"></iframe>';

        $html = $this->render('pdf');

        foreach (['id="doc-preview"', 'title="Budget"', 'style=', 'frameborder="0"', 'loading="lazy"', 'sandbox='] as $needle) {
            $this->assertStringContainsString($needle, $html, "kses stripped {$needle}");
        }
    }

    /**
     * A filter returning a non-string must not be concatenated into the page.
     */
    public function test_non_string_filter_return_is_ignored(): void
    {
        $this->previewFilter = static fn () => ['not', 'markup'];

        $html = $this->render('pdf');

        $this->assertStringNotContainsString('Array', $html);
        $this->assertStringContainsString(self::BASELINE_GVIEW_IFRAME, $html);
    }
}
