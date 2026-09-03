<?php

/**
 * Stub Proud\Core\ProudWidget for testing IconLink::printWidget().
 *
 * The real ProudWidget (modules/proud-widget/widget-base.class.php) extends
 * \WP_Widget and pulls in the whole widget/form-helper stack. printWidget()
 * touches none of it, so a bare stand-in with a permissive constructor is
 * enough to instantiate IconLink and call the method under test.
 *
 * Declared in its own file so the namespace declaration can be the first
 * statement, matching document-stubs.php.
 */

namespace Proud\Core;

if (!class_exists('Proud\Core\ProudWidget')) {
    abstract class ProudWidget
    {
        public $settings = [];

        public function __construct($id_base = '', $name = '', $widget_options = [], $control_options = [])
        {
        }
    }
}
