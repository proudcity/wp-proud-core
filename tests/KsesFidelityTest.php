<?php

use Brain\Monkey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the test harness to what the running site actually produces.
 *
 * Proud\Core\esc_widget_title() is a thin wrapper over wp_kses($value,
 * ['br' => []]), so a stubbed kses would mean these tests measure the stub
 * rather than WordPress. #1825 recorded that lesson as "a stub that is safer
 * than the real function turns the test into a rubber stamp".
 *
 * A hand-written model of kses was tried first and was NOT faithful: across 78
 * adversarial inputs it diverged on 22, in both directions. HTML comments,
 * "</ x>" bogus-comment tokens, NUL bytes and numeric character references were
 * all wrong, and several divergences were in the dangerous direction -- a
 * template test would have passed while real WordPress emitted raw markup. The
 * corpus meant to pin that model contained none of those cases, which is why it
 * looked trustworthy.
 *
 * So bootstrap.php loads the real wp-includes/kses.php, and AppliesPreKsesFilter
 * supplies the `pre_kses` filter WordPress registers in default-filters.php.
 * This class proves that combination reproduces production: every expected
 * value below was captured from the running site, not written by hand.
 *
 *   wp eval-file "~/Documents/developers/Github Issue Notes/2916 - scripts/kses-probe.php"
 *
 * If WordPress changes kses, or the harness drifts from it, this fails.
 */
