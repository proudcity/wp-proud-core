<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class FooterInfo extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_footer_info', // Base ID
      __( 'Footer info', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Displays proudly serving, with logo', 'wp-proud-core' ), ) // Args
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
    <h4>
      <div class="panel-pane pane-page-logo">
        <div class="pane-content">
          <a href="<?php echo get_home_url(); ?>" rel="home" id="logo" title="Home">
            <img src="<?php echo plugins_url( '/assets/images/IconBlack.png', __FILE__ ) ?>" alt="Home">
          </a>  
        </div>
      </div>
      <small>Proudly serving</small>
      <div><?php echo get_bloginfo( 'name' ); ?></div>
    </h4>
    <?php
  }
}

// register Foo_Widget widget
function register_footer_info_widget() {
  register_widget( 'FooterInfo' );
}
add_action( 'widgets_init', 'register_footer_info_widget' );