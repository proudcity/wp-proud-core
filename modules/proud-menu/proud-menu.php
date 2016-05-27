<?php

namespace Proud\Core;

class ProudMenuUtil {

  public static $menus;

  function __construct( ) {
    self::$menus = [];
    foreach( wp_get_nav_menus() as $menu ) {
      self::$menus[$menu->term_id] = $menu;
    }
  }

  /**
   * Returns menu items from a menu
   */
  public static function get_menu_items($menu_id) {
    if( $menu_id && !empty( self::$menus[$menu_id] ) ) {
      return wp_get_nav_menu_items( self::$menus[$menu_id]->term_id );
    }
    return false;
  }
}

global $proud_menu_util;
$proud_menu_util = new ProudMenuUtil();

class ProudMenu {

  static $back_template;
  static $link_template;
  static $wrapper_template;
  static $warning_template;
  static $menu_structure;

  function __construct( $menu_id = false ) {
    // Actually build
    if( $menu_id ) {
      // Init templates
      self::$back_template = plugin_dir_path( __FILE__ ) . 'templates/back-link.php';;
      self::$link_template = plugin_dir_path( __FILE__ ) . 'templates/menu-item.php';;
      self::$wrapper_template = plugin_dir_path( __FILE__ ) . 'templates/menu-wrapper.php'; 
      // Build menu structure
      self::$menu_structure = $this->get_nested_menu( $menu_id );
    }
    // Just utility    
    self::$warning_template = plugin_dir_path( __FILE__ ) . 'templates/warning.php'; 
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
   * Takes WP flat menu and makes nested
   */
  private function get_nested_menu( $menu_id ) {
    // menu arr
    $menu_structure = [];
    // grab menu info
    global $proud_menu_util;
    $menu_items = $proud_menu_util::get_menu_items( $menu_id );

    if ( !empty( $menu_items ) ) {
      global $post;

      // How deep we are into children
      $menu_depth_stack = [];
       
      foreach( $menu_items as $menu_item ) {
        $link_obj = [
          'url' => $menu_item->url,
          'title' => $menu_item->title
        ];
        // Active?
        if(!empty( $menu_item->object_id ) && $post->ID === (int) $menu_item->object_id) {
          $link_obj['active'] = true;
        }
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
        $active = ($children) ? count( $menus ) + 1 : $count;
      }

      if( $children ) {

        // in active trail, so add click level
        if( !empty( $item['active'] ) || !empty( $item['active_trail'] ) ) {
          $item['active_click_level'] = count( $menus ) + 1;
        } 

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

    if( !empty( self::$menu_structure ) ) {
      $active = 0;
      $menus = array();
      self::build_recursive( self::$menu_structure, $menus, $active );
      include(self::$wrapper_template);
    }
    else {
      include(self::$warning_template);
    }
  }
}

// register proud widget
function proud_menu_load_js() {
  wp_enqueue_script( 'proud-menu', plugins_url( 'assets/js/',__FILE__) . 'proud-menu.js' , ['proud'], false, true );
}
    // Load admin scripts from libraries
add_action('wp_enqueue_scripts',  __NAMESPACE__ . '\\proud_menu_load_js');