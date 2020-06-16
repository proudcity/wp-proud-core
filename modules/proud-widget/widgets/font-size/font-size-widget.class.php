<?php

/**
 * @author ProudCity
 */

use Proud\Core;

class FontSize extends Core\ProudWidget
{

    static $didRender;

    function __construct()
    {
        parent::__construct(
            'proud_font_size', // Base ID
            __('Font size dropdown', 'wp-proud-core'), // Name
            array('description' => __('Allow users to increase/decrease font size', 'wp-proud-core'),) // Args
        );
    }

    function initialize()
    {
        $this->settings = [
            'id' => [
                '#type' => 'hidden',
                '#default_value' => '',
                '#to_js_settings' => false
            ],
            'navbar' => [
                '#type' => 'hidden',
                '#default_value' => '',
                '#to_js_settings' => false
            ],
        ];
    }

    public function enqueueFrontend()
    {
        $path = plugins_url('js/', __FILE__);
        // Function init
        wp_enqueue_script('font-size-widget', $path . 'font-size.js', ['jquery'], '3', true);
    }

    /**
     * Determines if content empty, show widget, title ect?  
     *
     * @see self::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function hasContent($args, &$instance)
    {
        if (FontSize::$didRender) {
            return false;
        }

        FontSize::$didRender = true;

        return true;
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

        $id = !empty($instance['id']) ? $instance['id'] : 'font-size';
?>
        <!--<div class="dropdown font-size">-->
        <?php if (!empty($instance['navbar'])) : ?>
            <a href="#" id="<?= $id ?>" title="Translate" data-proud-navbar class="btn navbar-btn translate-button" aria-hidden="true" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i aria-hidden="true" class="fa fa-fw fa-font"></i>
                Size
            </a>
        <?php else : ?>
            <a href="#" id="<?= $id ?>e" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-fw fa-font"></i>Size</a>
        <?php endif; ?>
        <ul class="dropdown-menu nav nav-pills" aria-labelledby="<?= $id ?>">
            <li><a href="#" title="Increase Font Size" class="increaseFont"><i aria-hidden="true" class="fa fa-font"></i><sup>+</sup></a></li>
            <li><a href="#" title="Reset Font Size" class="resetFont">Reset</a></li>
            <li><a href="#" title="Decrease Font Size" class="decreaseFont">a<sup>-</sup></a></li>
        </ul>
        <!--</div>-->
<?php
    }
}

// register Foo_Widget widget
function register_font_size_widget()
{
    register_widget('FontSize');
}
add_action('widgets_init', 'register_font_size_widget');
