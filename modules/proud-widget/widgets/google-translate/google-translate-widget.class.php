<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class GoogleTranslate extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_google_translate', // Base ID
      __( 'Google Translate dropdown', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Adds a Google Translate dropdown select', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    // $this->settings = [];
  }

  public function enqueueFrontend() {
    $path = plugins_url('js/',__FILE__);
    // Load translate
    wp_enqueue_script('google-translate', '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit', [], '3', true);
    // Function init
    wp_enqueue_script('google-translate-widget', $path . 'google-translate.js', ['google-translate'], '3', true);
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
    <!--<div class="dropdown translate">-->
      <a href="#" id="translate" data-toggle="dropdown"><i class="fa fa-globe"></i> Translate</a>
      <ul class="dropdown-menu nav nav-pills" aria-labelledby="translate">
        <li>
          <div id="google_translate_element"></div>
        </li>
      </ul>
    <!--</div>-->
    <?php
  }
}

// register Foo_Widget widget
function register_google_translate_widget() {
  register_widget( 'GoogleTranslate' );
}
add_action( 'widgets_init', 'register_google_translate_widget' );