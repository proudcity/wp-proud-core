<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Proud\Gform\proud_gform_stateless_available() in
 * plugin_override/gravityforms/proud-gravityforms.php.
 *
 * Regression cover for saintra issue #55. proud_gravityforms_init() used only
 * proud_gform_stateless_active() — "is WP-Stateless' gravity-form module on" —
 * to decide whether to register our Google Cloud Storage bridge. That helper
 * returns false both when Stateless is installed with the module off (the
 * bridge should run) and when Stateless is absent entirely (nothing here can
 * run), so on the DigitalOcean sites the bridge was registered anyway and
 * gform_secure_file_download_url() fataled on the undefined
 * ud_get_stateless_media() during notification sending — every submission of a
 * form carrying a file upload field.
 *
 * proud_gform_stateless_available() is the new, wider gate: is there a usable
 * bucket at all.
 */
class GravityFormsStatelessAvailableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Default to a healthy, configured WP-Stateless; each test degrades it.
        StatelessMediaStub::$installed  = true;
        StatelessMediaStub::$settings   = ['sm.bucket' => 'proudcity'];
        StatelessMediaStub::$throwOnGet = null;
    }

    protected function tearDown(): void
    {
        StatelessMediaStub::$installed  = true;
        StatelessMediaStub::$settings   = ['sm.bucket' => 'proudcity'];
        StatelessMediaStub::$throwOnGet = null;
        parent::tearDown();
    }

    /**
     * Installed and pointed at a bucket — the k8s case. The bridge may run.
     */
    public function test_installed_and_configured_returns_true(): void
    {
        $this->assertTrue(\Proud\Gform\proud_gform_stateless_available());
    }

    /**
     * The issue #55 case: WP-Stateless is not installed, so
     * ud_get_stateless_media() does not exist. Must return false rather than
     * letting the Error escape.
     */
    public function test_plugin_absent_returns_false(): void
    {
        StatelessMediaStub::$installed = false;

        $this->assertFalse(\Proud\Gform\proud_gform_stateless_available());
    }

    /**
     * Installed but never configured — no bucket setting at all. Registering the
     * bridge here would build https://storage.googleapis.com//... download URLs,
     * so it must fall through to Gravity Forms' native local handling.
     */
    public function test_missing_bucket_returns_false(): void
    {
        StatelessMediaStub::$settings = [];

        $this->assertFalse(\Proud\Gform\proud_gform_stateless_available());
    }

    /**
     * Bucket present but empty — same broken-URL outcome as a missing bucket.
     */
    public function test_empty_bucket_returns_false(): void
    {
        StatelessMediaStub::$settings = ['sm.bucket' => ''];

        $this->assertFalse(\Proud\Gform\proud_gform_stateless_available());
    }

    /**
     * A bucket of nothing but whitespace is not a bucket.
     */
    public function test_whitespace_bucket_returns_false(): void
    {
        StatelessMediaStub::$settings = ['sm.bucket' => "  \t\n"];

        $this->assertFalse(\Proud\Gform\proud_gform_stateless_available());
    }

    /**
     * Non-string bucket values (a truthy array from a corrupted option, say)
     * must not be trim()ed — that would itself raise a TypeError.
     */
    public function test_non_string_bucket_returns_false(): void
    {
        StatelessMediaStub::$settings = ['sm.bucket' => ['proudcity']];

        $this->assertFalse(\Proud\Gform\proud_gform_stateless_available());
    }

    /**
     * A half-initialised Stateless can raise from get(). Exceptions degrade to
     * the local path.
     */
    public function test_get_throwing_exception_returns_false(): void
    {
        StatelessMediaStub::$throwOnGet = new \RuntimeException('no client');

        $this->assertFalse(\Proud\Gform\proud_gform_stateless_available());
    }

    /**
     * ...and so do Errors, which is why the helper catches \Throwable rather
     * than \Exception.
     */
    public function test_get_throwing_error_returns_false(): void
    {
        StatelessMediaStub::$throwOnGet = new \TypeError('bad settings shape');

        $this->assertFalse(\Proud\Gform\proud_gform_stateless_available());
    }

    /**
     * The two helpers answer different questions and must not be collapsed back
     * into one. With Stateless absent, "available" is false while "active" is
     * also false — and it was the second, narrower answer alone that drove
     * registration and caused the fatal.
     */
    public function test_available_is_independent_of_module_enabled_state(): void
    {
        StatelessModuleStub::$return = ['enabled' => false];

        // Installed with the module off: the legacy bridge should still run.
        $this->assertTrue(\Proud\Gform\proud_gform_stateless_available());
        $this->assertFalse(\Proud\Gform\proud_gform_stateless_active());

        // Not installed: nothing should run, even though "active" reads the same.
        StatelessMediaStub::$installed = false;
        $this->assertFalse(\Proud\Gform\proud_gform_stateless_available());
        $this->assertFalse(\Proud\Gform\proud_gform_stateless_active());
    }
}
