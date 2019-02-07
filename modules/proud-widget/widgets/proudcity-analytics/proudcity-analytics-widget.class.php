<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class ProudcityAnalytics extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proudcity_analytics', // Base ID
      __( 'ProudCity Analytics', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Display your site visit analytics for your visitors', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    // $this->settings = [];
  }

  public function enqueueFrontend() {
    global $proudcore;
    $proudcore::$libraries->addAngular(true, true, true);

    $path = plugins_url('js/',__FILE__);
    wp_enqueue_script('proudcity-analytics', $path . 'proudcity-analytics.js', ['jquery'], '3', true);
  }

  /**
   * Determines if content empty, show widget, title ect?  
   *
   * @see self::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function hasContent($args, &$instance) {
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
  public function printWidget( $args, $instance ) {
    ?>
    [pc analytics]
    <?php
  }
}

// register Foo_Widget widget
function register_proudcity_analytics_widget() {
  register_widget( 'ProudCityAnalytics' );
}
add_action( 'widgets_init', 'register_proudcity_analytics_widget' );