<?php

/**
 * @author ProudCity
 */

use Proud\Core;

class GoogleTranslate extends Core\ProudWidget
{

    static $didRender = false;

    function __construct()
    {
        parent::__construct(
            'proud_google_translate', // Base ID
            __('Google Translate dropdown', 'wp-proud-core'), // Name
            array('description' => __('Google Translate dropdown select widget', 'wp-proud-core'),) // Args
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
        wp_enqueue_script('google-translate-widget', $path . 'google-translate.js', [], '3', true);
        // Load translate
        wp_enqueue_script('google-translate', '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit', ['google-translate-widget'], '3', true);
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

        if (GoogleTranslate::$didRender) {
            return false;
        }

        GoogleTranslate::$didRender = true;

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
        // var_dump($args);
        // var_dump($instance);



        $id = !empty($instance['id']) ? $instance['id'] : 'translate';
?>
        <!--<div class="dropdown translate">-->

        <?php if (!empty($instance['navbar'])) : ?>
            <a href="#" id="<?= $id ?>" title="Translate" data-proud-navbar class="btn navbar-btn translate-button" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i aria-hidden="true" class="fa fa-fw fa-globe"></i>
                Translate
            </a>
        <?php else : ?>
            <a href="#" id="<?= $id ?>" title="Translate" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i aria-hidden="true" class="fa fa-fw fa-globe"></i>Translate</a>
        <?php endif; ?>
        <ul class="dropdown-menu nav nav-pills" aria-labelledby="<?= $id ?>">
            <li>
                <label id="google-<?= $id ?>-label"><span class="sr-only">Translate language select</span>
                    <div id="google_<?= $id ?>_element"></div>
                </label>
            </li>
        </ul>
        <script>
            window.google_translate_init_ids = window.google_translate_init_ids || [];
            window.google_translate_init_ids.push('<?= $id ?>')
        </script>
        <!--</div>-->
<?php
    }
}

// register Foo_Widget widget
function register_google_translate_widget()
{
    register_widget('GoogleTranslate');
}
add_action('widgets_init', 'register_google_translate_widget');
