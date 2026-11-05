<?php

use PHPUnit\Framework\TestCase;

use function Proud\Core\proud_sanitize_icon_class;

/**
 * Tests for proud_sanitize_icon_class() -- issue #2768.
 *
 * Term `icon` meta is saved raw from POST and reaches HTML class attributes,
 * so the helper is a strict allowlist.
 */
class SanitizeIconClassTest extends TestCase
{
    /**
     * @dataProvider validValues
     */
    public function test_real_values_pass_unchanged($value): void
    {
        $this->assertSame($value, proud_sanitize_icon_class($value));
    }

    public static function validValues(): array
    {
        return array(
            array('fa-gavel'),
            array('fa-solid fa-tree'),
            array('fa-solid fa-car-side'),
            array('fa-solid fa-charging-station'),
            array(''),
        );
    }

    public function test_whitespace_is_trimmed_and_collapsed(): void
    {
        $this->assertSame('fa-solid fa-tree', proud_sanitize_icon_class("  fa-solid \t fa-tree\n"));
    }

    /**
     * @dataProvider invalidValues
     */
    public function test_invalid_values_return_empty_string($value): void
    {
        $this->assertSame('', proud_sanitize_icon_class($value));
    }

    public static function invalidValues(): array
    {
        return array(
            'attr breakout'  => array('fa-x"><img src=x onerror=alert(1)>'),
            'single quote'   => array("fa-x' onmouseover="),
            'script'         => array('<script>'),
            'semicolon'      => array('fa-a;b'),
            'one bad token'  => array('fa-solid fa-x"y'),
            'five tokens'    => array('a b c d e'),
            'too long'       => array(str_repeat('a', 101)),
            'array'          => array(array('fa-gavel')),
            'int'            => array(5),
            'null'           => array(null),
            'bool'           => array(true),
        );
    }
}
