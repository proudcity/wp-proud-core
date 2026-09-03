<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the repeating-fields accordion header -- issue #2916.
 *
 * #2916 escaped the widget group values on the FRONT END. The same stored
 * values are printed again in the widget admin form, and that copy was raw:
 *
 *   proud-form.php:592          copies the #group_title_field sub-value into
 *                               $field['#group_titles'][$i]
 *   repeating-fields.php:17     assigns it to $group_title
 *   repeating-fields-template.php:5   echoed it with no escaping
 *
 * So the front end was clean while the payload still fired for any user who
 * opened the widget form -- including an administrator. This is not specific
 * to one widget: the template serves icon-set (link_title), image-set
 * (link_title), text-card (text_title) and jumbotron-header (slide_title),
 * which are exactly the fields #2916 is about.
 *
 * esc_html() rather than esc_widget_title() here: this is an admin UI label,
 * not published content, so the <br>-preservation argument does not apply and
 * a literal "&lt;br&gt;" in an accordion heading is fine.
 */
class RepeatingFieldsTemplateTest extends TestCase
{
    private const TEMPLATE = __DIR__ . '/../modules/proud-form/templates/repeating-fields-template.php';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('esc_html')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });
        Functions\when('esc_attr')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * The template calls $this->printFormItem() and $this->attachConfigStateJs().
     * An empty group means neither is reached, keeping the test on the header.
     */
    private function render(string $groupTitle): string
    {
        $harness = new class {
            public function printFormItem($field) {}
            public function attachConfigStateJs($states, $group) {}

            public function render(string $template, string $groupTitle): string
            {
                $key         = 0;
                $group       = [];
                $group_title = $groupTitle;
                $field       = [
                    '#id'     => 'iconset',
                    '#name'   => 'widget-proud_icon_set[2][iconset]',
                    '#keyed'  => false,
                    '#title'  => 'Icons',
                    '#items'  => [],
                ];

                ob_start();
                try {
                    include $template;
                } finally {
                    $out = (string) ob_get_clean();
                }
                return $out;
            }
        };

        return $harness->render(self::TEMPLATE, $groupTitle);
    }

    public function test_script_in_group_title_is_escaped(): void
    {
        $output = $this->render('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    /**
     * The reviewer's proof of concept: an img/onerror payload saved as a slide
     * title fired in the accordion header.
     */
    public function test_img_onerror_payload_is_escaped(): void
    {
        $output = $this->render('x<img src=x onerror=alert(document.cookie)>');

        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(document.cookie)&gt;', $output);
    }

    public function test_ordinary_titles_still_render(): void
    {
        $output = $this->render('Report illegal camping');

        $this->assertStringContainsString('Report illegal camping', $output);
    }

    /**
     * Real stored value from page #10131. It renders as literal text here --
     * this is an admin label, not the published page.
     */
    public function test_stored_br_renders_as_literal_text_in_the_admin(): void
    {
        $output = $this->render('Report illegal <br> camping');

        $this->assertStringNotContainsString('<br>', $output);
        $this->assertStringContainsString('&lt;br&gt;', $output);
    }

    public function test_ampersand_is_encoded_once(): void
    {
        $output = $this->render('Boards & Commissions');

        $this->assertStringContainsString('Boards &amp; Commissions', $output);
        $this->assertStringNotContainsString('&amp;amp;', $output);
    }
}
