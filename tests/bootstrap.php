<?php

/**
 * PHPUnit bootstrap for wp-proud-core.
 *
 * Load order is critical:
 *   1. Composer autoload — this loads Patchwork, which must be active before
 *      any file that contains functions you want to patch per-test.
 *   2. stubs.php — define minimal WP function stubs for load-time calls so
 *      plugin files can be required without a full WordPress install.
 *   3. Plugin files — included once here; PHPUnit runs all tests against
 *      these already-loaded definitions.
 *
 * Run tests from the plugin root:
 *   composer install
 *   vendor/bin/phpunit
 */

// Patchwork must be registered before any file that defines functions you want
// to mock per-test. Requiring it explicitly here, before anything else, ensures
// its stream wrapper is in place when stubs.php and the plugin files are loaded.
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';
require_once __DIR__ . '/../vendor/autoload.php';
// Real wp-includes/kses.php, loaded BEFORE stubs.php (whose definitions are all
// function_exists-guarded, so nothing shadows it).
//
// Proud\Core\esc_widget_title() is a thin wrapper over wp_kses(), so a stub
// would mean the tests measure the stub rather than WordPress. A hand-written
// model of kses was tried first and diverged from the real function on 22 of 78
// adversarial inputs -- HTML comments, "</ x>" bogus-comment tokens, NUL bytes
// and numeric character references were all wrong -- in both directions. Some
// of those divergences were in the dangerous direction, where a template test
// would pass while real WordPress emitted raw markup.
//
// kses.php loads standalone; the only things it needs at call time are
// wp_allowed_protocols() and the `pre_kses` filter, both provided in stubs.php.
$kses = __DIR__ . '/../../../../wp-includes/kses.php';
if (!is_readable($kses)) {
    fwrite(STDERR, "Cannot read {$kses}.\nThese tests load the real kses.php and must be run from inside a WordPress install.\n");
    exit(1);
}

// Since WordPress 7.0, wp_kses_hair() parses attributes with the HTML API
// rather than by regex, so kses.php no longer stands alone once an allowed
// element declares attributes. Allowlists with no attributes -- esc_widget_title()
// and friends -- return before that point, which is why this was not needed
// until proud_document_preview_allowed_html() (#2917) arrived.
//
// Load order follows wp-settings.php: the token map the character-reference
// table is built on, then spans and replacements, then the decoder and its
// table, then the attribute token type, then the processor.
$tokenMap = __DIR__ . '/../../../../wp-includes/class-wp-token-map.php';
if (!is_readable($tokenMap)) {
    fwrite(STDERR, "Cannot read {$tokenMap}.\nThe HTML API needs WP_Token_Map.\n");
    exit(1);
}
require_once $tokenMap;

$htmlApi = __DIR__ . '/../../../../wp-includes/html-api/';
foreach ([
    'class-wp-html-span.php',
    'class-wp-html-text-replacement.php',
    'html5-named-character-references.php',
    'class-wp-html-decoder.php',
    'class-wp-html-attribute-token.php',
    'class-wp-html-tag-processor.php',
] as $htmlApiFile) {
    if (!is_readable($htmlApi . $htmlApiFile)) {
        fwrite(STDERR, "Cannot read {$htmlApi}{$htmlApiFile}.\nwp_kses() needs the HTML API to parse attributes.\n");
        exit(1);
    }
    require_once $htmlApi . $htmlApiFile;
}

require_once $kses;
require_once __DIR__ . '/stubs.php';

// proud-helpers.php provides build_retina_image_meta (used by build_logo_meta)
// and the real pc_get_yoast_meta_or_excerpt (overrides the namespace stub above).
require_once __DIR__ . '/../proud-helpers.php';

// proud-menu.php defines ProudMenuUtil, ProudBreadcrumb and runs:
//   global $proud_menu_util; $proud_menu_util = new ProudMenuUtil();
// wp_get_nav_menus() returns [] from our stub so the constructor is a no-op.
require_once __DIR__ . '/../modules/proud-menu/proud-menu.php';

// proud-navbar.php defines build_logo_meta and wires up add_action hooks
// (stubbed, so no side effects).
require_once __DIR__ . '/../modules/proud-navbar/proud-navbar.php';

// proud-pagetitle.php defines proud_pagetitle_get_duplicates and registers
// wp_ajax hooks (stubbed, so no side effects).
require_once __DIR__ . '/../modules/proud-pagetitle/proud-pagetitle.php';

// proud-patternlibrary.php defines ProudPatternLibrary and instantiates it
// once. The constructor calls add_action (stubbed, so no side effects).
require_once __DIR__ . '/../modules/proud-patternlibrary/proud-patternlibrary.php';

// proud-layout.php defines ProudLayout; constructor calls add_filter/add_action
// (stubbed, so no side effects).
require_once __DIR__ . '/../modules/proud-layout/proud-layout.php';

// Stub Proud\Document namespace helpers so document-widget-ajax.php can be
// loaded without the wp-proud-document plugin present. Loaded via a separate
// file so the namespace declaration is the first statement in that file.
require_once __DIR__ . '/document-stubs.php';

// document-widget-ajax.php defines proud_document_search_callback() and
// proud_document_preview_callback(). Must be required after stubs so all
// WP functions it calls at load time are already defined.
require_once __DIR__ . '/../modules/proud-widget/widgets/document/document-widget-ajax.php';

// proud-wp-stateless.php defines proudcity_stateless_suffix_cache_bust() and
// registers the stateless_skip_cache_busting filter (add_filter is stubbed).
require_once __DIR__ . '/../plugin_override/wp-stateless/proud-wp-stateless.php';

// Provider-neutral HTML preview endpoint used by Document and Meeting templates.
require_once __DIR__ . '/../modules/proud-html-preview/proud-html-preview.php';

// events-manager-recurrence-slug.php defines
// proudcity_em_strip_accumulated_recurrence_dates() and registers the
// em_event_save_events_slug filter (add_filter is stubbed).
require_once __DIR__ . '/../modules/events-manager-recurrence-slug.php';

// gravityforms-stubs.php defines the \wpCloud\StatelessMedia\Module stub that
// proud_gform_stateless_active() calls, plus StatelessModuleStub for staging
// its return value per test.
require_once __DIR__ . '/gravityforms-stubs.php';

// proud-gravityforms.php defines Proud\Gform\proud_gform_stateless_active() at
// the top level (outside the class_exists('GFCommon') guard). GFCommon is not
// present in tests, so the guarded hook wiring is skipped and only the helper
// under test is defined.
require_once __DIR__ . '/../plugin_override/gravityforms/proud-gravityforms.php';

// icon-link-stubs.php provides a bare Proud\Core\ProudWidget so the IconLink
// widget class can be loaded without the WP_Widget/form-helper stack.
// IconLink::printWidget() is the unit under test (issue #2916).
// Shared trait wiring the `pre_kses` filter through Brain Monkey so wp_kses()
// behaves as it does on a real site. See the trait for why.
require_once __DIR__ . '/AppliesPreKsesFilter.php';

require_once __DIR__ . '/icon-link-stubs.php';
require_once __DIR__ . '/../modules/proud-widget/widgets/icon-link/icon-link-widget.class.php';

// cta-button-widget.class.php defines CTA, the sibling of IconLink. It uses the
// same stubbed Proud\Core\ProudWidget base, so it loads on the back of the
// require above. CTA::printWidget() is covered by CtaButtonWidgetTest (#2916).
require_once __DIR__ . '/../modules/proud-widget/widgets/cta-widget/cta-button-widget.class.php';
