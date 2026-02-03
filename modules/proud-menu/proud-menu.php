<?php

namespace Proud\Core;

/**
 * Menu helpers / getters
 */
class ProudMenuUtil
{
    public static $menus;
    public static $menu_structures = [];
    public static $active_menu_trails = [];

    public function __construct()
    {
        self::$menus = [];
        foreach (wp_get_nav_menus() as $menu) {
            self::$menus[$menu->slug] = $menu;
        }
    }

    /**
     * Returns menu items from a menu
     */
    public static function get_menu_items($menu_id)
    {
        if ($menu_id && !empty(self::$menus[$menu_id])) {
            return wp_get_nav_menu_items(self::$menus[$menu_id]->term_id);
        }
        return false;
    }

    /**
     * Helper attaches children onto menu
     * http://stackoverflow.com/a/2447631/1327637
     */
    private static function insert_deep(&$array, array $keys, &$value, &$active_trail)
    {
        $last = array_pop($keys);
        if(!empty($keys)) {
            foreach($keys as $key) {
                if(!array_key_exists($key, $array) ||
                    array_key_exists($key, $array) && !is_array($array[$key])) {
                    $array[$key] = array();
                    $array[$key]['children'] = array();
                    if(!empty($value['active'])) {
                        $active_trail[(string) $key] = '';
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
    private static function attach_link(&$menu_structure, $menu_depth_stack, $link_obj, &$active_trail)
    {
        $merge_arr = [];
        self::insert_deep($merge_arr, $menu_depth_stack, $link_obj, $active_trail);
        $menu_structure = array_merge_deep_array([$menu_structure, $merge_arr]);
    }

    /**
     * Takes WP flat menu and makes nested
     */
    public static function get_nested_menu($menu_id)
    {
        if(!empty(self::$menu_structures[$menu_id])) {
            return self::$menu_structures[$menu_id];
        }
        // Track active trail hits
        $active_trail = [];
        // menu arr
        $menu_structure = [];
        // grab menu info
        global $proud_menu_util;
        $menu_items = self::get_menu_items($menu_id);
        if (!empty($menu_items)) {
            global $post;

            // How deep we are into children
            $menu_depth_stack = [];

            foreach($menu_items as $menu_item) {
                $link_obj = [
                  'url' => $menu_item->url,
                  'title' => $menu_item->title,
                  'mid' => $menu_item->object_id
                ];
                // Active?
                if(!empty($menu_item->object_id) && $post->ID === (int) $menu_item->object_id) {
                    $link_obj['active'] = true;
                }
                // Top level
                if (!$menu_item->menu_item_parent) {
                    // Reset stack
                    $menu_depth_stack = [$menu_item->ID];
                } else {
                    // Find the right parent item
                    while(end($menu_depth_stack)) {
                        // Found parent
                        if(end($menu_depth_stack) === (int) $menu_item->menu_item_parent) {
                            break;
                        }
                        array_pop($menu_depth_stack);
                    }
                    array_push($menu_depth_stack, $menu_item->ID);
                    $link_obj['pid'] = $menu_item->menu_item_parent;
                }
                self::attach_link($menu_structure, $menu_depth_stack, $link_obj, $active_trail);
                // Add active
                if(!empty($link_obj['active'])) {
                    $active_trail[(string) $menu_item->ID] = '';
                }
            }
        }
        // Cache
        self::$menu_structures[$menu_id] = $menu_structure;
        self::$active_menu_trails[$menu_id] = $active_trail;
        return $menu_structure;
    }

    /**
     * Sets an active tail item for a menu
     */
    public static function get_active_trail($menu_id)
    {
        // Cached
        if(!empty(self::$active_menu_trails[$menu_id])) {
            return self::$active_menu_trails[$menu_id];
        }
        // Need to run nested first
        elseif(empty(self::$menu_structures[$menu_id])) {
            self::get_nested_menu($menu_id);
            return self::$active_menu_trails[$menu_id];
        }
        // Nothing active
        return [];
    }
}
global $proud_menu_util;
$proud_menu_util = new ProudMenuUtil();

/**
 * Prints out nested menu
 */
class ProudMenu
{
    public static $back_template;
    public static $link_template;
    public static $show_level;
    public static $wrapper_template;
    public static $warning_template;
    public static $menu_structure;

    public function __construct($menu_id = false, $format = 'sidebar')
    {
        // Actually build
        if($menu_id) {
            // Init templates
            if ($format == 'pills') {
                self::$link_template = plugin_dir_path(__FILE__) . 'templates/pills-item.php';
                self::$wrapper_template = plugin_dir_path(__FILE__) . 'templates/pills-wrapper.php';
                self::$show_level = false;
            } else {
                self::$link_template = plugin_dir_path(__FILE__) . 'templates/menu-item.php';
                self::$wrapper_template = plugin_dir_path(__FILE__) . 'templates/menu-wrapper.php';
                self::$back_template = plugin_dir_path(__FILE__) . 'templates/back-link.php';
                self::$show_level = true;

            }
            global $proud_menu_util;
            self::$menu_structure = $proud_menu_util::get_nested_menu($menu_id);
        }
        // Just utility
        self::$warning_template = plugin_dir_path(__FILE__) . 'templates/warning.php';
    }

    /**
     * Builds submenu markup
     */
    public static function build_recursive($current_menu, &$menus, &$active, $parent = false)
    {
        $count = count($menus) + 1;
        $menu_level = 'level-' . $count;

        // init menu
        $menus[$menu_level] = !empty(self::$show_level) ? '<div class="' . $menu_level . '">' : '';

        // Have parent?  Add backbutton
        if(!empty($parent) && file_exists(self::$back_template)) {
            ob_start();
            include(self::$back_template);
            $menus[$menu_level] .= ob_get_contents();
            ob_end_clean();
        }

        foreach($current_menu as $key => $item) {
            $children = !empty($item['children']);

            // We active?
            if(!empty($item['active'])) {
                $active = ($children) ? count($menus) + 1 : $count;
            }

            if($children) {

                // in active trail, so add click level
                if(!empty($item['active']) || !empty($item['active_trail'])) {
                    $item['active_click_level'] = count($menus) + 1;
                    // self::
                }

                self::build_recursive($item['children'], $menus, $active, [
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
        $menus[$menu_level] .= !empty(self::$show_level) ? '</div>' : '';
    }

    /**
     * Prints submenu
     * See comments https://developer.wordpress.org/reference/functions/wp_get_nav_menu_items/
     */
    public function print_menu()
    {
        if(!empty(self::$menu_structure)) {
            $active = 1;
            $menus = array();
            self::build_recursive(self::$menu_structure, $menus, $active);
            include(self::$wrapper_template);
        } else {
            include(self::$warning_template);
        }
    }
}

// register proud widget
function proud_menu_load_js()
{
    wp_enqueue_script('proud-menu', plugins_url('assets/js/', __FILE__) . 'proud-menu.js', ['proud'], false, true);
}
// Load admin scripts from libraries
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\proud_menu_load_js');

/**
 * Prints out breadcrumb
 */
class ProudBreadcrumb
{
    public static $active_trail;

    public static function build_breadcrumb()
    {
        global $pageInfo;

        if (empty($pageInfo['menu'])) {
            self::$active_trail = [];
            return;
        }

        if (empty(self::$active_trail)) {
            global $proud_menu_util;
            $menu_items   = $proud_menu_util::get_menu_items($pageInfo['menu']);
            $active_trail = $proud_menu_util::get_active_trail($pageInfo['menu']);
            if (! empty($menu_items)) {
                foreach ($menu_items as $menu_item) {
                    $menu_id = (string) $menu_item->ID;
                    if (isset($active_trail[ $menu_id ])) {
                        $active_trail[ $menu_id ] = [
                            'url'   => $menu_item->url,
                            'title' => $menu_item->title,
                            'menu_id' => $menu_id,
                            'post_id' => (string) $menu_item->object_id,
                            'post_type' => $menu_item->object,
                        ];
                        // We've filled it up
                        if (! empty(end($active_trail))) {
                            $active_trail[ $menu_id ]['active'] = true;
                            break;
                        }
                    }
                }
                if (! empty($pageInfo['parent_post_type'])) {
                    // If we're an agency, prepend the agency title
                    if ($pageInfo['parent_post_type'] === 'agency') {
                        array_unshift($active_trail, [
                            'url'   => get_permalink($pageInfo['parent_post']),
                            'title' => get_the_title($pageInfo['parent_post'])
                        ]);
                    } else {
                        $firstItem = reset($active_trail);

                        // Our parent item doesn't match top level, reset
                        if ($firstItem['post_id'] !== (string) $pageInfo['parent_post']) {
                            $pageInfo['parent_link'] = $firstItem['menu_id'];
                            $pageInfo['parent_post'] = $firstItem['post_id'];
                            $pageInfo['parent_post_type'] = $firstItem['post_type'];
                        }
                    }
                }

                self::$active_trail = $active_trail;
            }
        }
    }

    /**
     * Prints breadcrumb
     */
    public static function print_breadcrumb()
    {
        global $pageInfo;
        if (! empty($pageInfo['menu'])) {
            self::build_breadcrumb();
            // Extract for template;
            $active_trail = self::$active_trail;
            include(plugin_dir_path(__FILE__) . 'templates/breadcrumb.php');
        }
    }
}

/**
 * Resets a menu item parent if it is set to be its own parent
 *
 * @since 2023.02.20
 * @author Curtis
 *
 * @param   int         $menu_id            required            ID of the menu we're working with
 * @param   int         $menu_item_db_id    required            database id of the menu item we're saving
 * @param   array       $args               required            array of values that are getting saved in the menu
 * @uses    update_post_meta()                                  updates post meta given key, post_id, value
 */
function proud_menu_fix($menu_id, $menu_item_db_id, $args)
{

    // if ID of the current item and ID of parent match we get recursion so correct that
    if ($menu_item_db_id == $args['menu-item-parent-id']) {
        update_post_meta(absint($menu_item_db_id), '_menu_item_menu_item_parent', 0);
    }

    //echo '<pre>';
    //print_r( $menu_id );
    //echo 'menu_data ';
    //print_r( $menu_item_db_id );
    //echo 'args';
    //print_r( $args );
    //echo '</pre>';

}
add_action('wp_update_nav_menu_item', __NAMESPACE__ . '\\proud_menu_fix', 10, 3);
