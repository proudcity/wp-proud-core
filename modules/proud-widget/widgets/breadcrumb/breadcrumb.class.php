<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class BreadcrumbWidget extends Core\ProudWidget
{
    public function __construct()
    {
        parent::__construct(
            'proud_breadcrumb', // Base ID
            __('Breadcrumb', 'wp-proud-core'), // Name
            array( 'description' => __('Prints out breadcrumb path, page-title(optional)', 'wp-proud-core'), ) // Args
        );
    }

    public function initialize()
    {
        $this->settings = [
            'page_header' => [
                '#type' => 'checkbox',
                '#title' => 'Override page header?',
                '#description' => 'Prints out page header information above with breadcrumb.  <strong>Important!</strong> '
                                . 'if placed as the first item in a page with this checked, the normal page header region and '
                                . 'sidebar menu will be hidden.',
                '#return_value' => '1',
                '#label_above' => true,
                '#replace_title' => 'Yes',
                '#default_value' => true
            ]
        ];
    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function printWidget($args, $instance)
    {
        if (empty($instance['page_header'])) {
            Core\ProudBreadcrumb::print_breadcrumb();
        } else {
            // Set flag
            $file = locate_template('templates/page-header-breadcrumb.php');
            if ($file) {
                $hide_mobile_menu = true;
                include($file);
            } else {
                error_log('Missing templates/page-header-breadcrumb.php template needed for breadcrumb widget');
            }
        }
    }
}

// register Foo_Widget widget
function register_breadcrumb_widget()
{
    register_widget('BreadcrumbWidget');

}
add_action('widgets_init', 'register_breadcrumb_widget');

