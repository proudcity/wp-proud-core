<?php
/**
 * Shows the form for text widgets
 *
 * @package   ProudCore
 * @author    ProudCity <dev@proudcity.com>
 * @copyright Copyright (C) 2015 http://getproudcity.com. All Rights Reserved.
 * @license   GNU/GPL v2 or later http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Websites: http://getproudcity.com
 * Technical Support:  Feedback - http://getproudcity.com
 */

use Proud\Core;

if (! class_exists('TextWidget')) :

    /**
     * Proud Widget
     *
     * @package ProudCore
     * @since   1.0.0
     */
    class TextWidget extends Core\ProudWidget
    {
        /**
         * Constructor
         *
         * @return  void
         */
        public function __construct()
        {
            parent::__construct(
                'proud_text_widget', // Base ID
                __('Text Widget', 'wp-proud-core'), // Name
                array('description' => __('A collection of text cards', 'wp-proud-core'),) // Args
            );
        }

        /**
         * Define shortcode settings.
         *
         * @return void
         */
        function initialize()
        {
            $this->settings += [
            'across' => [
                '#title' => __('Columns across', 'wp-proud-core'),
                '#type' => 'radios',
                '#default_value'  => '3',
                '#options' => [
                '2' => __('Two', 'wp-proud-core'),
                '3' => __('Three', 'wp-proud-core'),
                ],
                '#description' => __('How many columns to display', 'wp-proud-core')
            ],
            'textset' => [
                '#title' => __('Text items', 'wp-proud-core'),
                '#type' => 'group',
                '#group_title_field' => 'link_title',
                '#sub_items_template' => [
                'link_title' => [
                    '#title' => 'Title',
                    '#type' => 'text',
                    '#default_value' => '',
                    '#description' => 'Title for the text card',
                    '#to_js_settings' => false
                ],
                'text' => [
                    '#title' => 'Widget text',
                    '#type' => 'text',
                    '#default_value' => '',
                    '#description' => 'Text to display. <strong>Limited to 160 characters</strong>',
                ]
                ],
            ]
            ];
        }

        /**
         * Opens list
         *
         * @param $current int required The current column
         * @param $columns int required 2/3 columns
         *
         * @return string
         */
        public static function row_open($current, $columns)
        {
            return $current%$columns === 0
                ? '<div class="row">'
                : '';
        }

        /**
         * Closes list
         *
         * @param $current    int required Current column count
         * @param $post_count int required The current post count, I think
         * @param $columns    int required The number of columns (2/3) we are using
         *
         * @return string
         */
        public static function row_close( $current, $post_count, $columns ) {
            return ( ( $post_count - 1 ) === $current ) || ( $current%$columns === ( $columns - 1 ) )
                ? '</div>'
                : '';
        }

        /**
         * Generate HTML code from shortcode content.
         *
         * @param $args     array optional The args being passed to the widget
         * @param $instance object optional The instance of the widget we're dealing with
         *
         * @return $file The file template contents
         */
        function printWidget($args, $instance)
        {
            extract($instance);
            $file = plugin_dir_path(__FILE__) . 'templates/image-cards.php';
            include $file;
        }
    }

// register Foo_Widget widget
function register_image_set_widget() {
  register_widget( 'ImageSet' );
}
add_action( 'widgets_init', __NAMESPACE__ . '\\register_image_set_widget' );

endif;
