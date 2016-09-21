<?php

/**
 * @author ProudCity
 */

use Proud\Core;

class PoweredByWidget extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'powered_by_widget', // Base ID
      __( 'Powered by text', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Simple ProudCity message', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    // $this->settings = [];
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
    // @TODO allow city to hide if they pay, ect
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
    $text = __('ProudCity - a new way to launch your city site.', 'proud-core');
    ?>
      <div class="powered-by">
        <p>
          Powered by <a href="https://proudcity.com" title="<?php echo $text ?>" alt="<?php echo $text ?>"><?php echo Core\print_proud_logo( 'logo-white') ?></a>
        </p>
      </div>
    <?php
  }
}

// register Foo_Widget widget
function register_powered_by_widget() {
  register_widget( 'PoweredByWidget' );
}
add_action( 'widgets_init', 'register_powered_by_widget' );