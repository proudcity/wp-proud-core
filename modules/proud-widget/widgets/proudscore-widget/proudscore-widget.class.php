<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class ProudScoreWidget extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proudscore_widget', // Base ID
      __( 'ProudScore Widget', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Allow visitors to vote up a piece of content', 'wp-proud-core' ), ) // Args
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
      <a class="btn btn-default btn-sm proudscore-widget <?php if ( !empty( $instance['class'] ) ) { print $instance['class']; } ?>" 
        href="#" title="This makes me proud" 
        <?php if($instance['title']): ?>data-title="<?php print $instance['title'] ?>"<?php endif; ?> >
        <i aria-hidden="true" class="fa fa-fw fa-heart"></i> Helpful
      </a>
    <?php
  }
}

// register Foo_Widget widget
function register_proudscore_widget() {
  register_widget( 'ProudScoreWidget' );
}
add_action( 'widgets_init', 'register_proudscore_widget' );