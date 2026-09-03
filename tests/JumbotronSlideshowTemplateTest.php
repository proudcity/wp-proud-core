<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the jumbotron-header widget's slideshow template -- found during
 * the #2916 security review and folded into that issue as an extra finding.
 *
 * jumbotron-slideshow.php printed four stored per-slide values with no
 * escaping whatsoever:
 *
 *   line 26  $slide['slide_title']   text node
 *   line 29  $slide['description']   text node
 *   line 32  $slide['link_url']      href attribute
 *   line 32  $slide['link_title']    text node
 *
 * The href was the serious one: a stored "javascript:" URL executed on click,
 * and a stored quote broke out of the attribute outright. This is the same
 * defect class #2916 was filed for, in a widget the issue did not list.
 *
 * The sibling jumbotron templates were already safe -- they render $content,
 * which jumbotron-header.class.php builds through
 * Core\sanitize_input_text_output(). Only the slideshow group fields were
 * missed, because they are read straight from $instance in the template.
 *
 * Escaping matches the rest of #2916: link_url through Core\esc_link_url()
 * (bare esc_url() would prepend "http://" to the relative paths every stored
 * value uses), and the three text nodes through Core\esc_widget_title(), which
 * keeps <br> and drops everything else.
 */
class JumbotronSlideshowTemplateTest extends TestCase
{
    use AppliesPreKsesFilter;

    private const TEMPLATE = __DIR__ . '/../modules/proud-widget/widgets/jumbotron-header/templates/jumbotron-slideshow.php';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->applyPreKsesFilter();

        Functions\when('esc_html')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });
        Functions\when('esc_attr')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });
        // Faithful enough esc_url() to prove esc_link_url() is wired up,
        // including the "http://" prepending that makes a bare esc_url() the
        // wrong choice for the relative paths stored in this field.
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
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function slide(array $overrides = []): array
    {
        return array_merge([
            'slide_title' => 'Get involved',
            'description' => 'Want to volunteer for local institutions?',
            'link_title'  => 'See opportunities',
            'link_url'    => '/volunteer',
        ], $overrides);
    }

    private function render(array $slides): string
    {
        $instance                = ['slideshow' => $slides];
        $random_id               = 'proud-header-123';
        $classes                 = ['jumbotron'];
        $arr_styles              = [];
        $resp_img_classes        = [];
        $jumbotron_col_classes   = 'col-lg-7 col-md-8 col-sm-9';

        ob_start();
        try {
            include self::TEMPLATE;
        } finally {
            $output = (string) ob_get_clean();
        }
        return $output;
    }

    // -----------------------------------------------------------------
    // link_url -- the attribute, and the only one that was exploitable
    // -----------------------------------------------------------------

    public function test_javascript_url_is_neutralised(): void
    {
        $output = $this->render([$this->slide(['link_url' => 'javascript:alert(1)'])]);

        $this->assertStringNotContainsString('javascript:alert(1)', $output);
    }

    public function test_quote_in_link_url_cannot_break_out_of_the_href(): void
    {
        $output = $this->render([$this->slide(['link_url' => '/x" onmouseover="alert(1)'])]);

        $this->assertStringNotContainsString('onmouseover="alert(1)"', $output);
        $this->assertStringNotContainsString('" onmouseover', $output);
    }

    /**
     * Regression lock, same as #1825: every stored link_url on the production
     * database copy is a root-relative path (/volunteer, /events,
     * /teen-advisory-board-tab/). A bare esc_url() would rewrite those to
     * http://volunteer and break all three.
     */
    public function test_relative_link_url_is_not_rewritten(): void
    {
        $output = $this->render([$this->slide(['link_url' => '/volunteer'])]);

        $this->assertStringContainsString('href="/volunteer"', $output);
        $this->assertStringNotContainsString('http://volunteer', $output);
    }

    /**
     * A character reference has no literal ":" for esc_url() to strip, so this
     * is the form that would survive a regression to bare esc_url().
     */
    public function test_entity_encoded_colon_is_escaped(): void
    {
        $output = $this->render([$this->slide(['link_url' => 'javascript&#58;alert(1)'])]);

        $this->assertStringContainsString('&amp;#58;', $output);
    }

    // -----------------------------------------------------------------
    // text nodes
    // -----------------------------------------------------------------

    public function test_script_in_slide_title_is_removed(): void
    {
        $output = $this->render([$this->slide(['slide_title' => 'Hi<script>alert(1)</script>'])]);

        $this->assertStringNotContainsString('<script', $output);
    }

    public function test_script_in_description_is_removed(): void
    {
        $output = $this->render([$this->slide(['description' => 'Hi<script>alert(1)</script>'])]);

        $this->assertStringNotContainsString('<script', $output);
    }

    public function test_script_in_link_title_is_removed(): void
    {
        $output = $this->render([$this->slide(['link_title' => 'Go<script>alert(1)</script>'])]);

        $this->assertStringNotContainsString('<script', $output);
    }

    public function test_image_payload_in_description_is_removed(): void
    {
        $output = $this->render([$this->slide(['description' => '<img src=x onerror=alert(1)>'])]);

        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringNotContainsString('onerror', $output);
    }

    /**
     * Titles keep line breaks, matching the rest of #2916.
     */
    public function test_line_break_survives_in_slide_title(): void
    {
        $output = $this->render([$this->slide(['slide_title' => 'Get<br> involved'])]);

        $this->assertStringContainsString('Get<br> involved', $output);
    }

    public function test_real_stored_values_render_unchanged(): void
    {
        $output = $this->render([$this->slide()]);

        $this->assertStringContainsString('Get involved', $output);
        $this->assertStringContainsString('Want to volunteer for local institutions?', $output);
        $this->assertStringContainsString('See opportunities', $output);
        $this->assertStringContainsString('href="/volunteer"', $output);
    }

    /**
     * The template loops twice over the same array (indicators, then slides),
     * so escaping has to hold for every slide, not just the first.
     */
    public function test_escaping_applies_to_every_slide(): void
    {
        $output = $this->render([
            $this->slide(['slide_title' => 'A']),
            $this->slide(['slide_title' => 'B<script>alert(1)</script>', 'link_url' => 'javascript:alert(2)']),
        ]);

        $this->assertStringNotContainsString('<script', $output);
        $this->assertStringNotContainsString('javascript:alert(2)', $output);
        $this->assertStringContainsString('A', $output);
    }
}
