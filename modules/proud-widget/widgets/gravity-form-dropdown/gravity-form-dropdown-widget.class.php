<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class GravityFormDropdown extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_gravity_form_dropdown', // Base ID
      __( 'Gravity form dropdown', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Google Translate dropdown select widget', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    $this->settings = [
      'link_title' => [
        '#title' => 'Gravity form ID',
        '#type' => 'text',
        '#default_value' => '',
        '#description' => 'The gravity form ID to be printed in the dropdown',
        '#to_js_settings' => false
      ],
    ];
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
      <a href="#" id="sub-dropdown" data-toggle="dropdown"><i class="fa fa-fw fa-envelope"></i>Subscribe <!--<span class="caret"></span>--></a>
      <ul class="dropdown-menu nav nav-pills" aria-labelledby="sub-dropdown">
        <li style="padding: 10px 15px;">[gravityform id="1" title="false" description="false"]</li>
      </ul>
    <!--</div>-->
    <?php
  }
}

// register Foo_Widget widget
function register_gravity_form_dropdown_widget() {
  register_widget( 'GravityFormDropdown' );
}
add_action( 'widgets_init', 'register_gravity_form_dropdown_widget' );