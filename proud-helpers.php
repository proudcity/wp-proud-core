<?php
/**
 * @author ProudCity
 */

namespace Proud\Core;

// Hacky copied function to produce exerpt
function wp_trim_excerpt( $text = '', $more_link = true ) {
  $raw_excerpt = $text;
  if ( '' == $text ) {
    $text = get_the_content('');

    $text = strip_shortcodes( $text );

    /** This filter is documented in wp-includes/post-template.php */
    // $text = apply_filters( 'the_content', $text );
    $text = str_replace(']]>', ']]&gt;', $text);

    /**
     * Filter the number of words in an excerpt.
     *
     * @since 2.7.0
     *
     * @param int $number The number of words. Default 55.
     */
    $excerpt_length = apply_filters( 'excerpt_length', 55 );
    /**
     * Filter the string in the "more" link displayed after a trimmed excerpt.
     *
     * @since 2.9.0
     *
     * @param string $more_string The string shown within the more link.
     */
    $excerpt_more = $more_link ? apply_filters( 'excerpt_more', ' ' . '[&hellip;]' ) : '...';
    $text = wp_trim_words( $text, $excerpt_length, $excerpt_more );
  }
  /**
   * Filter the trimmed excerpt string.
   *
   * @since 2.8.0
   *
   * @param string $text        The trimmed text.
   * @param string $raw_excerpt The text prior to trimming.
   */
  return apply_filters( 'wp_trim_excerpt', $text, $raw_excerpt );
}


/** 
 * Sanitize text input
 */
function sanitize_input_text_output($text, $shortcode = true) {
  $text = \wp_kses_post( $text );
  // Run some known stuff
  if( !empty($GLOBALS['wp_embed']) ) {
    $text = $GLOBALS['wp_embed']->autoembed( $text );
  }
  // Evaluate shortcode?
  if($shortcode) {
    $text = do_shortcode( $text );
  }
  return $text;
}

// Recusive array merging from 
// https://api.drupal.org/api/drupal/assets%21bootstrap.inc/function/drupal_array_merge_deep_array/7
function array_merge_deep_array($arrays) {
  $result = array();

  foreach ($arrays as $array) {
    foreach ($array as $key => $value) {
      // Recurse when both values are arrays.
      if (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
        $result[$key] = array_merge_deep_array(array($result[$key], $value));
      }
      // Otherwise, use the latter value, overriding any previous value.
      else {
        $result[$key] = $value;
      }
    }
  }

  return $result;
}

/** 
 * Helper attaches children onto menu
 * http://stackoverflow.com/a/2447631/1327637
 */
function insert_into_deep(&$array, array $keys, $value) {
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
function menu_structure_attach_link( &$menu_structure, $menu_depth_stack, $link_obj) {
  $merge_arr = [];
  insert_into_deep($merge_arr, $menu_depth_stack, $link_obj);
  $menu_structure = array_merge_deep_array([$menu_structure, $merge_arr]);
}

/** 
 * Prints submenu
 * See comments https://developer.wordpress.org/reference/functions/wp_get_nav_menu_items/
 */
function get_custom_menu_structure( $menu_name ) {
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
      menu_structure_attach_link( $menu_structure, $menu_depth_stack, $link_obj );
    }
  }
  return $menu_structure;
}

/** 
 * Builds submenu markup
 */
function build_custom_menu_recursive( $current_menu, &$menus, &$active, $parent = FALSE) {
  d($current_menu);
  $count = count( $menus ) + 1;
  $menu_level = 'level-' . $count;

  // init menu
  $menus[$menu_level] = '<div class="' . $menu_level . '">';

  // Have parent?  Add backbutton
  if( !empty($parent) ) {
    $menus[$menu_level] .= '<a data-back="' . $parent['count'] . '" href="' . $parent['url'] . '" title="' . $parent['title'] .  '">'
                        .  '<i class="fa fa-chevron-left"></i> ' . $parent['title'] . '</a>';
  }

  foreach( $current_menu as $key => $item ) {
    $children = !empty( $item['children'] );

    // We active? 
    if( !empty( $item['active'] ) ) {
      $active = ($children) ? $count + 1 : $count;
    }

    if( $children ) {
      build_custom_menu_recursive( $item['children'], $menus, $active, [
        'count' => $count, 
        'title' => $item['title'],
        'url' => $item['url']
      ]);
    }

    $menus[$menu_level] .= '<a href="' . $item['url'] . '" title="' . $item['title'] . '"'
                        .  (!empty( $item['active'] ) ? ' class="active"' : '')
                        .  (!empty( $item['active_trail'] ) ? ' data-active-click="' . $active . '"' : '')
                        .  '>' . $item['title'] . '</a>';

  }

  // close menu
  $menus[$menu_level] .= '</div>';
}

