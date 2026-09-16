<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Proud\Core\ProudWidget::widget(), the render path every ProudCity
 * widget goes through.
 *
 * Two findings from the security review of #2933:
 *
 *   1. The widget title was echoed unescaped. SiteOrigin widget instances live
 *      in panels_data post meta, so the title is settable by anyone who can
 *      edit the page -- not just users with edit_theme_options.
 *
 *   2. WidgetContentCache::getKey() was computed from $instance BEFORE
 *      hasContent() populated it. The meta-driven widgets (AgencyContact,
 *      AgencySocial) carry only 'title' at that point, so two different
 *      agencies rendered in one request hashed to the same key and the second
 *      served the first one's markup.
 */
class WidgetBaseRenderTest extends TestCase
{
    use AppliesPreKsesFilter;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->applyPreKsesFilter();

        Functions\when('is_admin')->justReturn(false);

        // widget() calls addJsSettings(), which writes through the $proudcore global.
        global $proudcore;
        $proudcore = new TestProudCore();

        // Clear the per-request content cache between tests.
        $ref = new ReflectionClass(\Proud\Core\WidgetContentCache::class);
        $prop = $ref->getProperty('cache');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function args(): array
    {
        return [
            'name'          => 'sidebar',
            'before_widget' => '<section class="widget">',
            'after_widget'  => '</section>',
            'before_title'  => '<h2 class="proud-widget-title">',
            'after_title'   => '</h2>',
        ];
    }

    private function render(\Proud\Core\ProudWidget $widget, array $instance): string
    {
        ob_start();
        $widget->widget($this->args(), $instance);
        return ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // Title escaping
    // -----------------------------------------------------------------------

    public function test_plain_title_still_renders(): void
    {
        $html = $this->render(new TestRenderWidget(), ['title' => 'Connect with us', 'body' => 'x']);

        $this->assertStringContainsString('<h2 class="proud-widget-title">Connect with us</h2>', $html);
    }

    /**
     * The payload from the security review.
     */
    public function test_script_in_title_is_neutralised(): void
    {
        $html = $this->render(new TestRenderWidget(), [
            'title' => '<img src=x onerror=alert(1)>',
            'body'  => 'x',
        ]);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_script_tag_in_title_is_neutralised(): void
    {
        $html = $this->render(new TestRenderWidget(), [
            'title' => '</h2><script>alert(1)</script>',
            'body'  => 'x',
        ]);

        $this->assertStringNotContainsString('<script', $html);
    }

    /**
     * esc_widget_title() allows <br> and nothing else -- the allowlist
     * wp-proud-core settled on in #2916. The DB scan for #2933 found no stored
     * widget title containing any markup at all across three sites, so nothing
     * legitimate is lost, but <br> stays permitted for consistency with the
     * other title sinks.
     */
    public function test_br_in_title_is_preserved(): void
    {
        $html = $this->render(new TestRenderWidget(), ['title' => 'Call us<br>today', 'body' => 'x']);

        $this->assertStringContainsString('Call us<br>today', $html);
    }

    public function test_empty_title_renders_no_heading(): void
    {
        $html = $this->render(new TestRenderWidget(), ['title' => '', 'body' => 'x']);

        $this->assertStringNotContainsString('proud-widget-title', $html);
    }

    // -----------------------------------------------------------------------
    // Cache keying
    // -----------------------------------------------------------------------

    /**
     * Two widgets of the same class with the same title but different
     * hasContent()-resolved content must not share a cache entry.
     *
     * Before the fix both hashed on ['title' => 'Contact'] and the second
     * rendered the first one's markup.
     */
    public function test_same_title_different_resolved_content_do_not_collide(): void
    {
        $first  = new TestMetaWidget('Library and Recreation');
        $second = new TestMetaWidget('Fire');

        $firstHtml  = $this->render($first, ['title' => 'Contact']);
        $secondHtml = $this->render($second, ['title' => 'Contact']);

        $this->assertStringContainsString('Library and Recreation', $firstHtml);
        $this->assertStringContainsString('Fire', $secondHtml);
        $this->assertStringNotContainsString('Library and Recreation', $secondHtml);
    }

    /**
     * The cache must still do its job: rendering the same resolved content
     * twice should only invoke printWidget() once.
     */
    public function test_identical_content_is_served_from_cache(): void
    {
        TestMetaWidget::$printCount = 0;

        $this->render(new TestMetaWidget('Fire'), ['title' => 'Contact']);
        $this->render(new TestMetaWidget('Fire'), ['title' => 'Contact']);

        $this->assertSame(1, TestMetaWidget::$printCount, 'printWidget() should have been cached');
    }

    /**
     * A widget whose hasContent() returns false renders nothing at all.
     */
    public function test_empty_widget_renders_nothing(): void
    {
        $this->assertSame('', trim($this->render(new TestMetaWidget(''), ['title' => 'Contact'])));
    }
}

/**
 * Minimal concrete widget: renders whatever is in $instance['body'].
 */
class TestRenderWidget extends \Proud\Core\ProudWidget
{
    public function __construct()
    {
        parent::__construct('test_render', 'Test render widget', []);
        // The real base wires initialize() to the 'init' action, which never
        // fires here, so call it directly.
        $this->initialize();
    }

    public function initialize()
    {
        // ProudWidget::addSettingDefaults() filters $instance down to declared
        // settings before hasContent() runs, so 'body' has to be declared.
        $this->settings['body'] = ['#type' => 'text', '#default_value' => ''];
    }

    public function hasContent($args, &$instance)
    {
        return !empty($instance['body']);
    }

    public function printWidget($args, $instance)
    {
        echo '<p>' . $instance['body'] . '</p>';
    }
}

/**
 * Stands in for the meta-driven widgets: hasContent() populates $instance from
 * an external source, so $instance differs only AFTER it has run.
 */
class TestMetaWidget extends \Proud\Core\ProudWidget
{
    public static $printCount = 0;

    private $agency;

    public function __construct($agency = '')
    {
        $this->agency = $agency;
        parent::__construct('test_meta', 'Test meta widget', []);
        $this->initialize();
    }

    public function initialize() {}

    public function hasContent($args, &$instance)
    {
        $instance['agency'] = $this->agency;
        return !empty($this->agency);
    }

    public function printWidget($args, $instance)
    {
        self::$printCount++;
        echo '<p>' . $instance['agency'] . '</p>';
    }
}

/**
 * Collects the JS settings widget() pushes out, so the render path can run.
 */
class TestProudCore
{
    public array $jsSettings = [];

    public function addJsSettings($settings)
    {
        $this->jsSettings[] = $settings;
    }
}
