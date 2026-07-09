<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Proud\Gform\proud_gform_stateless_active() in
 * plugin_override/gravityforms/proud-gravityforms.php.
 *
 * Regression cover for issue #2857: when the WP-Stateless gravity-form module
 * is not registered, Module::get_module('gravity-form') returns false and the
 * old code indexed ['enabled'] straight onto that false, raising a PHP warning
 * on every init. The helper now null-coalesces before filter_var(), so the
 * array access never touches a non-array.
 */
class GravityFormsStatelessModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The Module stub returns whatever we stage here per test.
        StatelessModuleStub::$return = false;
    }

    /**
     * The regression case: get_module() returns false. The helper must return
     * false AND emit no PHP warning (the whole point of the fix).
     */
    public function test_module_false_returns_false_without_warning(): void
    {
        StatelessModuleStub::$return = false;

        set_error_handler(static function ($severity, $message) {
            throw new \RuntimeException("Unexpected PHP notice/warning: {$message}");
        });

        try {
            $result = \Proud\Gform\proud_gform_stateless_active();
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($result);
    }

    /**
     * Module registered and enabled => true.
     */
    public function test_module_enabled_returns_true(): void
    {
        StatelessModuleStub::$return = ['enabled' => true];

        $this->assertTrue(\Proud\Gform\proud_gform_stateless_active());
    }

    /**
     * Module registered but explicitly disabled => false.
     */
    public function test_module_disabled_returns_false(): void
    {
        StatelessModuleStub::$return = ['enabled' => false];

        $this->assertFalse(\Proud\Gform\proud_gform_stateless_active());
    }

    /**
     * Module array present but missing the 'enabled' key => false via the
     * null-coalesce default (no undefined-index warning).
     */
    public function test_module_missing_enabled_key_returns_false(): void
    {
        StatelessModuleStub::$return = [];

        $this->assertFalse(\Proud\Gform\proud_gform_stateless_active());
    }

    /**
     * Truthy string values are coerced by FILTER_VALIDATE_BOOLEAN, matching
     * how WP-Stateless stores the option.
     */
    public function test_module_enabled_truthy_string_returns_true(): void
    {
        StatelessModuleStub::$return = ['enabled' => 'true'];

        $this->assertTrue(\Proud\Gform\proud_gform_stateless_active());
    }

    /**
     * Well-formed export ids (the charset GF actually emits) are accepted.
     */
    #[DataProvider('validExportIds')]
    public function test_valid_export_id_is_accepted(string $id): void
    {
        $this->assertTrue(\Proud\Gform\proud_gform_valid_export_id($id));
    }

    public static function validExportIds(): array
    {
        return [
            'hex hash'       => ['a1b2c3d4e5f6'],
            'alphanumeric'   => ['Export123'],
            'with dash'      => ['2026-07-09-export'],
            'with underscore' => ['form_42_export'],
        ];
    }

    /**
     * Path-traversal, scheme, and separator payloads are rejected before the id
     * ever reaches the readfile() URL (issue #2859).
     */
    #[DataProvider('maliciousExportIds')]
    public function test_malicious_export_id_is_rejected($id): void
    {
        $this->assertFalse(\Proud\Gform\proud_gform_valid_export_id($id));
    }

    public static function maliciousExportIds(): array
    {
        return [
            'dot-dot traversal'   => ['../../../../etc/passwd'],
            'encoded traversal'   => ['..%2f..%2fsecret'],
            'trailing extension'  => ['export.csv'],
            'php wrapper'         => ['php://filter/resource=x'],
            'remote url'          => ['https://evil.example/x'],
            'null byte'           => ["abc\0.csv"],
            'slash'               => ['a/b'],
            'empty string'        => [''],
            'not a string (null)' => [null],
            'not a string (array)' => [['x']],
        ];
    }
}
