<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class LogoWidget extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_logo', // Base ID
      __( 'Footer logo', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Footer logo and slogan', 'wp-proud-core' ), ) // Args
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
    $instance['logo'] = get_proud_logo();
    return !empty( $instance['logo'] );
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
    <h4>
      <div class="panel-pane pane-page-logo text-center">
        <div class="pane-content">
          <a href="<?php echo get_home_url(); ?>" rel="home" id="logo" title="Home">
            <img src="<?php echo $instance['logo']; ?>" alt="Home">
            <h4><?php bloginfo(); ?></h4>
          </a>  
        </div>
      </div>
    </h4>
    <?php
  }
}

// register Foo_Widget widget
function register_logo_widget() {
  register_widget( 'LogoWidget' );
}
add_action( 'widgets_init', 'register_logo_widget' );