<?php

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class Proud_Filter_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'proud_filter_widget',
            __('New Content Filters', 'wp-proud-core'),
            ['description' => __('Displays filters for a registered content list.', 'wp-proud-core')]
        );
    }

    public function widget($args, $instance)
    {
        echo $args['before_widget'];

        $title = !empty($instance['title']) ? $instance['title'] : '';
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        if (!function_exists('proud_filters')) {
            // Filter framework not available
            echo $args['after_widget'];
            return;
        }

        $context_id = proud_filters()->registry()->get_default_context_id();

        if (!$context_id) {
            echo '<!-- proud-filter: no default context -->';
            echo $args['after_widget'];
            return;
        }

        $context = proud_filters()->registry()->get($context_id);
        if (!$context || empty($context['provider']) || empty($context['config'])) {
            echo '<!-- proud-filter: default context missing -->';
            echo $args['after_widget'];
            return;
        }

        $provider = $context['provider'];
        $config   = $context['config'];
        $state    = $provider->get_state($_GET);

        $provider->render($config, $state);


        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');
        $instance['context_id'] = sanitize_text_field($new_instance['context_id'] ?? '');
        return $instance;
    }

    public function form($instance)
    {
        $title = $instance['title'] ?? '';
        $context_id = $instance['context_id'] ?? '';
?>
        <p>
            <label>
                <?php esc_html_e('Title', 'wp-proud-core'); ?><br />
                <input class="widefat"
                    name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                    value="<?php echo esc_attr($title); ?>" />
            </label>
        </p>

        <!--
        <p>
            <label>
                <?php esc_html_e('Target context id (optional)', 'wp-proud-core'); ?><br />
                <input class="widefat"
                    name="<?php echo esc_attr($this->get_field_name('context_id')); ?>"
                    value="<?php echo esc_attr($context_id); ?>"
                    placeholder="<?php echo esc_attr__('Leave blank for auto', 'wp-proud-core'); ?>" />
            </label>
        </p>
        <p class="description">
            <?php esc_html_e('If blank, the widget will use the first available filter context on the page.', 'wp-proud-core'); ?>
        </p>
        -->
<?php
    }
}
add_action('widgets_init', function () {
    register_widget(Proud_Filter_Widget::class);
});
