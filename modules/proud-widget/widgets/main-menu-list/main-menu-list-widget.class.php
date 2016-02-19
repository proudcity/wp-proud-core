<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class MainMenuList extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_main_menu_list', // Base ID
      __( 'Main menu list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Main menu in simple list', 'wp-proud-core' ), ) // Args
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
    return has_nav_menu('primary_navigation');
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
    wp_nav_menu( [ 
      'theme_location'    => 'primary_navigation',
      'container'         => 'div',
      'container_class'   => '',
      'container_id'      => '',
      'menu_class'        => 'list-unstyled',
      'menu_id'           => 'main-menu-list',
      'echo'              => true,
      'fallback_cb'       => 'wp_page_menu',
      'before'            => '',
      'after'             => '',
      'link_before'       => '',
      'link_after'        => '',
      'items_wrap'        => '<ul id="%1$s" class="%2$s">%3$s</ul>',
      'depth'             => 1,
      'walker'            => ''
    ] );
  }
}

// register Foo_Widget widget
function register_main_menu_list_widget() {
  register_widget( 'MainMenuList' );
}
add_action( 'widgets_init', 'register_main_menu_list_widget' );