<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for the render-time autocomplete helpers in
 * plugin_override/gravityforms/proud-gravityforms.php (wp-proudcity#3021,
 * WCAG 2.1 SC 1.3.5). Fields are plain stdClass objects; GF is not loaded.
 */
class GravityFormsAutocompleteTest extends TestCase
{
    private function field(string $type, array $props = []): stdClass
    {
        $f = new stdClass();
        $f->type = $type;
        foreach ($props as $k => $v) {
            $f->$k = $v;
        }
        return $f;
    }

    private function inputs(int $fieldId, array $suffixes, array $existing = []): array
    {
        $out = [];
        foreach ($suffixes as $s) {
            $input = ['id' => $fieldId . '.' . $s, 'label' => 'x'];
            if (isset($existing[ $s ])) {
                $input['autocompleteAttribute'] = $existing[ $s ];
            }
            $out[] = $input;
        }
        return $out;
    }

    private function tokens(stdClass $f): array
    {
        $out = [];
        foreach ($f->inputs as $input) {
            $out[ $input['id'] ] = $input['autocompleteAttribute'] ?? null;
        }
        return $out;
    }

    private function render(stdClass $f): stdClass
    {
        $form = \Proud\Gform\proud_gform_autocomplete_pre_render(['fields' => [$f]]);
        return $form['fields'][0];
    }

    public function test_name_advanced_inputs_get_tokens(): void
    {
        $f   = $this->field('name', ['nameFormat' => 'advanced', 'inputs' => $this->inputs(4, [2, 3, 4, 6, 8])]);
        $out = $this->render($f);

        $this->assertTrue($out->enableAutocomplete);
        $this->assertSame([
            '4.2' => 'honorific-prefix',
            '4.3' => 'given-name',
            '4.4' => 'additional-name',
            '4.6' => 'family-name',
            '4.8' => 'honorific-suffix',
        ], $this->tokens($out));
    }

    public function test_name_extended_format_is_handled(): void
    {
        $f   = $this->field('name', ['nameFormat' => 'extended', 'inputs' => $this->inputs(4, [3, 6])]);
        $out = $this->render($f);

        $this->assertSame(['4.3' => 'given-name', '4.6' => 'family-name'], $this->tokens($out));
    }

    public function test_simple_and_normal_name_formats_untouched(): void
    {
        foreach (['simple', 'normal', '', 'something-else'] as $format) {
            $f = $this->field('name', ['nameFormat' => $format, 'inputs' => $this->inputs(4, [3, 6])]);
            $this->assertSame($f, $this->render($f), "format '{$format}'");
            $this->assertFalse(isset($f->enableAutocomplete));
        }
        $noFormat = $this->field('name', ['inputs' => $this->inputs(4, [3, 6])]);
        $this->assertSame($noFormat, $this->render($noFormat));
    }

    public function test_address_inputs_mapped_and_unknown_suffix_untouched(): void
    {
        $f   = $this->field('address', ['inputs' => $this->inputs(7, [1, 2, 3, 4, 5, 6, 9])]);
        $out = $this->render($f);

        $this->assertTrue($out->enableAutocomplete);
        $this->assertSame([
            '7.1' => 'address-line1',
            '7.2' => 'address-line2',
            '7.3' => 'address-level2',
            '7.4' => 'address-level1',
            '7.5' => 'postal-code',
            '7.6' => 'country-name',
            '7.9' => null,
        ], $this->tokens($out));
    }

    public function test_email_single(): void
    {
        $out = $this->render($this->field('email'));

        $this->assertTrue($out->enableAutocomplete);
        $this->assertSame('email', $out->autocompleteAttribute);
    }

    public function test_email_confirmation_both_inputs(): void
    {
        $f   = $this->field('email', [
            'emailConfirmEnabled' => true,
            'inputs'              => [['id' => '5'], ['id' => '5.2']],
        ]);
        $out = $this->render($f);

        $this->assertSame('email', $out->autocompleteAttribute);
        $this->assertSame(['5' => 'email', '5.2' => 'email'], $this->tokens($out));
    }

    public function test_email_confirmation_with_null_inputs_does_not_error(): void
    {
        $out = $this->render($this->field('email', ['emailConfirmEnabled' => true, 'inputs' => null]));

        $this->assertSame('email', $out->autocompleteAttribute);
        $this->assertNull($out->inputs);
    }

    public function test_phone_and_website(): void
    {
        $this->assertSame('tel', $this->render($this->field('phone'))->autocompleteAttribute);
        $this->assertSame('url', $this->render($this->field('website'))->autocompleteAttribute);
        $this->assertTrue($this->render($this->field('phone'))->enableAutocomplete);
    }

