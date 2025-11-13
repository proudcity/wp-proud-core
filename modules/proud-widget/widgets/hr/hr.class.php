<?php
/**
 * @author ProudCity <curtis@proudcity.com>
 */

use Proud\Core;

class HRWidget extends Core\ProudWidget
{
    public function __construct()
    {
        parent::__construct(
            'proud_hr', // Base ID
            __('HR', 'wp-proud-core'), // Name
            array( 'description' => __('Prints the hr HTML tag', 'wp-proud-core'), ) // Args
        );
    }

    public function initialize()
    {
        // need a hidden field so nothing shows up at all
        $this->settings = [
            'page_header' => [
                '#type' => 'hidden',
                '#title' => 'Nothing',
                '#value' => 'nothing',
            ]
        ];
    }

    /**
     * Front-end display of widget.
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     *
     * @see WP_Widget::widget()
     *
     * @return the HR tag
     */
    public function printWidget($args, $instance)
    {
        extract($instance);
        $file = plugin_dir_path(__FILE__) . 'templates/hr.php';
        // Include the template file
        include($file);
    }
}

// register Foo_Widget widget
function register_hr_widget()
{
    register_widget('HRWidget');

}
add_action('widgets_init', 'register_hr_widget');

