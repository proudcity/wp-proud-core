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

        $dropdown_class = !empty($instance['hidefilters']) ? 'panel-group' : '';

        echo '<div class="proud-filter-wrapper ' . sanitize_html_class($dropdown_class) . '">';

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

        // merging widget args into $config state
        $config['widgettitle'] = !empty($instance['title']) ? $instance['title'] : '';
        $config['widgetargs'] = $args;
        $config['hidesearch'] = !empty($instance['hidesearch']) ? 1 : 0;
        $config['hidecheckboxfilters'] = !empty($instance['hidecheckboxfilters']) ? 1 : 0;
        $provider->render($config, $state);

        echo '</div><!-- /.proud-filter-wrapper -->';

        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');
        $instance['context_id'] = sanitize_text_field($new_instance['context_id'] ?? '');
        $instance['hidecheckboxfilters'] = !empty($new_instance['hidecheckboxfilters']) ? 1 : 0;
        $instance['hidesearch'] = !empty($new_instance['hidesearch']) ? 1 : 0;

        return $instance;
    }

    public function form($instance)
    {
        $title = $instance['title'] ?? '';
        $context_id = $instance['context_id'] ?? '';
        $hideCheckboxFilters = $instance['hidecheckboxfilters'] ?? 0;
        $hideSearch = $instance['hidesearch'] ?? 0;

?>
        <div class="form-group">
            <label for="<?php echo esc_attr($this->get_field_name('title')); ?>"> <?php esc_html_e('Title', 'wp-proud-core'); ?></label>
            <input class="widefat"
                name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                value="<?php echo esc_attr($title); ?>" />
        </div>

        <div class="form-group">
            <fieldset class="checkboxes">
                <legend class="option-box-label">Hide Checkbox Filters</legend>
                <label for="<?php echo esc_attr($this->get_field_name('hidecheckboxfilters')); ?>">
                    <input
                        name="<?php echo esc_attr($this->get_field_name('hidecheckboxfilters')); ?>"
                        value="1"
                        <?php checked($hideCheckboxFilters, true); ?> type="checkbox" />
                    <p class="description help-box">By checking this box the checkboxes wiill NOT show up for users.</p>
                </label>
            </fieldset>
        </div>

        <div class="form-group">
            <fieldset class="checkboxes">
                <legend class="option-box-label">Hide Search</legend>
                <label for="<?php echo esc_attr($this->get_field_name('hidesearch')); ?>">
                    <input
                        name="<?php echo esc_attr($this->get_field_name('hidesearch')); ?>"
                        value="1"
                        <?php checked($hideSearch, true); ?> type="checkbox" />
                    <p class="description help-box">By checking this box the search will NOT show for users.</p>
                </label>
            </fieldset>
        </div>

<?php
    }
}
add_action('widgets_init', function () {
    register_widget(Proud_Filter_Widget::class);
});
