<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Proud\Core\esc_link_url() in proud-helpers.php.
 *
 * Covers issue #1825 follow-up: the first round wrapped link_url output in
 * a bare esc_url(), which prepends "http://" to any schemeless value that
 * doesn't already start with "/", "#" or "?" -- silently breaking legitimate
 * relative links such as "topics/homelessness/". esc_link_url() routes pure
 * relative paths through htmlspecialchars() (double_encode = true) instead,
 * and everything else through esc_url() as before.
 *
 * The relative-path branch deliberately does not call WordPress's esc_attr().
 * esc_attr() calls _wp_specialchars() with double_encode = false, which
 * preserves an existing character reference such as "&#58;" instead of
 * re-encoding its "&" -- the HTML parser then decodes that reference back
 * into a literal ":" inside the attribute value, resurrecting a
 * "javascript:" scheme from a value with no literal ":" in it at all.
 * htmlspecialchars() with double_encode = true encodes the "&" itself, so no
 * character reference can survive into the rendered attribute. Tests for
 * that branch therefore assert neither esc_url() nor esc_attr() is called
 * (see expectRelativeBranch()), except the character-reference bypass tests
 * below, which instead assert on the actual decoded output so they exercise
 * the real security property rather than an implementation detail.
 *
 * Tests for the esc_url() branch assert, via Functions::expect(), that
 * esc_url() is called once and esc_attr() is never called. The
 * andReturnUsing() closure reproduces WordPress's real esc_url() behaviour
 * closely enough to also verify the value transformation: it prepends
 * "http://" to schemeless, non-relative values and strips javascript: URLs.
 */
