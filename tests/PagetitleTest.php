<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for proud_pagetitle_get_duplicates() in proud-pagetitle.php.
 *
 * Verifies that the SQL query uses wpdb->prepare() with a %s placeholder
 * rather than embedding the title via string concatenation, which would
 * make prepare() a no-op and allow SQL injection (#2800).
 */
class PagetitleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = null;
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * proud_pagetitle_get_duplicates() must pass the title as a separate
     * argument to $wpdb->prepare() using a %s placeholder.
     *
     * Before the fix: prepare() is called with only the SQL string — the title
     * is already embedded via concatenation, so $args is empty and prepare()
     * provides no escaping.
     *
     * After the fix: prepare() receives the SQL template with %s and the
     * sanitized title as a second argument, enabling proper escaping.
     */
    public function test_get_duplicates_passes_title_as_prepare_placeholder(): void
    {
        global $wpdb;

        $mock = new class {
            public string $posts = 'wp_posts';
            public array $lastPrepareArgs = [];
            public string $lastPrepareQuery = '';

            public function prepare(string $sql, ...$args): string
            {
                $this->lastPrepareQuery = $sql;
                $this->lastPrepareArgs = $args;
                return $sql;
            }

            public function get_results(string $_sql): array
            {
                return [];
            }
        };

        $wpdb = $mock;

        Functions\when('sanitize_text_field')->returnArg();

        proud_pagetitle_get_duplicates('test title', 999);

        $this->assertStringContainsString(
            '%s',
            $mock->lastPrepareQuery,
            'SQL query must use a %s placeholder rather than embedding the title directly.'
        );

        $this->assertCount(
            1,
            $mock->lastPrepareArgs,
            'prepare() must receive the title as a separate argument, not embedded in the SQL string.'
        );

        $this->assertSame(
            'test title',
            $mock->lastPrepareArgs[0],
            'The sanitized title must be passed as the first prepare() argument.'
        );
    }

    /**
     * Returns false when no post with the given title exists.
     */
    public function test_get_duplicates_returns_false_when_no_match(): void
    {
        global $wpdb;

        $wpdb = new class {
            public string $posts = 'wp_posts';

            public function prepare(string $sql, ...$_args): string
            {
                return $sql;
            }

            public function get_results(string $_sql): array
            {
                return [];
            }
        };

        Functions\when('sanitize_text_field')->returnArg();

        $result = proud_pagetitle_get_duplicates('unique title', 0);

        $this->assertFalse($result);
    }

    /**
     * Returns true when a different post already has the same title.
     */
    public function test_get_duplicates_returns_true_when_another_post_has_same_title(): void
    {
        global $wpdb;

        $wpdb = new class {
            public string $posts = 'wp_posts';

            public function prepare(string $sql, ...$_args): string
            {
                return $sql;
            }

            public function get_results(string $_sql): array
            {
                $row      = new stdClass();
                $row->ID  = 42;
                return [$row];
            }
        };

        Functions\when('sanitize_text_field')->returnArg();

        // Post ID 0 does not match the returned ID of 42 — it's a duplicate.
        $result = proud_pagetitle_get_duplicates('existing title', 0);

        $this->assertTrue($result);
    }

}
