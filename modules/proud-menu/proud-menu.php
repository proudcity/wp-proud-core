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
     * Takes WP flat menu and makes nested
     */
    public static function get_nested_menu($menu_id)
    {
        if (!empty(self::$menu_structures[$menu_id])) {
            return self::$menu_structures[$menu_id];
        }
        $active_trail   = [];
        $menu_structure = [];
        $menu_items = self::get_menu_items($menu_id);
        if (!empty($menu_items)) {
            global $post;

            // Build item data and a parent→children map (preserving menu_order within each level).
            $items_data  = [];
            $children_of = []; // parent nav_menu_item ID => [child IDs in menu_order]

            foreach ($menu_items as $menu_item) {
                $link_obj = [
                    'url'     => $menu_item->url,
                    'title'   => $menu_item->title,
                    'mid'     => $menu_item->object_id,
                    'type'    => $menu_item->type,
                    'object'  => $menu_item->object,
                    'excerpt' => \Proud\Core\pc_get_yoast_meta_or_excerpt(absint($menu_item->object_id)),
                ];
                if (!empty($menu_item->object_id) && $post->ID === (int) $menu_item->object_id) {
                    $link_obj['active'] = true;
                }
                $parent = (int) $menu_item->menu_item_parent;
                if ($parent) {
                    $link_obj['pid'] = $parent;
                }
                $items_data[$menu_item->ID]  = $link_obj;
                $children_of[$parent][]      = $menu_item->ID;
            }

            // Recursively build the tree using explicit parent IDs so that nesting is
            // correct regardless of menu_order values (the previous stack-based approach
            // broke whenever an item's menu_order placed it after a sibling branch).
            //
            // $found_active prevents a post that appears twice in the menu from creating
            // two separate active branches — only the first occurrence wins.
            $found_active = false;
            $build = function (int $parent_id) use (&$build, &$items_data, &$children_of, &$active_trail, &$found_active): array {
                if (empty($children_of[$parent_id])) {
                    return [];
                }
                $result = [];
                foreach ($children_of[$parent_id] as $item_id) {
                    $item     = $items_data[$item_id];
                    $children = $build($item_id);

                    if (!empty($children)) {
                        $item['children'] = $children;
                    }

                    // Only the first active occurrence is marked; duplicates are ignored.
                    $is_active             = !$found_active && !empty($item['active']);
                    $has_active_descendant = false;
                    foreach ($children as $child) {
                        if (!empty($child['active']) || !empty($child['active_trail'])) {
                            $has_active_descendant = true;
                            break;
                        }
                    }

                    if ($is_active) {
                        $active_trail[(string) $item_id] = '';
                        $found_active = true;
                    }
                    if ($has_active_descendant) {
                        $active_trail[(string) $item_id] = '';
                        $item['active_trail']             = true;
                    }

                    $result[$item_id] = $item;
                }
                return $result;
            };

            $menu_structure = $build(0);
            // $build() inserts active_trail entries leaf→root as the call stack unwinds.
            // Reverse so the trail is in root→leaf display order.
            $active_trail = array_reverse($active_trail, true);
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
        if (!empty(self::$active_menu_trails[$menu_id])) {
            return self::$active_menu_trails[$menu_id];
        }
        // Need to run nested first
        elseif (empty(self::$menu_structures[$menu_id])) {
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
    public $back_template = null;
    public $link_template = null;
    public $show_level = true;
    public $wrapper_template = null;
    public $warning_template = null;
    public $menu_structure = [];
    public $across = 2; // how many columns
    public $format = 'sidebar';

    public function __construct($menu_id = false, $format = 'sidebar', $textcardcolumns = 2)
    {
        $across = (int) $textcardcolumns;
        $this->across = in_array($across, [2, 3], true) ? $across : 2;
        // setting the format so we can build_recursive different based on format
        $this->format = (string) $format;

        $this->warning_template = plugin_dir_path(__FILE__) . 'templates/warning.php';

        if (!$menu_id) {
            return;
        }

        if ($format === 'pills') {
            $this->link_template    = plugin_dir_path(__FILE__) . 'templates/pills-item.php';
            $this->wrapper_template = plugin_dir_path(__FILE__) . 'templates/pills-wrapper.php';
            $this->show_level       = false;
        } elseif ($format === 'textcard') {
            $this->link_template    = plugin_dir_path(__FILE__) . 'templates/textcard-item.php';
            $this->wrapper_template = plugin_dir_path(__FILE__) . 'templates/textcard-wrapper.php';
            $this->show_level       = false;
            $this->back_template    = null; // explicitly disabled
        } else {
            $this->link_template    = plugin_dir_path(__FILE__) . 'templates/menu-item.php';
            $this->wrapper_template = plugin_dir_path(__FILE__) . 'templates/menu-wrapper.php';
            $this->back_template    = plugin_dir_path(__FILE__) . 'templates/back-link.php';
            $this->show_level       = true;
        }

        global $proud_menu_util;
        $this->menu_structure = $proud_menu_util::get_nested_menu($menu_id) ?: [];
    }

    /**
     * Recursively builds the menu
     */
    public function build_recursive($current_menu, &$menus, &$active, $parent = false)
    {


        $count = count($menus) + 1;
        $menu_level = 'level-' . $count;

        $menus[$menu_level] = !empty($this->show_level) ? '<div class="' . $menu_level . '">' : '';

        if (
            !empty($parent)
            && is_string($this->back_template)
            && $this->back_template !== ''
            && file_exists($this->back_template)
        ) {
            ob_start();
            include $this->back_template;
            $menus[$menu_level] .= ob_get_clean();
        }

        foreach ($current_menu as $item) {

            $children = !empty($item['children']);

            // For textcard: only show top-level items (no children, no recursion)
            if ($this->format === 'textcard' && !empty($item['pid'])) {
                continue; // child item, skip entirely
            }

            if (!empty($item['active'])) {
                $active = ($children) ? count($menus) + 1 : $count;
            }

            if ($children) {
                if (!empty($item['active']) || !empty($item['active_trail'])) {
                    $item['active_click_level'] = count($menus) + 1;
                }

                $this->build_recursive($item['children'], $menus, $active, [
                    'count' => $count,
                    'title' => $item['title'],
                    'url'   => $item['url'],
                ]);
            }

            ob_start();
            include $this->link_template;
            $menus[$menu_level] .= ob_get_clean();
        }

        $menus[$menu_level] .= !empty($this->show_level) ? '</div>' : '';
    }

    /**
     * Prints submenu
     * See comments https://developer.wordpress.org/reference/functions/wp_get_nav_menu_items/
     */
    public function print_menu()
    {
        if (!empty($this->menu_structure)) {
            $active = 1;
            $menus = [];

            $this->build_recursive($this->menu_structure, $menus, $active);

            // variables for wrapper template scope
            $across = $this->across;

            include $this->wrapper_template;
        } else {
            include $this->warning_template;
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
                // Fill each trail slot with full item data. Iterate all items rather
                // than breaking early — menu_order is not guaranteed to be root→leaf,
                // so an early break could leave ancestor slots as empty strings.
                foreach ($menu_items as $menu_item) {
                    $menu_id = (string) $menu_item->ID;
                    if (isset($active_trail[$menu_id])) {
                        $active_trail[$menu_id] = [
                            'url'      => $menu_item->url,
                            'title'    => $menu_item->title,
                            'menu_id'  => $menu_id,
                            'post_id'  => (string) $menu_item->object_id,
                            'post_type' => $menu_item->object,
                        ];
                    }
                }
                // Drop slots that were never matched (post removed from menu, etc.).
                $active_trail = array_filter($active_trail, 'is_array');
                // Mark the deepest item (last in root→leaf trail) as the active page.
                $last_key = array_key_last($active_trail);
                if ($last_key !== null) {
                    $active_trail[$last_key]['active'] = true;
                }
                if (! empty($pageInfo['parent_post_type'])) {
                    // If we're an agency or proud-topic, prepend the parent title
                    if ($pageInfo['parent_post_type'] === 'agency' || $pageInfo['parent_post_type'] === 'proud-topic') {
                        array_unshift($active_trail, [
                            'url'   => get_permalink($pageInfo['parent_post']),
                            'title' => get_the_title($pageInfo['parent_post'])
                        ]);
                    } else {
                        $firstItem = reset($active_trail);

                        // Our parent item doesn't match top level, reset.
                        // Guard against an empty trail (firstItem would be false).
                        if (is_array($firstItem) && $firstItem['post_id'] !== (string) $pageInfo['parent_post']) {
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
function proud_menu_fix($_menu_id, $menu_item_db_id, $args)
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
