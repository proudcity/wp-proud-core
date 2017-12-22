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
      array( 'description' => __( 'Google Translate dropdown select widget', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    // $this->settings = [];
  }

  public function enqueueFrontend() {
    $path = plugins_url('js/',__FILE__);
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
    <!--<div class="dropdown translate">-->
      <a href="#" id="translate" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i aria-hidden="true" class="fa fa-fw fa-globe"></i>Translate</a>
      <ul class="dropdown-menu nav nav-pills" aria-labelledby="translate">
        <li>
          <label><span class="sr-only">Translate language select</span>
            <div id="google_translate_element"></div>
          </label>
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