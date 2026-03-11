<?php

/**
 * @author ProudCity
 */

use Proud\Core;

class SubTitle extends Core\ProudWidget
{

    function __construct()
    {
        parent::__construct(
            'proud_subtitle', // Base ID
            __('Subtitle', 'wp-proud-core'), // Name
            array('description' => __('Style paragraph text that\'s bolder and larger than regular paragraph text.', 'wp-proud-core'),) // Args
        );
    }

    function initialize()
    {
        $this->settings = [
            'subtitle_title' => [
                '#title' => 'Title',
                '#type' => 'textarea',
                '#default_value' => '',
                '#description' => 'Write a sentence or 2 describing the purpose or main message of the page. Max 155 characters.',
                '#to_js_settings' => false
            ],
        ];
    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     *
     * @return null
     */
    public function printWidget($args, $instance)
    {
?>
        <p class="proud-subtitle"><?php echo esc_html($instance['subtitle_title']); ?></p>
<?php
    }
}

// register Foo_Widget widget
function register_subtitle_widget()
{
    register_widget('SubTitle');
}
add_action('widgets_init', 'register_subtitle_widget');
