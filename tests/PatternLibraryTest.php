<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ProudPatternLibrary::template_redirect() in proud-patternlibrary.php.
 *
 * Verifies that the pattern library endpoint requires manage_options, preventing
 * unauthenticated access and open-redirect exploitation (#2800).
 */
class PatternLibraryTest extends TestCase
{
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
     * template_redirect() must return without doing anything when the
     * wp_proud_patternlibrary query var is absent. current_user_can() must
     * never be called — the route check is the first gate.
     */
    public function test_template_redirect_returns_early_when_not_pattern_route(): void
    {
        global $wp;
        $wp              = new stdClass();
        $wp->query_vars  = [];

        Functions\expect('current_user_can')->never();

        $library = new ProudPatternLibrary();
        $library->template_redirect();

        // Reaching this line confirms no exit or die was called.
        $this->assertTrue(true);
    }

    /**
     * template_redirect() must call wp_die() when the current user lacks
     * manage_options, blocking the open-redirect vector.
     *
     * Before the fix: no capability check exists — wp_die() is never called
     * and the test errors when execution reaches the require_once call.
     *
     * After the fix: current_user_can('manage_options') is checked, wp_die()
     * is called for unauthorized users, and a return prevents reaching
     * require_once (required for testability — wp_die() is a no-op in tests).
     */
    public function test_template_redirect_denies_users_without_manage_options(): void
    {
        global $wp;
        $wp             = new stdClass();
        $wp->query_vars = ['wp_proud_patternlibrary' => 1];

        Functions\when('current_user_can')->justReturn(false);
        Functions\when('__')->returnArg();
        Functions\when('wp_die')->alias(static function (): void {
            throw new \RuntimeException('wp_die called');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wp_die called');

        $library = new ProudPatternLibrary();
        $library->template_redirect();
    }

    /**
     * template_redirect() must call current_user_can('manage_options') —
     * not any other capability — when the pattern route is matched.
     */
    public function test_template_redirect_checks_manage_options_capability(): void
    {
        global $wp;
        $wp             = new stdClass();
        $wp->query_vars = ['wp_proud_patternlibrary' => 1];

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        Functions\when('__')->returnArg();
        Functions\when('wp_die')->alias(static function (): void {
            throw new \RuntimeException('wp_die called');
        });

        $this->expectException(\RuntimeException::class);

        $library = new ProudPatternLibrary();
        $library->template_redirect();
    }
}