class EscLinkUrlTest extends TestCase
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

    private function fakeEscUrl(string $url): string
    {
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
    }

    /**
     * Faithful to WordPress's real esc_attr(), which calls
     * _wp_specialchars() with double_encode = false: an existing character
     * reference such as "&#58;", "&#x3a;" or a named reference like
     * "&colon;" is preserved rather than having its "&" encoded again. ENT_HTML5
     * is required here so PHP's own htmlspecialchars() recognizes named
     * references beyond the small ENT_HTML401 default table -- without it,
     * this stub would silently fail to preserve "&colon;" and hide the bug
     * this test exists to catch. This is what makes routing a relative value
     * through esc_attr() dangerous -- a plain htmlspecialchars() call with
     * default arguments double-encodes and would hide the bug entirely.
     */
    private function fakeEscAttrDoubleEncodeFalse(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }

    private function expectEscUrlBranch(): void
    {
        Functions\expect('esc_url')->once()->andReturnUsing(fn ($url) => $this->fakeEscUrl((string) $url));
        Functions\expect('esc_attr')->never();
    }

    /**
     * The relative-path branch escapes via htmlspecialchars() directly, not
     * the WordPress esc_attr()/esc_url() wrappers, so neither should ever be
     * invoked.
     */
    private function expectRelativeBranch(): void
    {
        Functions\expect('esc_attr')->never();
        Functions\expect('esc_url')->never();
    }

    private function assertDecodedResultHasNoJavascriptScheme(string $result, string $message = ''): void
    {
        $decoded = html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertDoesNotMatchRegularExpression(
            '#^\s*javascript\s*:#i',
            $decoded,
            $message ?: 'The HTML-decoded attribute value must not resolve to a live javascript: scheme.'
        );
    }

    public function test_relative_topics_path_is_unchanged(): void
    {
        $this->expectRelativeBranch();

        $this->assertSame(
            'topics/homelessness/',
            \Proud\Core\esc_link_url('topics/homelessness/'),
            'A relative path must not gain an http:// prefix.'
        );
    }

    public function test_relative_documents_path_is_unchanged(): void
    {
        $this->expectRelativeBranch();

        $this->assertSame(
            'documents/approved-street-trees/',
            \Proud\Core\esc_link_url('documents/approved-street-trees/')
        );
    }

    public function test_relative_path_with_ampersand_query_string_is_not_double_encoded_further(): void
    {
        $this->expectRelativeBranch();

        $this->assertSame(
            'docs/a&amp;b=c',
            \Proud\Core\esc_link_url('docs/a&b=c'),
            'An ordinary "&" in a relative path must still be entity-encoded exactly like esc_attr() would.'
        );
    }

    public function test_absolute_url_with_query_string_goes_through_esc_url(): void
    {
        $this->expectEscUrlBranch();

        $this->assertSame(
            'https://example.com/x?a=1&amp;b=2',
            \Proud\Core\esc_link_url('https://example.com/x?a=1&b=2')
        );
    }

    public function test_root_relative_url_takes_esc_url_branch(): void
    {
        $this->expectEscUrlBranch();

        $this->assertSame('/root-relative?x=1', \Proud\Core\esc_link_url('/root-relative?x=1'));
    }

    public function test_anchor_only_url_takes_esc_url_branch(): void
    {
        $this->expectEscUrlBranch();

        $this->assertSame('#anchor', \Proud\Core\esc_link_url('#anchor'));
    }

    public function test_query_only_url_takes_esc_url_branch(): void
    {
        $this->expectEscUrlBranch();

        $this->assertSame('?query=1', \Proud\Core\esc_link_url('?query=1'));
    }

    public function test_protocol_relative_url_takes_esc_url_branch_and_is_not_prepended(): void
    {
        $this->expectEscUrlBranch();

        $this->assertSame(
            '//protocol-relative.com',
            \Proud\Core\esc_link_url('//protocol-relative.com'),
            'A leading "//" must never gain an http:// prefix.'
        );
    }

    public function test_leading_control_character_does_not_bypass_esc_url_branch(): void
    {
        $this->expectEscUrlBranch();

        $this->assertSame(
            '//evil.example.com',
            \Proud\Core\esc_link_url("\x0C//evil.example.com"),
            'A leading control character must not divert a protocol-relative value into the relative-path branch.'
        );
    }

    public function test_mailto_link_survives(): void
    {
        $this->expectEscUrlBranch();

        $this->assertSame('mailto:a@b.com', \Proud\Core\esc_link_url('mailto:a@b.com'));
    }

    public function test_tel_link_survives(): void
    {
        $this->expectEscUrlBranch();

        $this->assertSame('tel:+15555551212', \Proud\Core\esc_link_url('tel:+15555551212'));
    }

    public function test_javascript_url_is_neutralized(): void
    {
        $this->expectEscUrlBranch();

        $this->assertSame('', \Proud\Core\esc_link_url('javascript:alert(1)'));
    }

    /**
     * These three cover the entity-encoded-colon bypass: no literal ":" is
     * present in any of these payloads, so they take the relative-path
     * branch. They deliberately do NOT assert which WP function is called --
     * that would make a pre-fix failure a Mockery call-count mismatch
     * instead of the actual security property. Instead esc_attr() is given a
     * faithful double_encode = false stub (matching real WordPress) so that,
     * whichever branch implementation is under test, the decoded output
     * reveals whether a live javascript: scheme survives.
     */
    public function test_decimal_character_reference_colon_is_not_a_bypass(): void
    {
        Functions\when('esc_attr')->alias(fn ($text) => $this->fakeEscAttrDoubleEncodeFalse((string) $text));
        Functions\expect('esc_url')->never();

        $result = \Proud\Core\esc_link_url('javascript&#58;alert(1)');

        $this->assertDecodedResultHasNoJavascriptScheme($result);
    }

    public function test_hex_character_reference_colon_is_not_a_bypass(): void
    {
        Functions\when('esc_attr')->alias(fn ($text) => $this->fakeEscAttrDoubleEncodeFalse((string) $text));
        Functions\expect('esc_url')->never();

        $result = \Proud\Core\esc_link_url('javascript&#x3a;alert(1)');

        $this->assertDecodedResultHasNoJavascriptScheme($result);
    }

    public function test_named_character_reference_colon_is_not_a_bypass(): void
    {
        Functions\when('esc_attr')->alias(fn ($text) => $this->fakeEscAttrDoubleEncodeFalse((string) $text));
        Functions\expect('esc_url')->never();

        $result = \Proud\Core\esc_link_url('javascript&colon;alert(1)');

        $this->assertDecodedResultHasNoJavascriptScheme($result);
    }

    public function test_relative_path_with_double_quote_is_attribute_escaped(): void
    {
        $this->expectRelativeBranch();

        $result = \Proud\Core\esc_link_url('relative/"onmouseover=alert(1)');

        $this->assertStringNotContainsString('"', $result, 'A literal double quote must not survive to break out of the href attribute.');
        $this->assertStringContainsString('&quot;', $result);
    }

    public function test_invalid_utf8_relative_path_substitutes_instead_of_emptying(): void
    {
        $this->expectRelativeBranch();

        $result = \Proud\Core\esc_link_url("bad\x80path");

        $this->assertNotSame('', $result, 'Invalid UTF-8 must not be silently discarded into an empty href.');
        $this->assertDoesNotMatchRegularExpression('#:#', $result, 'The substituted output must still contain no live colon.');
    }

    public function test_empty_string_returns_empty_string(): void
    {
        Functions\expect('esc_url')->never();
        Functions\expect('esc_attr')->never();

        $this->assertSame('', \Proud\Core\esc_link_url(''));
    }

    public function test_whitespace_only_string_returns_empty_string(): void
    {
        Functions\expect('esc_url')->never();
        Functions\expect('esc_attr')->never();

        $this->assertSame('', \Proud\Core\esc_link_url('   '));
    }
}
