<?php

/**
 * Stubs for the Gravity Forms stateless helper tests.
 *
 * proud_gform_stateless_active() calls
 * \wpCloud\StatelessMedia\Module::get_module('gravity-form'). We stand in a
 * minimal Module class whose return value the test stages via
 * StatelessModuleStub::$return, so each case can drive get_module() to return
 * false / an array / a missing key without a full WP-Stateless install.
 *
 * proud_gform_stateless_available() calls ud_get_stateless_media()->get(...).
 * PHP cannot undefine a function once declared, so the "WP-Stateless is not
 * installed at all" case — the one that fataled Santa Ana in issue #55 — is
 * staged through StatelessMediaStub::$installed, which makes the stub throw the
 * same Error PHP raises for an undefined function.
 */

namespace {
    // Test-controlled holder in the global namespace for easy access from tests.
    class StatelessModuleStub
    {
        /** @var mixed value get_module() should return */
        public static $return = false;
    }

    /**
     * Stand-in for the WP-Stateless singleton returned by ud_get_stateless_media().
     */
    class StatelessMediaStub
    {
        /** @var bool false makes ud_get_stateless_media() behave as undefined */
        public static $installed = true;

        /** @var array<string,mixed> values get() should return, keyed by setting */
        public static $settings = ['sm.bucket' => 'proudcity'];

        /** @var \Throwable|null thrown from get() when set, to model a broken install */
        public static $throwOnGet = null;

        public function get($key)
        {
            if (self::$throwOnGet !== null) {
                throw self::$throwOnGet;
            }

            return self::$settings[$key] ?? null;
        }
    }

    if (! function_exists('ud_get_stateless_media')) {
        function ud_get_stateless_media()
        {
            if (! \StatelessMediaStub::$installed) {
                // Matches what PHP throws when the plugin is absent, so
                // proud_gform_stateless_available() is exercised against the
                // real failure shape rather than a friendlier stand-in.
                throw new \Error('Call to undefined function ud_get_stateless_media()');
            }

            return new \StatelessMediaStub();
        }
    }
}

namespace wpCloud\StatelessMedia {
    class Module
    {
        public static function get_module($slug)
        {
            return \StatelessModuleStub::$return;
        }
    }
}
