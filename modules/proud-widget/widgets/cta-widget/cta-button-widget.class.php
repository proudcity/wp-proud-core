<?php

/**
 * @author ProudCity
 */

use Proud\Core;

class CTA extends Core\ProudWidget
{

    function __construct()
    {
        parent::__construct(
            'proud_cta', // Base ID
            __('Call to Action', 'wp-proud-core'), // Name
            array('description' => __('Simple button with a link', 'wp-proud-core'),) // Args
        );
    }

    function initialize()
    {
        $this->settings = [
            'link_title' => [
                '#title' => 'Link title',
                '#type' => 'text',
                '#default_value' => '',
                '#description' => 'Text for the link',
                '#to_js_settings' => false
            ],
            'link_url' => [
                '#title' => 'Link url',
                '#type' => 'text',
                '#default_value' => '',
                '#description' => 'Url for the link',
                '#to_js_settings' => false
            ],
            'classname' => [
                '#type' => 'select',
                '#title' => 'Style',
                '#options' => [
                    'action' => 'Action: Uses the color defined in the Customizer for Action Button as the background color with white text.',
                    '' => 'Standard: Dark text on light background',
                    'card-inverse' => 'Inverse: Light text on dark background',
                ],
                '#default_value' => ''
            ],
            'external' => [
                '#type' => 'checkbox',
                '#title' => 'Open in new tab',
                '#return_value' => '1',
                '#default_value' => false
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

        if ($instance['classname'] == 'action') {
            $actionColor = get_theme_mod('color_action_button', '#e49c11'); // default fallback

?>
            <style type="text/css">
                .card.card-btn.action {
                    background-color: <?php echo esc_html($actionColor); ?>;
                    color: white;
                }

                .card.card-btn.action .h4 {
                    color: white;
                }
            </style>
        <?php
        }

        ?>
        <div class="card-wrap">
            <a href="<?php echo esc_url($instance['link_url']); ?>" class="card text-center card-btn card-block <?php echo sanitize_html_class(@$instance['classname']); ?>" <?php if ($instance['external']): ?>target="_blank" <?php endif; ?>>
                <div class="h4"><?php echo sanitize_title($instance['link_title']); ?></div>
            </a>
        </div>
<?php
    }
}

// register Foo_Widget widget
function register_cta_button_widget()
{
    register_widget('CTA');
}
add_action('widgets_init', 'register_cta_button_widget');
