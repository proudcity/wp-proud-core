<?php

/**
 * Stubs for the Gravity Forms stateless helper test.
 *
 * proud_gform_stateless_active() calls
 * \wpCloud\StatelessMedia\Module::get_module('gravity-form'). We stand in a
 * minimal Module class whose return value the test stages via
 * StatelessModuleStub::$return, so each case can drive get_module() to return
 * false / an array / a missing key without a full WP-Stateless install.
 */

namespace {
    // Test-controlled holder in the global namespace for easy access from tests.
    class StatelessModuleStub
    {
        /** @var mixed value get_module() should return */
        public static $return = false;
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
