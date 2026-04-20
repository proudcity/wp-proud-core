<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Proud\Core\ProudBreadcrumb;
use Proud\Core\ProudMenuUtil;

/**
 * Tests for the ProudBreadcrumb and ProudMenuUtil breadcrumb logic.
 *
 * Covers three edge-cases found in production:
 *  - Post appearing twice in the same menu forks the active trail (#2806)
 *  - Non-sequential menu_order leaves ancestor trail slots unfilled (#2806)
 *  - Empty trail after filtering causes a TypeError in the firstItem branch (#2806)
 */
class ProudMenuBreadcrumbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Reset static caches so tests don't bleed into each other.
        ProudMenuUtil::$menus             = [];
        ProudMenuUtil::$menu_structures   = [];
        ProudMenuUtil::$active_menu_trails = [];
        ProudBreadcrumb::$active_trail    = null;

        // pc_get_yoast_meta_or_excerpt is called for every menu item inside
        // get_nested_menu; stub it so tests don't need a real post table.
        Functions\when('Proud\Core\pc_get_yoast_meta_or_excerpt')->justReturn('');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a fake nav menu item as a stdClass with the properties that
     * ProudMenuUtil reads.
     */
    private function makeMenuItem(int $id, int $objectId, int $parentId = 0, string $title = ''): object
    {
        return (object) [
            'ID'               => $id,
            'object_id'        => (string) $objectId,
            'menu_item_parent' => (string) $parentId,
            'url'              => "https://example.com/page-{$objectId}",
            'title'            => $title ?: "Page {$objectId}",
            'type'             => 'post_type',
            'object'           => 'page',
            'menu_order'       => $id,
        ];
    }

    /** Register a fake menu slug so get_menu_items() doesn't return false. */
    private function registerMenu(string $slug, int $termId = 1): void
    {
        ProudMenuUtil::$menus[$slug] = (object) ['term_id' => $termId, 'slug' => $slug];
    }

    // -------------------------------------------------------------------------
    // Tests: get_nested_menu — duplicate active post
    // -------------------------------------------------------------------------

    /**
     * When the same post appears twice as siblings in a menu, only the first
     * occurrence should be marked active. Before the $found_active fix both
     * siblings were added to the trail, producing an ambiguous forked path.
     */
    public function test_get_nested_menu_single_trail_branch_when_post_appears_twice(): void
    {
        global $post;
        $post = (object) ['ID' => 20];

        $this->registerMenu('test-menu');

        // root(1, obj=10) → child_a(2, obj=20), child_b(3, obj=20)
        Functions\when('wp_get_nav_menu_items')->justReturn([
            $this->makeMenuItem(1, 10),
            $this->makeMenuItem(2, 20, 1, 'Child A'),
            $this->makeMenuItem(3, 20, 1, 'Child B'),
        ]);

        ProudMenuUtil::get_nested_menu('test-menu');

        $trail = ProudMenuUtil::$active_menu_trails['test-menu'];

        // Trail must be root→one child only. Without the fix it would be
        // root→child_a→child_b (or root→child_b→child_a), containing 3 entries.
        $this->assertCount(2, $trail, 'Trail should contain exactly root + one active child, not both duplicates.');
        $this->assertArrayHasKey('1', $trail, 'Root item must be in the trail.');
        $this->assertArrayHasKey('2', $trail, 'First occurrence of the active post (child_a) must be in the trail.');
        $this->assertArrayNotHasKey('3', $trail, 'Second occurrence of the active post (child_b) must be excluded.');
    }

    // -------------------------------------------------------------------------
    // Tests: build_breadcrumb — out-of-order menu_order
    // -------------------------------------------------------------------------

    /**
     * wp_get_nav_menu_items() returns items sorted by menu_order, which is not
     * guaranteed to be root→leaf. If the leaf has a lower menu_order the old
     * end()-based break fired early, leaving ancestor slots as '' (empty strings).
     * reset() on the resulting array returned '' and ''['post_id'] threw a TypeError.
     *
     * With the fix the loop iterates all items so every slot is filled.
     */
    public function test_build_breadcrumb_fills_all_slots_when_menu_order_is_leaf_first(): void
    {
        global $pageInfo;
        $pageInfo = ['menu' => 'test-menu'];

        $this->registerMenu('test-menu');

        // Pre-populate the trail (root→mid→leaf) the way get_nested_menu would.
        ProudMenuUtil::$active_menu_trails['test-menu'] = [
            '101' => '',
            '102' => '',
            '103' => '',
        ];

        // Return items in REVERSE order: leaf first (menu_order 1), then mid, then root.
        Functions\when('wp_get_nav_menu_items')->justReturn([
            (object) ['ID' => 103, 'object_id' => '300', 'url' => '/c', 'title' => 'Leaf',   'object' => 'page', 'menu_order' => 1],
            (object) ['ID' => 102, 'object_id' => '200', 'url' => '/b', 'title' => 'Mid',    'object' => 'page', 'menu_order' => 2],
            (object) ['ID' => 101, 'object_id' => '100', 'url' => '/a', 'title' => 'Root',   'object' => 'page', 'menu_order' => 3],
        ]);

        ProudBreadcrumb::build_breadcrumb();

        $trail = ProudBreadcrumb::$active_trail;

        $this->assertCount(3, $trail, 'All three trail slots must be filled.');

        foreach ($trail as $item) {
            $this->assertIsArray($item, 'Every trail entry must be an array, not an empty string.');
        }

        $lastItem = end($trail);
        $this->assertTrue($lastItem['active'] ?? false, 'The deepest (leaf) item must be marked active.');
    }

    // -------------------------------------------------------------------------
    // Tests: build_breadcrumb — unmatched trail slots
    // -------------------------------------------------------------------------

    /**
     * If a trail entry has no matching menu item (e.g. the item was removed from
     * the menu since the trail was cached), the slot stays as '' after the loop.
     * array_filter drops it. The result should be an empty trail with no crash.
     */
    public function test_build_breadcrumb_with_no_matching_menu_items_produces_empty_trail(): void
    {
        global $pageInfo;
        $pageInfo = ['menu' => 'test-menu'];

        $this->registerMenu('test-menu');

        // Trail references item 999 which doesn't exist in the menu.
        ProudMenuUtil::$active_menu_trails['test-menu'] = ['999' => ''];

        Functions\when('wp_get_nav_menu_items')->justReturn([
            (object) ['ID' => 1, 'object_id' => '10', 'url' => '/x', 'title' => 'Other', 'object' => 'page', 'menu_order' => 1],
        ]);

        // Must not throw a TypeError.
        ProudBreadcrumb::build_breadcrumb();

        $this->assertSame([], ProudBreadcrumb::$active_trail, 'Unmatched trail should be empty after filtering.');
    }

    /**
     * Same as above, but with parent_post_type set to a non-agency/topic type.
     * This exercises the else branch that calls reset() and accesses ['post_id'].
     * Before the is_array($firstItem) guard this threw a TypeError.
     */
    public function test_build_breadcrumb_empty_trail_with_parent_post_type_does_not_crash(): void
    {
        global $pageInfo;
        $pageInfo = [
            'menu'             => 'test-menu',
            'parent_post_type' => 'page',      // not agency or proud-topic
            'parent_post'      => 99,
        ];

        $this->registerMenu('test-menu');

        ProudMenuUtil::$active_menu_trails['test-menu'] = ['999' => ''];

        Functions\when('wp_get_nav_menu_items')->justReturn([
            (object) ['ID' => 1, 'object_id' => '10', 'url' => '/x', 'title' => 'Other', 'object' => 'page', 'menu_order' => 1],
        ]);

        // Before the is_array($firstItem) guard: TypeError: Cannot access offset
        // of type string on string (or on bool when false).
        ProudBreadcrumb::build_breadcrumb();

        // No assertion needed beyond surviving; confirm trail is empty.
        $this->assertSame([], ProudBreadcrumb::$active_trail);
    }
}
