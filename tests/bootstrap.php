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
