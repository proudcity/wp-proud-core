<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use function Proud\Core\proud_html_preview_is_resolving_source;

/**
 * Document teasers must hand their Download button the original file (#2970).
 *
 * FileToWeb's Link_Rewriter::filter_document_meta() replaces `document` post
 * meta with its HTML rendition on public pages unless ProudCity raises the
 * resolving-source flag. The teaser list read the meta directly, so its
 * Download button pointed at the HTML preview while keeping
 * download="<file>.pdf" -- browsers saved the HTML page as a "corrupt" PDF.
 *
 * The provider is simulated here: get_post_meta() returns the preview URL
 * unless the flag is raised, which is exactly the contract FileToWeb honours.
 */
class TeaserDocumentDownloadUrlTest extends TestCase
{
    private const ORIGINAL = 'https://storage.googleapis.com/proudcity/site/2026/09/policy.pdf';
    private const PREVIEW = 'https://site.test/?proud_html_preview=7953&preview_token=abc';

    private string $template;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        require_once __DIR__ . '/../modules/proud-html-preview/proud-html-preview.php';
        require_once __DIR__ . '/../modules/proud-teasers/proud-teasers.php';

        $GLOBALS['proud_html_preview_resolving_source'] = 0;

        // Stand-in for a document teaser template: prints what the Download href gets.
        $this->template = tempnam(sys_get_temp_dir(), 'teaser') . '.php';
        file_put_contents($this->template, '<?php echo $src;');

        Functions\when('locate_template')->justReturn($this->template);
        Functions\when('wp_get_post_terms')->justReturn([]);
        Functions\when('get_post_meta')->alias(static function ($id, $key = '', $single = false) {
            if ('document' !== $key) {
                return '';
            }

            return proud_html_preview_is_resolving_source() ? self::ORIGINAL : self::PREVIEW;
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->template);
        $GLOBALS['proud_html_preview_resolving_source'] = 0;
        unset($GLOBALS['post']);

        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Guard against a vacuous pass: a plain read must get the preview URL,
     * or the stand-in provider isn't doing anything.
     */
    public function test_stand_in_provider_replaces_a_plain_read(): void
    {
        $this->assertSame(self::PREVIEW, get_post_meta(42, 'document', true));
    }

    public function test_document_teaser_download_src_is_the_original_file(): void
    {
        $this->assertSame(self::ORIGINAL, $this->renderDocumentTeaser('list'));
    }

    public function test_document_mini_teaser_download_src_is_the_original_file(): void
    {
        $this->assertSame(self::ORIGINAL, $this->renderDocumentTeaser('mini'));
    }

    public function test_flag_is_lowered_after_rendering(): void
    {
        $this->renderDocumentTeaser('list');

        $this->assertSame(0, $GLOBALS['proud_html_preview_resolving_source']);
    }

    /**
     * Runs TeaserList::print_content() for one document post and returns the
     * rendered $src.
     */
    private function renderDocumentTeaser(string $displayType): string
    {
        Functions\when('in_the_loop')->justReturn(true);

        $GLOBALS['post'] = (object) ['ID' => 42];

        $class = new ReflectionClass(\Proud\Core\TeaserList::class);
        $teasers = $class->newInstanceWithoutConstructor();

        foreach ([
            'post_type' => 'document',
            'display_type' => $displayType,
            'query_args' => [],
            'hide' => [],
            'query' => new class {
                public function the_post(): void
                {
                }
            },
        ] as $property => $value) {
            $prop = $class->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($teasers, $value);
        }

        $print = $class->getMethod('print_content');
        $print->setAccessible(true);

        ob_start();
        $print->invoke($teasers, 1, 1, 'teasers-test');

        return (string) ob_get_clean();
    }
}
