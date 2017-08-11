<?php
/**
 * @author ProudCity
 */

use Proud\Core;

/**
 * Stub class extends the WP_Widget_Custom_HTML introduced in 4.8
 * this allows migration from using the pre-4.8 WP_Widget_Text widget
 */
class ProudEmbed extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_embed', // Base ID
      __( 'Code or iFrame embed', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Arbitrary HTML or Embed code.', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    $this->settings += [
      'text' => [
        '#type' => 'textarea',
        '#rows' => 6,
        '#title' => 'Code / Embed code',
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
    ?>
    <div class="textwidget custom-html-widget">
      <?php echo $instance['text']; ?>
    </div>
    <?php
  }
}

// register widget
function register_proudembed_widget() {
  register_widget( 'ProudEmbed' ); 
}
add_action( 'widgets_init', 'register_proudembed_widget' );