<?php

namespace Proud\Core;

class ProudMenu {

  static $back_template;
  static $link_template;
  static $wrapper_template;
  static $menu_structure;

  function __construct( $menu_name ) {
    // Init templates
    self::$back_template = plugin_dir_path( __FILE__ ) . 'templates/back-link.php';;
    self::$link_template = plugin_dir_path( __FILE__ ) . 'templates/menu-item.php';;
    self::$wrapper_template = plugin_dir_path( __FILE__ ) . 'templates/menu-wrapper.php'; 
    // Build menu structure
    self::$menu_structure = $this->get_structure( $menu_name );
  }

  /** 
   * Helper attaches children onto menu
   * http://stackoverflow.com/a/2447631/1327637
   */
  function insert_deep(&$array, array $keys, $value) {
    $last = array_pop($keys);       
    if( !empty($keys) ) {
      foreach( $keys as $key ) {
        if(!array_key_exists( $key, $array ) || 
            array_key_exists( $key, $array ) && !is_array( $array[$key] )) {
              $array[$key] = array();
              $array[$key]['children'] = array();
              if(!empty($value['active'])) {
                $array[$key]['active_trail'] = true;
              }

        }
        $array = &$array[$key]['children'];
      }
    }
    $array[$last] = $value;
  }

  /** 
   * Helper attaches children onto menu
   */
  private function attach_link( &$menu_structure, $menu_depth_stack, $link_obj) {
    $merge_arr = [];
    $this->insert_deep($merge_arr, $menu_depth_stack, $link_obj);
    $menu_structure = array_merge_deep_array([$menu_structure, $merge_arr]);
  }

  /** 
   * Prints submenu
   * See comments https://developer.wordpress.org/reference/functions/wp_get_nav_menu_items/
   */
  private function get_structure( $menu_name ) {
    // menu arr
    $menu_structure = [];
    if ( ($menu_name) && ( $locations = get_nav_menu_locations() ) && isset( $locations[$menu_name] ) ) {
      global $post;

      // grab menu info
      $menu = get_term( $locations[$menu_name], 'nav_menu' );
      $menu_items = wp_get_nav_menu_items( $menu->term_id );
      // How deep we are into children
      $menu_depth_stack = [];
       
      foreach( $menu_items as $menu_item ) {
        $link_obj = [
          'url' => $menu_item->url,
          'title' => $menu_item->title
        ];
        // Top level
        if ( !$menu_item->menu_item_parent ) {
          // Reset stack
          $menu_depth_stack = [$menu_item->ID];
        }
        else {
          // Find the right parent item
          while(end( $menu_depth_stack )) {
            // Found parent
            if( end( $menu_depth_stack ) === (int) $menu_item->menu_item_parent ) {
              break;
            }
            array_pop( $menu_depth_stack );
          }
          array_push( $menu_depth_stack, $menu_item->ID );
          $link_obj['pid'] = $menu_item->menu_item_parent;
          // Active item
          if(!empty( $menu_item->object_id ) && $post->ID === (int) $menu_item->object_id) {
            $link_obj['active'] = true;
          }
        }
        $this->attach_link( $menu_structure, $menu_depth_stack, $link_obj );
      }
    }
    return $menu_structure;
  }

  /** 
   * Builds submenu markup
   */
  static function build_recursive( $current_menu, &$menus, &$active, $parent = FALSE) {
    $count = count( $menus ) + 1;
    $menu_level = 'level-' . $count;

    // init menu
    $menus[$menu_level] = '<div class="' . $menu_level . '">';

    // Have parent?  Add backbutton
    if( !empty($parent) ) {
      ob_start();
      include(self::$back_template);
      $menus[$menu_level] .= ob_get_contents();
      ob_end_clean();
    }

    foreach( $current_menu as $key => $item ) {
      $children = !empty( $item['children'] );

      // We active? 
      if( !empty( $item['active'] ) ) {
        $active = ($children) ? $count + 1 : $count;
      }

      if( $children ) {
        self::build_recursive( $item['children'], $menus, $active, [
          'count' => $count, 
          'title' => $item['title'],
          'url' => $item['url']
        ]);
      }
      ob_start();
      include(self::$link_template);
      $menus[$menu_level] .= ob_get_contents();
      ob_end_clean();
    }

    // close menu
    $menus[$menu_level] .= '</div>';
  }

  /** 
   * Prints submenu
   * See comments https://developer.wordpress.org/reference/functions/wp_get_nav_menu_items/
   */
  static function print_menu( ) {
    $active = 0;
    $menus = array();
    self::build_recursive( self::$menu_structure, $menus, $active );
    include(self::$wrapper_template);
  }
}

// register Foo_Widget widget
function proud_menu_load_js() {
  wp_enqueue_script( 'proud-menu', plugins_url( 'assets/js/',__FILE__) . 'proud-menu.js' , ['proud'], false, true );
}
    // Load admin scripts from libraries
add_action('admin_enqueue_scripts',  __NAMESPACE__ . '\\proud_menu_load_js');