<?php

/**
 * Deregister widgets provided by the wordpress-faq-manager plugin.
 *
 * We keep the plugin untouched so standard installs retain full widget support,
 * but the ProudCity platform does not expose these widgets to editors.
 *
 * @since  2026.03.27
 * @author Curtis <curtis@proudcity.com>
 *
 * @uses unregister_widget() Unregisters a previously registered widget
 */
function proud_deregister_faq_manager_widgets()
{
    unregister_widget('Search_FAQ_Widget');
    unregister_widget('Random_FAQ_Widget');
    unregister_widget('Recent_FAQ_Widget');
    unregister_widget('Topics_FAQ_Widget');
    unregister_widget('Cloud_FAQ_Widget');
}
add_action('widgets_init', 'proud_deregister_faq_manager_widgets', 20);
