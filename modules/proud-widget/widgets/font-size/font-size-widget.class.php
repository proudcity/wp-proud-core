<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class FontSize extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_font_size', // Base ID
      __( 'Font size dropdown', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Allow users to increase/decrease font size', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    // $this->settings = [];
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
    <!--<div class="dropdown font-size">-->
      <a href="#" id="font-size" data-toggle="dropdown"><i class="fa fa-fw fa-font"></i>Size <!--<span class="caret"></span>--></a>
      <ul class="dropdown-menu nav nav-pills" aria-labelledby="font-size">
        <li><a href="#" class="increaseFont"><i class="fa fa-font"></i><sup>+</sup></a></li>
        <li class="active"><a href="#" class="resetFont">normal</a></li>
        <li><a href="#" class="decreaseFont">a<sup>-</sup></a></li>
      </ul>
    <!--</div>-->
    <?php
  }
}

// register Foo_Widget widget
function register_font_size_widget() {
  register_widget( 'FontSize' );
}
add_action( 'widgets_init', 'register_font_size_widget' );