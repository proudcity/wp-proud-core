<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Proud\Core\resolve_taxonomy_filter_slugs() in proud-helpers.php.
 *
 * Issue #2720. The teaser category filter has carried three different value
 * types in ?filter_categories[] over its life:
 *
 *   - term IDs      -- contact-submenu-widget.class.php:78 still builds
 *                      "?filter_categories[]=" . $cat->term_id
 *   - term names    -- what the Elastic facet has emitted since the
 *                      aggregation was added, e.g. "Public Works"
 *   - term slugs    -- what the facet emits after #2720
 *
 * All three are live in bookmarks, in the Zendesk ticket, and in markup on
 * production, so the resolver has to accept any of them and normalise to a
 * slug. Slugs are the target because they are the only one of the three that
 * is stable across a rename, unique within a taxonomy, and guaranteed by
 * sanitize_title() to contain no character that can break out of the
 * unescaped id="" / name="" attributes in
 * modules/proud-form/templates/option-box.php.
 *
 * The entity cases are the reason this is a helper and not an inline
 * get_term_by() call. Term names are stored HTML-encoded -- the San Rafael
 * database holds "Arts &amp; Culture", not "Arts & Culture" -- while the
 * value coming back from the Elastic facet has been decoded for display. A
 * plain get_term_by('name', 'Arts & Culture') misses, which is exactly how
 * four categories silently stopped matching.
 */
