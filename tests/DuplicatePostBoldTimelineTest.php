<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for plugin_override/duplicate-post/proud-duplicate-post.php.
 *
 * Enables Duplicate Post for Bold Timeline, builds the Clone / New Draft link
 * map for the list page script, and routes New Draft to the builder.
 *
 * See wp-proudcity#3014.
 */
class DuplicatePostBoldTimelineTest extends TestCase
{
    private array $request_backup = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->request_backup = $_REQUEST;

        Functions\when('is_admin')->justReturn(true);
        Functions\when('sanitize_key')->alias(function ($key) {
            return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $key));
        });
        Functions\when('admin_url')->alias(function ($path = '') {
            return 'https://example.test/wp-admin/' . ltrim($path, '/');
        });
    }

    protected function tearDown(): void
    {
        $_REQUEST = $this->request_backup;
        Monkey\tearDown();
        parent::tearDown();
    }

    private function links_return(string $clone, string $draft): void
    {
        Functions\when('duplicate_post_get_clone_post_link')->alias(
            function ($id, $context, $is_draft) use ($clone, $draft) {
                return $is_draft ? $draft : $clone;
            }
        );
    }

    private const ROW_ON  = ['row' => '1'];
    private const BOTH_ON = ['clone' => '1', 'new_draft' => '1'];

    // Enabling the post type.

    public function test_filter_adds_bold_timeline_and_keeps_existing_types(): void
    {
        $this->assertSame(
            ['post', 'page', 'bold-timeline'],
            proudcity_duplicate_post_enable_bold_timeline(['post', 'page'])
        );
    }

    public function test_filter_does_not_add_bold_timeline_twice(): void
    {
        $this->assertSame(
            ['post', 'bold-timeline'],
            proudcity_duplicate_post_enable_bold_timeline(['post', 'bold-timeline'])
        );
    }

    public function test_filter_handles_non_array_input(): void
    {
        $this->assertSame(['bold-timeline'], proudcity_duplicate_post_enable_bold_timeline(''));
    }

    // Link map.

    public function test_map_carries_both_links_and_draft_flag(): void
    {
        $this->links_return(
            'https://example.test/wp-admin/admin.php?action=duplicate_post_clone&amp;post=5&amp;_wpnonce=abc',
            'https://example.test/wp-admin/admin.php?action=duplicate_post_new_draft&amp;post=5&amp;_wpnonce=def'
        );

        $map = proudcity_bold_timeline_clone_link_map([5 => 'draft', 6 => 'publish'], self::ROW_ON, self::BOTH_ON);

        $this->assertSame([5, 6], array_keys($map));
        $this->assertSame(
            'https://example.test/wp-admin/admin.php?action=duplicate_post_clone&post=5&_wpnonce=abc',
            $map[5]['clone']
        );
        $this->assertSame(
            'https://example.test/wp-admin/admin.php?action=duplicate_post_new_draft&post=5&_wpnonce=def',
            $map[5]['newDraft']
        );
        $this->assertTrue($map[5]['isDraft']);
        $this->assertFalse($map[6]['isDraft']);
    }

    public function test_map_omits_timelines_when_duplicate_post_returns_no_links(): void
    {
        $this->links_return('', '');

        $this->assertSame([], proudcity_bold_timeline_clone_link_map([5 => 'publish'], self::ROW_ON, self::BOTH_ON));
    }

    public function test_map_keeps_a_timeline_that_has_only_one_link(): void
    {
        $this->links_return('', 'https://example.test/wp-admin/admin.php?action=x');

        $map = proudcity_bold_timeline_clone_link_map([5 => 'publish'], self::ROW_ON, self::BOTH_ON);

        $this->assertSame('', $map[5]['clone']);
        $this->assertNotSame('', $map[5]['newDraft']);
    }

    public function test_map_is_empty_when_row_links_are_turned_off(): void
    {
        $this->links_return('https://example.test/a', 'https://example.test/b');

        $this->assertSame([], proudcity_bold_timeline_clone_link_map([5 => 'publish'], [], self::BOTH_ON));
        $this->assertSame([], proudcity_bold_timeline_clone_link_map([5 => 'publish'], ['row' => ''], self::BOTH_ON));
        $this->assertSame([], proudcity_bold_timeline_clone_link_map([5 => 'publish'], false, self::BOTH_ON));
    }

    public function test_map_respects_the_per_link_settings(): void
    {
        $this->links_return('https://example.test/clone', 'https://example.test/draft');

        $clone_only = proudcity_bold_timeline_clone_link_map([5 => 'publish'], self::ROW_ON, ['clone' => '1']);
        $this->assertSame('https://example.test/clone', $clone_only[5]['clone']);
        $this->assertSame('', $clone_only[5]['newDraft']);

        $draft_only = proudcity_bold_timeline_clone_link_map([5 => 'publish'], self::ROW_ON, ['new_draft' => '1']);
        $this->assertSame('', $draft_only[5]['clone']);
        $this->assertSame('https://example.test/draft', $draft_only[5]['newDraft']);

        $this->assertSame([], proudcity_bold_timeline_clone_link_map([5 => 'publish'], self::ROW_ON, []));
    }

    // New Draft redirect.

    private function new_draft_request(string $action = 'duplicate_post_new_draft'): void
    {
        $_REQUEST['action'] = $action;
    }

    public function test_new_draft_of_a_timeline_goes_to_the_builder(): void
    {
        $this->new_draft_request();
        Functions\when('get_post_type')->justReturn('bold-timeline');

        $this->assertSame(
            'https://example.test/wp-admin/admin.php?page=bold-timeline-edit&post=42',
            proudcity_bold_timeline_new_draft_redirect('https://example.test/wp-admin/post.php?action=edit&post=42', 302)
        );
    }

    public function test_new_draft_of_a_post_or_page_is_untouched(): void
    {
        $this->new_draft_request();
        Functions\when('get_post_type')->justReturn('page');

        $location = 'https://example.test/wp-admin/post.php?action=edit&post=42';
        $this->assertSame($location, proudcity_bold_timeline_new_draft_redirect($location, 302));
    }

    public function test_other_actions_are_untouched(): void
    {
        $this->new_draft_request('duplicate_post_clone');
        Functions\when('get_post_type')->justReturn('bold-timeline');

        $location = 'https://example.test/wp-admin/post.php?action=edit&post=42';
        $this->assertSame($location, proudcity_bold_timeline_new_draft_redirect($location, 302));

        unset($_REQUEST['action']);
        $this->assertSame($location, proudcity_bold_timeline_new_draft_redirect($location, 302));
    }

    public function test_redirects_outside_the_admin_are_untouched(): void
    {
        $this->new_draft_request();
        Functions\when('is_admin')->justReturn(false);
        Functions\when('get_post_type')->justReturn('bold-timeline');

        $location = 'https://example.test/wp-admin/post.php?action=edit&post=42';
        $this->assertSame($location, proudcity_bold_timeline_new_draft_redirect($location, 302));
    }

    public function test_non_post_php_targets_are_untouched(): void
    {
        $this->new_draft_request();
        Functions\when('get_post_type')->justReturn('bold-timeline');

        foreach ([
            'https://example.test/wp-admin/edit.php?post_type=bold-timeline',
            'https://example.test/wp-admin/post.php?action=trash&post=42',
            'https://example.test/wp-admin/post.php?action=edit',
            'https://evil.test/elsewhere',
        ] as $location) {
            $this->assertSame($location, proudcity_bold_timeline_new_draft_redirect($location, 302));
        }
    }

    public function test_a_non_numeric_post_id_cannot_inject_into_the_target(): void
    {
        $this->new_draft_request();
        Functions\when('get_post_type')->justReturn('bold-timeline');

        $location = 'https://example.test/wp-admin/post.php?action=edit&post=abc';
        $this->assertSame($location, proudcity_bold_timeline_new_draft_redirect($location, 302));

        $this->assertSame(
            'https://example.test/wp-admin/admin.php?page=bold-timeline-edit&post=7',
            proudcity_bold_timeline_new_draft_redirect('https://example.test/wp-admin/post.php?action=edit&post=7%26x%3D1', 302)
        );
    }
}
