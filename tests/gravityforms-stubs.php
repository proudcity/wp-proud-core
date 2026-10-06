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

    /**
     * Stubs for GravityFormsImageCompressionTest.php (issue #2912).
     *
     * proud_gform_compress_image_file() and friends are pure helpers living
     * outside the class_exists('GFCommon') guard, so they need a handful of
     * real WordPress functions they call at runtime. WP_Error/is_wp_error are
     * faithful minimal stand-ins; trailingslashit/wp_normalize_path are
     * verbatim copies of wp-includes/formatting.php and functions.php (same
     * rationale as sanitize_hex_color() above -- these are pure functions with
     * no side effects, so copying is safer than a permissive passthrough).
     * wp_get_image_editor() is deliberately NOT usable out of the box -- every
     * test that needs one supplies its own Mockery double via
     * Functions\when('wp_get_image_editor').
     */
    if (! class_exists('WP_Error')) {
        class WP_Error
        {
            public $errors = [];

            public function __construct($code = '', $message = '')
            {
                if ($code) {
                    $this->errors[$code][] = $message;
                }
            }

            public function get_error_message()
            {
                foreach ($this->errors as $messages) {
                    return reset($messages);
                }

                return '';
            }
        }
    }

    if (! function_exists('is_wp_error')) {
        function is_wp_error($thing)
        {
            return $thing instanceof \WP_Error;
        }
    }

    if (! function_exists('wp_get_image_editor')) {
        function wp_get_image_editor($path)
        {
            return new \WP_Error('image_editor_not_stubbed', 'Test did not stub wp_get_image_editor().');
        }
    }

    if (! function_exists('wp_raise_memory_limit')) {
        function wp_raise_memory_limit($context = '')
        {
            return true;
        }
    }

    // Verbatim from wp-includes/load.php.
    if (! function_exists('wp_convert_hr_to_bytes')) {
        function wp_convert_hr_to_bytes($value)
        {
            $value = strtolower(trim((string) $value));
            $bytes = (int) $value;

            if (str_contains($value, 'g')) {
                $bytes *= 1024 * 1024 * 1024;
            } elseif (str_contains($value, 'm')) {
                $bytes *= 1024 * 1024;
            } elseif (str_contains($value, 'k')) {
                $bytes *= 1024;
            }

            return min($bytes, PHP_INT_MAX);
        }
    }

    // Verbatim from wp-includes/formatting.php.
    if (! function_exists('untrailingslashit')) {
        function untrailingslashit($string)
        {
            return rtrim((string) $string, '/\\');
        }
    }
    if (! function_exists('trailingslashit')) {
        function trailingslashit($string)
        {
            return untrailingslashit($string) . '/';
        }
    }

    // Verbatim from wp-includes/functions.php (minus the multisite VIP filter,
    // which does not apply outside a full WP load).
    if (! function_exists('wp_is_stream')) {
        function wp_is_stream($path)
        {
            $scheme_separator = strpos((string) $path, '://');
            if (false === $scheme_separator) {
                return false;
            }

            $stream = substr($path, 0, $scheme_separator);

            return in_array($stream, stream_get_wrappers(), true);
        }
    }
    if (! function_exists('wp_normalize_path')) {
        function wp_normalize_path($path)
        {
            $wrapper = '';
            if (wp_is_stream($path)) {
                [$wrapper, $path] = explode('://', $path, 2);
                $wrapper .= '://';
            }

            $path = str_replace('\\', '/', $path);
            $path = preg_replace('|(?<=.)/+|', '/', $path);

            if (':' === substr($path, 1, 1)) {
                $path = ucfirst($path);
            }

            return $wrapper . $path;
        }
    }

    /**
     * Minimal stand-in for GFFormsModel. Only the three static methods
     * proud-gravityforms.php's image-compression code touches. $permission_calls
     * lets tests assert set_permissions() ran without a real file system
     * permission check.
     */
    if (! class_exists('GFFormsModel')) {
        class GFFormsModel
        {
            public static $upload_root     = '';
            public static $upload_url_root = '';
            public static $permission_calls = [];

            public static function get_upload_root()
            {
                return self::$upload_root;
            }

            public static function get_upload_url_root()
            {
                return self::$upload_url_root;
            }

            public static function set_permissions($path)
            {
                self::$permission_calls[] = $path;
            }
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
