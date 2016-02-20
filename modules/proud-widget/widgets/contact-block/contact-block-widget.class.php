<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class ContactBlock extends Core\ProudWidget {

  // proud libraries
  public static $libaries;

  function __construct() {
    parent::__construct(
      'proud_contact_block', // Base ID
      __( 'Contact Block', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Simple contact info block', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {

    $this->settings += [
      'contact_html' => [
        '#type' => 'textarea',
        '#rows' => 6,
        '#title' => 'Accounts to display',
        '#description' => "Enter some contact info for display",
        '#default_value' => '',
      ]
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
    extract($instance);
    $file = plugin_dir_path( __FILE__ ) . 'templates/contact-block.php';
    // Include the template file
    include( $file );
  }
}

// register Foo_Widget widget
function register_contact_block_widget() {
  register_widget( 'ContactBlock' );

}
add_action( 'widgets_init', 'register_contact_block_widget' );