class KsesFidelityTest extends TestCase
{
    use AppliesPreKsesFilter;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->applyPreKsesFilter();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function realWordPressOutputs(): array
    {
        return [
            // The value actually stored on page #10131, the reason for this change.
            'stored br is preserved'        => ['Report illegal <br> camping ', 'Report illegal <br> camping '],
            'self closing br normalises'    => ['Report illegal <br/> camping', 'Report illegal <br /> camping'],
            'spaced self closing br'        => ['Report illegal <br /> camping', 'Report illegal <br /> camping'],
            'uppercase br keeps its case'   => ['Report illegal <BR> camping', 'Report illegal <BR> camping'],

            // Ampersands: encoded once, existing references left alone.
            'bare ampersand encodes once'   => ['Boards & Commissions', 'Boards &amp; Commissions'],
            'existing entity is preserved'  => ['Finance &amp; tax', 'Finance &amp; tax'],

            // kses does not touch quotes. Safe: every call site is a text node.
            'quotes are left alone'         => ['They\'re "quoted"', 'They\'re "quoted"'],

            // Disallowed tags are dropped, their text content kept.
            'script tag is dropped'         => ['<script>alert(1)</script>', 'alert(1)'],
            'mixed case script is dropped'  => ['<sCrIpT>alert(1)</sCrIpT>', 'alert(1)'],
            'style tag is dropped'          => ['<style>body{}</style>', 'body{}'],
            'anchor is dropped'             => ['<a href="https://evil.tld">click</a>', 'click'],
            'formatting tags are dropped'   => ['<b>bold</b> and <em>em</em>', 'bold and em'],
            'img is dropped entirely'       => ['<img src=x onerror=alert(1)>', ''],
            'svg is dropped entirely'       => ['<svg onload=alert(1)>', ''],

            // br is allowed but carries no attributes.
            'br attributes are stripped'    => ['<br onclick="alert(1)">x', '<br>x'],
            'br class is stripped'          => ['<br class="foo">x', '<br>x'],
            'br junk attribute is stripped' => ['<br\t>x', '<br>x'],

            // Breakout attempts.
            'attribute breakout is inert'   => ['"><img src=x onerror=alert(1)>', '"&gt;'],
            'unclosed tag is encoded'       => ['<br', '&lt;br'],
            'nested angle brackets'         => ['<<br>>', '&lt;<br>&gt;'],

            // Documented content-loss case: a bare "<" eats through the next ">".
            'bare angle brackets eat text'  => ['a < b and c > d', 'a  d'],

            'multiple br with newline'      => ["<br>\n<br>", "<br>\n<br>"],
            'javascript scheme is text'     => ['javascript:alert(1)', 'javascript:alert(1)'],
            'plain text is untouched'       => ['plain title', 'plain title'],

            // --- Adversarial set -------------------------------------------
            // The first version of this corpus was benign, and a hand-written
            // kses model passed it while being wrong about every case below.
            // These are base64 so control characters survive transcription;
            // all captured from the running site.
            'comments are preserved raw'     => [base64_decode('YTwhLS0gY29tbWVudCAtLT5i'), base64_decode('YTwhLS0gY29tbWVudCAtLT5i')],
            'comment after a line break'     => [base64_decode('UmVwb3J0IGlsbGVnYWwgPGJyPiBjYW1waW5nIDwhLS0gbm90ZSAtLT4gbW9yZQ=='), base64_decode('UmVwb3J0IGlsbGVnYWwgPGJyPiBjYW1waW5nIDwhLS0gbm90ZSAtLT4gbW9yZQ==')],
            'bogus comment slash space'      => [base64_decode('PC8gc2NyaXB0Pg=='), base64_decode('PC8gc2NyaXB0Pg==')],
            'bogus comment double slash'     => [base64_decode('PC8vYnI+'), base64_decode('PC8vYnI+')],
            'stray closing br'               => [base64_decode('PC9icj4='), base64_decode('PC9icj4=')],
            'comment can swallow text'       => [base64_decode('YTwhLS0geCAtLSE+Yg=='), base64_decode('YTwhLS0geCAtISZndDtiLS0+')],
            'nul byte is stripped'           => [base64_decode('YQBi'), base64_decode('YWI=')],
            'named entities normalise'       => [base64_decode('Jm5ic3A7JmNvcHk7Jm5vdGF2YWxpZGVudGl0eTs='), base64_decode('Jm5ic3A7JmNvcHk7JmFtcDtub3RhdmFsaWRlbnRpdHk7')],
            'padded numeric reference'       => [base64_decode('JiMwMDAwMDYwO3NjcmlwdCYjMDAwMDA2Mjs='), base64_decode('JiMwNjA7c2NyaXB0JiMwNjI7')],
            'hyphenated pseudo tag'          => [base64_decode('PGJyLXggb25sb2FkPWFsZXJ0KDEpPg=='), base64_decode('')],
            'formfeed inside br'             => [base64_decode('PGJyDG9ubG9hZD1hbGVydCgxKT4='), base64_decode('')],
            'nested split script'            => [base64_decode('PHNjcjxzY3JpcHQ+aXB0PmFsZXJ0KDEpPC9zY3I8L3NjcmlwdD5pcHQ+'), base64_decode('Jmx0O3NjcmlwdCZndDthbGVydCgxKSZsdDsvc2NyaXB0Jmd0Ow==')],
            'digit leading pseudo tag'       => [base64_decode('V2FpdCA8IDUgbWludXRlcyA+IGNhbGwgdXM='), base64_decode('V2FpdCAgY2FsbCB1cw==')],
            'cdata section'                  => [base64_decode('PCFbQ0RBVEFbPHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pl1dPg=='), base64_decode('Jmx0OyFbQ0RBVEFbYWxlcnQoMSldXSZndDs=')],
            'conditional comment'            => [base64_decode('PCFbaWYgIUlFXT48c2NyaXB0PmFsZXJ0KDEpPC9zY3JpcHQ+PCFbZW5kaWZdPg=='), base64_decode('YWxlcnQoMSk=')],
            'br with slash attribute'        => [base64_decode('PGJyL29ubG9hZD1hbGVydCgxKT4='), base64_decode('PGJyPg==')],
            'fullwidth angle brackets'       => [base64_decode('77ycc2NyaXB077yeYWxlcnQoMSnvvJwvc2NyaXB077ye'), base64_decode('77ycc2NyaXB077yeYWxlcnQoMSnvvJwvc2NyaXB077ye')],
        ];
    }

    #[DataProvider('realWordPressOutputs')]
    public function test_harness_matches_real_wordpress(string $input, string $expected): void
    {
        $this->assertSame($expected, wp_kses($input, ['br' => []]));
    }
}