    public function test_admin_tokens_preserved_including_off(): void
    {
        $f   = $this->field('name', [
            'nameFormat' => 'advanced',
            'inputs'     => $this->inputs(4, [3, 6], [3 => 'nickname', 6 => 'off']),
        ]);
        $out = $this->render($f);
        $this->assertSame(['4.3' => 'nickname', '4.6' => 'off'], $this->tokens($out));

        $phone = $this->render($this->field('phone', ['autocompleteAttribute' => 'off', 'enableAutocomplete' => false]));
        $this->assertSame('off', $phone->autocompleteAttribute);
        $this->assertTrue($phone->enableAutocomplete);
    }

    public function test_already_enabled_field_keeps_its_token(): void
    {
        $out = $this->render($this->field('email', ['enableAutocomplete' => true, 'autocompleteAttribute' => 'username']));

        $this->assertSame('username', $out->autocompleteAttribute);
        $this->assertTrue($out->enableAutocomplete);
    }

    public function test_blank_and_whitespace_tokens_are_filled(): void
    {
        foreach (['', '   ', null, false] as $blank) {
            $out = $this->render($this->field('phone', ['autocompleteAttribute' => $blank]));
            $this->assertSame('tel', $out->autocompleteAttribute);
        }

        $f   = $this->field('address', ['inputs' => $this->inputs(7, [1, 2], [1 => '', 2 => ' '])]);
        $out = $this->render($f);
        $this->assertSame(['7.1' => 'address-line1', '7.2' => 'address-line2'], $this->tokens($out));
    }

    public function test_non_allowlisted_types_return_same_object(): void
    {
        foreach (['text', 'textarea', 'select', 'creditcard', 'stripe_creditcard', 'product', 'hidden', 'number'] as $type) {
            $f = $this->field($type, ['inputs' => $this->inputs(9, [1, 3])]);
            $this->assertSame($f, $this->render($f), $type);
            $this->assertFalse(isset($f->enableAutocomplete), $type);
            $this->assertSame([ '9.1' => null, '9.3' => null ], $this->tokens($f), $type);
        }
    }

    public function test_original_field_is_not_mutated(): void
    {
        $f   = $this->field('name', ['nameFormat' => 'advanced', 'inputs' => $this->inputs(4, [3])]);
        $out = $this->render($f);

        $this->assertNotSame($f, $out);
        $this->assertFalse(isset($f->enableAutocomplete));
        $this->assertSame(['4.3' => null], $this->tokens($f));
    }

    public function test_idempotent(): void
    {
        $f     = $this->field('address', ['inputs' => $this->inputs(7, [1, 2, 3, 4, 5, 6])]);
        $once  = \Proud\Gform\proud_gform_autocomplete_pre_render(['fields' => [$f]]);
        $twice = \Proud\Gform\proud_gform_autocomplete_pre_render($once);

        $this->assertEquals($once, $twice);
    }

    public function test_form_without_usable_fields_unchanged(): void
    {
        $this->assertSame(['id' => 1], \Proud\Gform\proud_gform_autocomplete_pre_render(['id' => 1]));
        $this->assertSame(['fields' => 'nope'], \Proud\Gform\proud_gform_autocomplete_pre_render(['fields' => 'nope']));
        $this->assertSame(['fields' => []], \Proud\Gform\proud_gform_autocomplete_pre_render(['fields' => []]));
    }

    public function test_token_with_quote_and_handler_is_replaced(): void
    {
        $bad = 'x" onfocus="alert(1)';
        $out = $this->render($this->field('phone', ['autocompleteAttribute' => $bad]));
        $this->assertSame('tel', $out->autocompleteAttribute);

        $f   = $this->field('address', ['inputs' => $this->inputs(7, [1], [1 => $bad])]);
        $this->assertSame(['7.1' => 'address-line1'], $this->tokens($this->render($f)));
    }

    public function test_array_token_is_replaced(): void
    {
        $out = $this->render($this->field('email', ['autocompleteAttribute' => ['email']]));
        $this->assertSame('email', $out->autocompleteAttribute);

        $f   = $this->field('name', ['nameFormat' => 'advanced', 'inputs' => $this->inputs(4, [3], [3 => ['a']])]);
        $this->assertSame(['4.3' => 'given-name'], $this->tokens($this->render($f)));
    }

    public function test_invalid_token_in_unmapped_slot_is_emptied(): void
    {
        $f   = $this->field('address', ['inputs' => $this->inputs(7, [9], [9 => '"><script>'])]);
        $this->assertSame(['7.9' => ''], $this->tokens($this->render($f)));
    }

    public function test_off_and_multi_token_values_kept(): void
    {
        $out = $this->render($this->field('email', ['autocompleteAttribute' => 'section-a email']));
        $this->assertSame('section-a email', $out->autocompleteAttribute);
        $this->assertSame('off', $this->render($this->field('website', ['autocompleteAttribute' => 'off']))->autocompleteAttribute);
    }
}
