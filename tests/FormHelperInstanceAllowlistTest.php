<?php

use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use Proud\Core\FormHelper;

/**
 * Tests for FormHelper::updateGroupsWeight() -- issue #2918.
 *
 * ProudWidget::update() routes every widget save through this method, and it
 * used to copy every key of $new_instance into the saved instance. Widget
 * templates are rendered after extract( $instance ), so any key a caller could
 * POST became a variable in template scope. content-embed-document.php read two
 * variables it never assigned ($form_id, $form) and printed $form unescaped.
 *
 * The template's dead block is gone, but the underlying hole -- instances able
 * to carry keys the widget never declared -- is closed here, for every widget.
 */
class FormHelperInstanceAllowlistTest extends TestCase
{
    /** Field definitions in the shape a widget's $settings uses. */
    private const FIELDS = [
        'title'   => ['#type' => 'text'],
        'post_id' => ['#type' => 'text'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * The #2918 payload: a widget save carrying keys the widget does not
     * declare must not persist them.
     */
    public function test_undeclared_keys_are_dropped(): void
    {
        $instance = FormHelper::updateGroupsWeight([
            'title'   => 'Budget',
            'post_id' => '42',
            'form_id' => '1',
            'form'    => '<script>alert(1)</script>',
        ], self::FIELDS);

        $this->assertArrayNotHasKey('form', $instance, 'An undeclared key must not be saved.');
        $this->assertArrayNotHasKey('form_id', $instance, 'An undeclared key must not be saved.');
    }

    /**
     * Declared fields must survive untouched -- this is a key allowlist, not a
     * value sanitizer.
     */
    public function test_declared_keys_are_preserved(): void
    {
        $instance = FormHelper::updateGroupsWeight([
            'title'   => 'Budget',
            'post_id' => '42',
            'form'    => 'x',
        ], self::FIELDS);

        $this->assertSame('Budget', $instance['title']);
        $this->assertSame('42', $instance['post_id']);
    }

    /**
     * ProudWidget::widget() renders $instance['title'] for every widget, but
     * 'title' is only added to $settings by the ProudWidget constructor.
     * Sixteen widgets assign $this->settings wholesale in initialize(), which
     * runs later on `init`, so by the time update() runs their declared fields
     * no longer contain it. Dropping it would silently erase widget titles.
     */
    public function test_title_survives_even_when_the_widget_does_not_declare_it(): void
    {
        $instance = FormHelper::updateGroupsWeight([
            'title'   => 'Share this page',
            'classes' => 'btn',
        ], ['classes' => ['#type' => 'text']]);

        $this->assertSame('Share this page', $instance['title'], 'Widget titles must not be stripped.');
        $this->assertSame('btn', $instance['classes']);
    }

    /**
     * SiteOrigin Panels stores its own bookkeeping on the instance.
     * panels_info carries the widget class; the sidebars emulator reads
     * so_sidebar_emulator_id and option_name straight off the persisted
     * instance on the front end (inc/sidebars-emulator.php:148,238). Stripping
     * them would break Panels on every stored layout.
     */
    public function test_siteorigin_bookkeeping_keys_survive(): void
    {
        $instance = FormHelper::updateGroupsWeight([
            'post_id'                => '42',
            'panels_info'            => ['class' => 'DocumentWidget'],
            'so_sidebar_emulator_id' => 'proud_document_embed-3',
            'option_name'            => 'widget_proud_document_embed',
        ], self::FIELDS);

        $this->assertSame(['class' => 'DocumentWidget'], $instance['panels_info']);
        $this->assertSame('proud_document_embed-3', $instance['so_sidebar_emulator_id']);
        $this->assertSame('widget_proud_document_embed', $instance['option_name']);
    }

    /**
     * updateGroupsWeight() is also reached through FormHelper::formValues(),
     * whose $fields argument defaults to []. proud-teasers.php:1101 calls it
     * that way. With no declared fields there is no allowlist to apply, and
     * filtering against an empty one would discard the entire submission.
     */
    public function test_no_filtering_when_the_caller_declares_no_fields(): void
    {
        $values = ['anything' => 'a', 'at_all' => 'b'];

        $this->assertSame($values, FormHelper::updateGroupsWeight($values, []));
        $this->assertSame($values, FormHelper::updateGroupsWeight($values));
    }

    /**
     * The existing weight-sorting behaviour for repeating (0-indexed) field
     * groups must be unchanged for a declared field.
     */
    public function test_repeating_group_is_still_sorted_by_weight(): void
    {
        $instance = FormHelper::updateGroupsWeight([
            'imageset' => [
                ['weight' => 2, 'label' => 'second'],
                ['weight' => 1, 'label' => 'first'],
            ],
        ], ['imageset' => ['#type' => 'repeating']]);

        $this->assertSame('first', $instance['imageset'][0]['label']);
        $this->assertSame('second', $instance['imageset'][1]['label']);
    }

    /**
     * And #keyed groups must still be re-keyed for a declared field.
     */
    public function test_keyed_group_is_still_rekeyed(): void
    {
        $instance = FormHelper::updateGroupsWeight([
            'textset' => [
                ['id' => 'alpha', 'body' => 'A'],
            ],
        ], ['textset' => ['#type' => 'repeating', '#keyed' => 'id']]);

        $this->assertArrayHasKey('alpha', $instance['textset']);
        $this->assertSame('A', $instance['textset']['alpha']['body']);
    }
}
