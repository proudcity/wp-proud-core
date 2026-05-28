<?php

/**
 * Stub Proud\Document namespace functions for testing.
 *
 * Provides minimal stand-ins for get_document_type() and get_document_icon()
 * so document-widget-ajax.php can be loaded without the wp-proud-document
 * plugin being present. Brain\Monkey can override these per-test.
 */

namespace Proud\Document;

if (!function_exists('Proud\Document\get_document_type')) {
    function get_document_type($post = 0, $filename = null) {
        return '';
    }
}

if (!function_exists('Proud\Document\get_document_icon')) {
    function get_document_icon($post = 0, $filename = null) {
        return 'fa-file';
    }
}
