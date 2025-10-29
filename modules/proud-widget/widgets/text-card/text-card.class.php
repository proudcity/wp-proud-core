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

if (! class_exists('TextCardWidget')) :

    /**
     * Proud Widget
     *
     * @package ProudCore
     * @since   1.0.0
     */
    class TextCardWidget extends Core\ProudWidget
    {
        /**
         * Constructor
         *
         * @return  void
         */
        public function __construct()
        {

            // overwriting the default title set in Core\ProudWidget
            $this->settings = [
                'title' => [
                    '#title' => 'testing',
                    '#type' => 'text',
                    '#default_value' => '',
                    '#description' => 'This title appears as a heading (H2) above the cards. The card titles are H3. Leaving it blank can create an accessibility issue.',
                    '#to_js_settings' => false
                ]
            ];

            parent::__construct(
                'proud_text_card_widget', // Base ID
                __('Text Card Widget', 'wp-proud-core'), // Name
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
                '#default_value'  => '2',
                '#required' => true,
                '#options' => [
                    '2' => __('Two', 'wp-proud-core'),
                    '3' => __('Three', 'wp-proud-core'),
                ],
                '#description' => __('How many columns to display', 'wp-proud-core')
            ],
            'textset' => [
                '#title' => __('Text Card', 'wp-proud-core'),
                '#type' => 'group',
                '#group_title_field' => 'text_title',
                '#sub_items_template' => [
                'text_title' => [
                    '#title' => 'Title',
                    '#type' => 'text',
                    '#default_value' => '',
                    '#description' => 'Title for the text card',
                    '#to_js_settings' => false
                ],
                'link_url' => [
                    '#title' => 'Link url',
                    '#type' => 'text',
                    '#default_value' => '',
                    '#description' => 'Link for the title',
                    '#to_js_settings' => false
                ],
                'text' => [
                    '#title' => 'Widget text',
                    '#type' => 'text',
                    '#default_value' => '',
                    '#description' => 'Text to display. <strong>Limited to 155 characters</strong>',
                    '#args' => array(
                        'class' => 'limit-maxlength',
                        'maxlength' => '155'
                    ),
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
        public static function row_close($current, $post_count, $columns)
        {
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
            $file = plugin_dir_path(__FILE__) . 'templates/text-cards.php';
            include $file;
        }
    }

    // register Foo_Widget widget
    function register_text_card_widget()
    {
        register_widget('TextCardWidget');
    }
    add_action('widgets_init', __NAMESPACE__ . '\\register_text_card_widget');

endif;