class TaxonomyFilterSlugsTest extends TestCase
{
    /**
     * Stand-in for the terms on a site, keyed the way get_term_by() looks
     * them up. Names are stored as WordPress stores them: HTML-encoded.
     */
    private const TERMS = [
        ['term_id' => 578, 'slug' => 'public-works',    'name' => 'Public Works'],
        ['term_id' => 545, 'slug' => 'everything-traffic', 'name' => 'Everything Traffic'],
        ['term_id' => 610, 'slug' => 'arts-culture',    'name' => 'Arts &amp; Culture'],
        ['term_id' => 611, 'slug' => 'library-recreation', 'name' => 'Library &amp; Recreation'],
        ['term_id' => 612, 'slug' => 'cafe',            'name' => 'Café'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('sanitize_title')->alias(function ($value) {
            $value = strtolower(trim((string) $value));
            $value = preg_replace('~[^a-z0-9]+~', '-', $value);
            return trim($value, '-');
        });

        // Models get_term_by() closely enough for this helper: exact match on
        // the requested field, false when nothing matches, and 'id' coerced to
        // an integer the way WordPress does.
        Functions\when('get_term_by')->alias(function ($field, $value, $taxonomy = '') {
            if ('category' !== $taxonomy) {
                return false;
            }
            foreach (self::TERMS as $term) {
                $matches = 'id' === $field
                    ? (int) $value === $term['term_id']
                    : (string) $value === $term[$field];
                if ($matches) {
                    return (object) $term;
                }
            }
            return false;
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function resolve($values, $taxonomy = 'category'): array
    {
        return \Proud\Core\resolve_taxonomy_filter_slugs($values, $taxonomy);
    }

    // ---------------------------------------------------------------------
    // The three accepted input shapes
    // ---------------------------------------------------------------------

    public function testResolvesASlugToItself(): void
    {
        $this->assertSame(['public-works'], $this->resolve(['public-works']));
    }

    public function testResolvesATermNameToItsSlug(): void
    {
        $this->assertSame(['public-works'], $this->resolve(['Public Works']));
    }

    public function testResolvesANumericTermIdToItsSlug(): void
    {
        $this->assertSame(['public-works'], $this->resolve([578]));
    }

    public function testResolvesATermIdArrivingAsAStringFromTheQueryString(): void
    {
        // Everything in $_GET is a string; the contact submenu widget builds
        // "?filter_categories[]=578".
        $this->assertSame(['public-works'], $this->resolve(['578']));
    }

    // ---------------------------------------------------------------------
    // Entity-encoded names -- the "some filters" half of #2720
    // ---------------------------------------------------------------------

    public function testResolvesADecodedAmpersandNameAgainstTheEncodedStoredName(): void
    {
        // What the Elastic facet emits after html_entity_decode().
        $this->assertSame(['arts-culture'], $this->resolve(['Arts & Culture']));
    }

    public function testResolvesAnAlreadyEncodedAmpersandName(): void
    {
        // What an older bookmark or a raw copy of the stored name looks like.
        $this->assertSame(['arts-culture'], $this->resolve(['Arts &amp; Culture']));
    }

    public function testResolvesEveryAmpersandCategoryOnTheSanRafaelSite(): void
    {
        $this->assertSame(
            ['arts-culture', 'library-recreation'],
            $this->resolve(['Arts & Culture', 'Library & Recreation'])
        );
    }

    public function testDoesNotMangleAccentedCharactersWhileEncodingAmpersands(): void
    {
        // htmlentities() would turn "Café" into "Caf&eacute;" and miss the
        // term; htmlspecialchars() leaves non-ASCII alone.
        $this->assertSame(['cafe'], $this->resolve(['Café']));
    }

    // ---------------------------------------------------------------------
    // Array shapes
    // ---------------------------------------------------------------------

    public function testAcceptsTheAssociativeShapeAPostedCheckboxGroupProduces(): void
    {
        // FormHelper renders name="...[filter_categories][public-works]", so a
        // direct POST arrives keyed rather than as a list.
        $values = ['public-works' => 'public-works', 'everything-traffic' => 'everything-traffic'];

        $this->assertSame(['public-works', 'everything-traffic'], $this->resolve($values));
    }

    public function testAcceptsABareScalarInsteadOfAnArray(): void
    {
        $this->assertSame(['public-works'], $this->resolve('public-works'));
    }

    public function testReturnsAListWithSequentialKeys(): void
    {
        $resolved = $this->resolve(['x' => 'public-works', 'y' => 'everything-traffic']);

        $this->assertSame([0, 1], array_keys($resolved));
    }

    // ---------------------------------------------------------------------
    // Mixed, duplicate and empty input
    // ---------------------------------------------------------------------

    public function testResolvesAMixOfSlugsNamesAndIdsInOneRequest(): void
    {
        $this->assertSame(
            ['public-works', 'everything-traffic', 'arts-culture'],
            $this->resolve(['public-works', 'Everything Traffic', '610'])
        );
    }

    public function testDeduplicatesValuesThatResolveToTheSameTerm(): void
    {
        $this->assertSame(['public-works'], $this->resolve(['public-works', 'Public Works', 578]));
    }

    public function testPreservesTheOrderTheValuesArrivedIn(): void
    {
        $this->assertSame(
            ['everything-traffic', 'public-works'],
            $this->resolve(['Everything Traffic', 'Public Works'])
        );
    }

    public function testDropsEmptyAndWhitespaceOnlyValues(): void
    {
        $this->assertSame(['public-works'], $this->resolve(['', '   ', 'public-works']));
    }

    public function testTrimsSurroundingWhitespaceBeforeResolving(): void
    {
        $this->assertSame(['public-works'], $this->resolve(['  Public Works  ']));
    }

    public function testIgnoresNonScalarValues(): void
    {
        $this->assertSame(['public-works'], $this->resolve([['nested'], null, 'public-works']));
    }

    public function testReturnsAnEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], $this->resolve([]));
        $this->assertSame([], $this->resolve(''));
        $this->assertSame([], $this->resolve(['', '  ']));
    }

    // ---------------------------------------------------------------------
    // Unresolvable values
    //
    // A filter for a category that does not exist must produce no results,
    // never every result. Falling back to the sanitised value keeps the
    // caller's tax_query non-empty so it still matches nothing, rather than
    // handing back [] and tempting the caller to drop the clause.
    // ---------------------------------------------------------------------

    public function testFallsBackToASanitisedSlugWhenNothingResolves(): void
    {
        $this->assertSame(['no-such-category'], $this->resolve(['No Such Category']));
    }

    public function testFallbackNeverReturnsAnEmptyArrayForNonEmptyInput(): void
    {
        $this->assertNotSame([], $this->resolve(['No Such Category']));
    }

    public function testKeepsOnlyTheResolvedValuesWhenSomeOfThemResolve(): void
    {
        $this->assertSame(
            ['public-works'],
            $this->resolve(['Public Works', 'No Such Category'])
        );
    }

    public function testFallbackStripsCharactersThatCouldBreakOutOfAnAttribute(): void
    {
        // option-box.php echoes the option key into id="" and name="" without
        // escaping, so nothing this returns may contain a quote or a bracket.
        $resolved = $this->resolve(['bad" onfocus=alert(1) x']);

        $this->assertCount(1, $resolved);
        $this->assertMatchesRegularExpression('~^[a-z0-9-]+$~', $resolved[0]);
    }

    public function testEveryReturnedValueIsSlugShaped(): void
    {
        $resolved = $this->resolve([
            'public-works',
            'Arts & Culture',
            578,
            'No Such Category',
            '<script>alert(1)</script>',
        ]);

        foreach ($resolved as $slug) {
            $this->assertMatchesRegularExpression('~^[a-z0-9-]+$~', $slug);
        }
    }

    // ---------------------------------------------------------------------
    // Taxonomy scoping
    // ---------------------------------------------------------------------

    public function testDoesNotMatchTermsFromAnotherTaxonomy(): void
    {
        // "Public Works" also exists as a document_taxonomy, staff-member-group
        // and faq-topic term on San Rafael. Matching by name across taxonomies
        // is exactly the collision the slug switch is meant to remove.
        $this->assertSame(['public-works'], $this->resolve(['Public Works'], 'document_taxonomy'));
    }

    public function testReturnsAnEmptyArrayWhenNoTaxonomyIsGiven(): void
    {
        $this->assertSame([], $this->resolve(['public-works'], ''));
    }

    // ---------------------------------------------------------------------
    // Numeric slugs
    //
    // A bare number in ?filter_categories[] is ambiguous: the contact submenu
    // widget means a term ID by it, and the filter form means a slug, which
    // WordPress permits to be fully numeric (a category named "2024" gets the
    // slug "2024"). The form is the dominant source now, so the slug wins and
    // the term ID remains the fallback -- which still resolves every ID,
    // because no slug matches a bare ID unless someone deliberately made one.
    // ---------------------------------------------------------------------

    public function testPrefersANumericSlugOverATermIdOfTheSameNumber(): void
    {
        Functions\when('get_term_by')->alias(function ($field, $value, $taxonomy = '') {
            if ('slug' === $field && '2024' === (string) $value) {
                return (object) ['term_id' => 900, 'slug' => '2024', 'name' => '2024'];
            }
            if ('id' === $field && 2024 === (int) $value) {
                return (object) ['term_id' => 2024, 'slug' => 'something-else', 'name' => 'Something Else'];
            }
            return false;
        });

        $this->assertSame(['2024'], $this->resolve(['2024']));
    }

    public function testStillResolvesABareTermIdWhenNoSlugMatchesIt(): void
    {
        $this->assertSame(['public-works'], $this->resolve(['578']));
    }

    // ---------------------------------------------------------------------
    // Unresolvable input must never widen the result set
    //
    // Security review finding 4. The fallback sanitises the raw values, but
    // sanitize_title('-'), sanitize_title('%') and sanitize_title('&amp;') all
    // return '', so a request built only from those emptied the array. Both
    // call sites guard with if (!empty($terms)), so an empty return dropped
    // the tax_query entirely and served every published post -- where the old
    // (int) cast produced term_id 0 and therefore nothing.
    // ---------------------------------------------------------------------

    public function testDoesNotReturnEmptyWhenEveryValueSanitisesToNothing(): void
    {
        $this->assertNotSame([], $this->resolve(['-']));
        $this->assertNotSame([], $this->resolve(['%']));
        $this->assertNotSame([], $this->resolve(['&amp;']));
        $this->assertNotSame([], $this->resolve(['-', '%', '&amp;']));
    }

    public function testAValueThatSanitisesToNothingCannotMatchAnyTerm(): void
    {
        $resolved = $this->resolve(['-']);

        foreach ($resolved as $slug) {
            $this->assertFalse(get_term_by('slug', $slug, 'category'), "'$slug' must not match a real term");
        }
    }

    // ---------------------------------------------------------------------
    // Lookup amplification
    //
    // Security review finding 2. Each value costs up to four uncached
    // get_term_by() calls -- slug, term ID, three name candidates -- and the
    // parameter is unauthenticated GET input, so
    // an uncapped list is a query amplifier on a URL that also bypasses page
    // caching. PHP's default max_input_vars is 1000.
    // ---------------------------------------------------------------------

    public function testCapsTheNumberOfValuesItWillLookUp(): void
    {
        $calls = 0;
        Functions\when('get_term_by')->alias(function () use (&$calls) {
            $calls++;
            return false;
        });

        $this->resolve(range(1, 1000));

        $this->assertLessThanOrEqual(400, $calls, 'uncapped term lookups are a DoS amplifier');
    }

    public function testDeduplicatesBeforeLookingTermsUpNotJustAfter(): void
    {
        $calls = 0;
        Functions\when('get_term_by')->alias(function ($field, $value, $taxonomy = '') use (&$calls) {
            $calls++;
            return false;
        });

        $this->resolve(array_fill(0, 200, 'same-value'));

        $this->assertLessThanOrEqual(4, $calls, 'repeated values must be looked up once');
    }

    public function testStillResolvesARealisticNumberOfSelectedCategories(): void
    {
        // The cap must sit well above any real facet selection.
        $this->assertSame(
            ['public-works', 'everything-traffic'],
            $this->resolve(['public-works', 'everything-traffic'])
        );
    }

    // ---------------------------------------------------------------------
    // The cap tracks the size of the taxonomy (#2923)
    //
    // A flat 50 had to be both high enough not to truncate a real selection
    // and low enough to bound the amplifier. On a site with 91 categories
    // those are different numbers: ticking every box is a legitimate request
    // that came back silently truncated. What the cap defends against is an
    // unbounded list, and a list longer than the taxonomy cannot be anything
    // else.
    // ---------------------------------------------------------------------

    /**
     * @param int $terms Terms the taxonomy reports.
     */
    private function withTermCount(int $terms): void
    {
        Functions\when('wp_count_terms')->justReturn($terms);
        Functions\when('is_wp_error')->justReturn(false);
    }

    public function testCapRisesWithTheNumberOfTermsInTheTaxonomy(): void
    {
        $this->withTermCount(91);

        $this->assertSame(91, \Proud\Core\resolve_taxonomy_filter_max_values('category'));
    }

    public function testCapNeverFallsBelowTheFloor(): void
    {
        $this->withTermCount(4);

        $this->assertSame(
            \Proud\Core\RESOLVE_TAXONOMY_FILTER_MAX_VALUES,
            \Proud\Core\resolve_taxonomy_filter_max_values('category'),
            'a small taxonomy must not shrink the cap below its historical value'
        );
    }

    public function testCapIsBoundedByTheCeiling(): void
    {
        $this->withTermCount(50000);

        $this->assertSame(
            \Proud\Core\RESOLVE_TAXONOMY_FILTER_MAX_VALUES_CEILING,
            \Proud\Core\resolve_taxonomy_filter_max_values('category')
        );
    }

    public function testCapFallsBackToTheFloorWhenTheCountIsUnavailable(): void
    {
        Functions\when('wp_count_terms')->justReturn(new \stdClass());
        Functions\when('is_wp_error')->justReturn(true);

        $this->assertSame(
            \Proud\Core\RESOLVE_TAXONOMY_FILTER_MAX_VALUES,
            \Proud\Core\resolve_taxonomy_filter_max_values('category'),
            'a miscount must restore the old behaviour, never break the filter'
        );
    }

    public function testResolvesEveryBoxOnALargeTaxonomyWithoutTruncating(): void
    {
        $this->withTermCount(91);

        $values   = [];
        $expected = [];
        for ($i = 1; $i <= 91; $i++) {
            $values[]   = 'cat-' . $i;
            $expected[] = 'cat-' . $i;
        }

        Functions\when('get_term_by')->alias(function ($field, $value, $taxonomy = '') {
            return ('category' === $taxonomy && 'slug' === $field)
                ? (object) ['term_id' => 1, 'slug' => (string) $value, 'name' => (string) $value]
                : false;
        });

        $this->assertSame(
            $expected,
            $this->resolve($values),
            'ticking every box on a 91-category site must not be truncated'
        );
    }

    public function testStillCapsAListLongerThanTheTaxonomy(): void
    {
        $this->withTermCount(91);

        $calls = 0;
        Functions\when('get_term_by')->alias(function () use (&$calls) {
            $calls++;
            return false;
        });

        $this->resolve(range(1, 5000));

        $this->assertLessThanOrEqual(
            91 * 5,
            $calls,
            'a list longer than the taxonomy is not a real selection and must still be cut'
        );
    }
}
