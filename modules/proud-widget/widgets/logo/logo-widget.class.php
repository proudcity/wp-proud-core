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
    $instance['logo'] = Core\get_proud_logo();
    return !empty( $instance['logo'] );
  }

  /**
   *  Helper prints logo
   */
  function get_proud_logo_wrapper_class() {
    $hide = get_theme_mod( 'proud_logo_includes_title' );
    return $hide ? 'hide-site-name' : '';
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
    $hide = get_theme_mod( 'proud_logo_includes_title' );
    $hide_class = $hide ? 'hide-site-name' : '';
    ?>
      <div class="panel-pane pane-page-logo text-center <?php echo $hide_class; ?>">
        <div class="pane-content">
          <a href="<?php echo get_home_url(); ?>" rel="home" id="logo" title="Home">
            <?php if( $instance['logo'] ): ?>
              <img src="<?php echo $instance['logo']; ?>" alt="Home">
            <?php else: ?>
              <?php echo Core\print_proud_logo( 'icon-white', [
                'class' => 'logo',
                'title' => 'Home',
                'alt' => 'Alt'
              ] ); ?> 
            <?php endif; ?>
            <h4 class="site-name"><?php bloginfo(); ?></h4>
          </a>  
        </div>
      </div>
    <?php
  }
}

// register Foo_Widget widget
function register_logo_widget() {
  register_widget( 'LogoWidget' );
}
add_action( 'widgets_init', 'register_logo_widget' );