/** 
 * Prints submenu
 * See comments https://developer.wordpress.org/reference/functions/wp_get_nav_menu_items/
 */
function print_custom_menu( $menu_name ) {
  $menu_structure = get_custom_menu_structure( $menu_name );
  $active = 0;
  $menus = array();
  build_custom_menu_recursive($menu_structure, $menus, $active);
  d($menus);
  return $menus;
}


/**
 *  Gets  logo
 */
function get_proud_logo($use_theme = true, $logo_file = 'icon-white-1x.png') {
  if($use_theme) {
    $logo = get_theme_mod( 'proud_logo' );
  }
  else if($logo_file) {
    $logo = plugins_url( '/assets/images/' . $logo_file,  __FILE__  );
  }
  return $logo ? $logo : false;
}

/**
 * Prints retina version of proud logo
 * $logo_version = 'icon-white' || 'logo-white' (with text)
 */
function print_proud_logo($logo_version = 'icon-white', $meta = []) {
  $image_meta = [
    'srcset' => [
      '1x' => get_proud_logo( false, $logo_version . '-1x.png' ),
      '2x' => get_proud_logo( false, $logo_version . '-2x.png' )
    ],
    'src' => get_proud_logo( false, $logo_version . '-1x.png' ),
    'meta' => [
      'image_meta' => array_merge( [
        'title' => __('ProudCity - a new way to launch your city site.', 'proud-core'),
        'alt' => __('ProudCity - a new way to launch your city site.', 'proud-core'),
        'class' => 'proud-logo'
      ], $meta )
    ]
  ];
  print_retina_image( $image_meta, false, true );
}


/** 
 * Build responsive image meta
 */
function build_responsive_image_meta( $media_id, $size_max = 'full', $size_small = 'medium' ) {
  // Get meta
  $media_meta = wp_get_attachment_metadata($media_id);
  return [
    'srcset' => wp_get_attachment_image_srcset($media_id, $size_max, $media_meta),
    'size' => wp_get_attachment_image_sizes($media_id, $size_max),
    'src' => wp_get_attachment_image_src($media_id, $size_small),
    'meta' => $media_meta
  ];
}


/** 
 * Print responsive image html
 */
function print_responsive_image( $resp_img, $classes = [], $skip_media = false ) {
  $classes[] = 'media';
  $image_meta = $resp_img['meta']['image_meta'];
  ?> 
  <?php if( !empty( $resp_img['src'] ) ): ?> 
    <?php if( !$skip_media && !empty( $classes ) ): ?> 
    <div class="<?php echo implode(' ', $classes) ?>">
    <?php endif; ?>
      <img src="<?php echo esc_url( $resp_img['src'][0] ); ?>"
         srcset="<?php echo esc_attr( $resp_img['srcset'] ); ?>"
         sizes="<?php echo esc_attr( $resp_img['size'] ); ?>"
         <?php if ( !empty( $image_meta['class'] ) ): ?> class="<?php echo $image_meta['class'] ?>"<?php endif; ?>
         <?php if ( !empty( $image_meta['title'] ) ): ?> title="<?php echo $image_meta['title'] ?>"<?php endif; ?>
         <?php if ( !empty( $image_meta['alt'] ) ): ?> alt="<?php echo $image_meta['alt'] ?>"<?php endif; ?>>
    <?php if ( !$skip_media && !empty( $image_meta['caption'] ) ): ?>
      <div class="media-byline text-left"><span><?php echo $image_meta['caption'] ?></span></div>
    <?php endif; ?>
    <?php if( !$skip_media && !empty( $classes ) ): ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
  <?php
}

/** 
 * Build retina image meta
 */
function build_retina_image_meta( $media_id, $normal = 'medium', $retina = 'medium_large' ) {
  // Get meta
  $media_meta = wp_get_attachment_metadata($media_id);
  $src = wp_get_attachment_image_url($media_id, $normal);
  return [
    'srcset' => [
      '1x' => $src,
      '2x' => wp_get_attachment_image_url($media_id, $retina)
    ],
    'src' => $src,
    'meta' => $media_meta
  ];
}

/** 
 * Print retina image
 */
function print_retina_image( $resp_img, $classes = [], $skip_media = false ) {
  $classes[] = 'media';
  $image_meta = $resp_img['meta']['image_meta'];
  $srcset = '';
  foreach ( $resp_img['srcset'] as $key => $image ) {
    $resp_img['srcset'][$key] = esc_url($image) . ' ' . $key;
  }
  ?> 
  <?php if( !empty( $resp_img['src'] ) ): ?> 
    <?php if( !$skip_media && !empty( $classes ) ): ?> 
    <div class="<?php echo implode(' ', $classes) ?>">
    <?php endif; ?>
      <img src="<?php echo esc_url( $resp_img['src'] ); ?>"
         srcset="<?php echo implode( ', ', $resp_img['srcset'] ); ?>"
         <?php if ( !empty( $image_meta['class'] ) ): ?> class="<?php echo $image_meta['class'] ?>"<?php endif; ?>
         <?php if ( !empty( $image_meta['title'] ) ): ?> title="<?php echo $image_meta['title'] ?>"<?php endif; ?>
         <?php if ( !empty( $image_meta['alt'] ) ): ?> alt="<?php echo $image_meta['alt'] ?>"<?php endif; ?>>
    <?php if ( !$skip_media && !empty( $image_meta['caption'] ) ): ?>
      <div class="media-byline text-left"><span><?php echo $image_meta['caption'] ?></span></div>
    <?php endif; ?>
    <?php if( !$skip_media && !empty( $classes ) ): ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
  <?php
}


/**
 * Helper function returns url to social acount
 */
function socialAccountUrl($service, $account) {
  switch ($service) {
    case 'facebook':
    case 'instagram':
    case 'twitter':
      return sprintf( 'https://%s.com/%s', $service, $account);
      break;
  }
}


/**
 * Helper function returns useful data for account
 * $string: [service]:[account] eg: 'twitter:proudcity'
 */
function extractSocialData($string) {
  $account = explode( ':', $string );
  $url = socialAccountUrl( $account[0], $account[1] );
  return [
    'service' => ucfirst( $account[0] ),
    'account' => $account[1],
    'url'     => $url
  ];
}

/**
 * Helper function gets social accounts from options
 */
function getSocialData() {
  $social = get_option('social_feeds');
  if( !empty( $social ) ) {
    $social = explode( PHP_EOL, $social );
    // Empty? Trim whitespace
    return !empty( $social ) ? array_filter( array_map('trim', $social) ) : [];
  }
  return [];
}

/**
 * Returns the timezone string for a site, even if it's set to a UTC offset
 *
 * Adapted from http://www.php.net/manual/en/function.timezone-name-from-abbr.php#89155
 *
 * @return string valid PHP timezone string
 */
function wpGetTimezoneString() {
 
    // if site timezone string exists, return it
    if ( $timezone = get_option( 'timezone_string' ) )
        return $timezone;
 
    // get UTC offset, if it isn't set then return UTC
    if ( 0 === ( $utc_offset = get_option( 'gmt_offset', 0 ) ) )
        return 'UTC';
 
    // adjust UTC offset from hours to seconds
    $utc_offset *= 3600;
 
    // attempt to guess the timezone string from the UTC offset
    if ( $timezone = timezone_name_from_abbr( '', $utc_offset, 0 ) ) {
        return $timezone;
    }
 
    // last try, guess timezone string manually
    $is_dst = date( 'I' );
 
    foreach ( timezone_abbreviations_list() as $abbr ) {
        foreach ( $abbr as $city ) {
            if ( $city['dst'] == $is_dst && $city['offset'] == $utc_offset )
                return $city['timezone_id'];
        }
    }
     
    // fallback to UTC
    return 'UTC';
}

function wpGetTimestamp($datetime_string = "now") {
  // get datetime object from site timezone
  $datetime = new \DateTime( $datetime_string, new \DateTimeZone( wpGetTimezoneString() ) );

  // get the unix timestamp (adjusted for the site's timezone already)
  return $datetime->format( 'U' );
}