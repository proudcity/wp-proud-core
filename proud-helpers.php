<?php
/**
 * @author ProudCity
 */

namespace Proud\Core;

// Hacky copied function to produce exerpt
function wp_trim_excerpt( $text = '', $more_link = false, $use_yoast = false, $words = false ) {
  $raw_excerpt = $text;
  // Try using yoast ?
  if( $use_yoast ) {
    global $post;
    if( !empty( $post ) ) {
      $text = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
    }
  }
  if ( empty( $text ) ) {
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
    $excerpt_length = !empty( $words ) ? $words : apply_filters( 'excerpt_length', 55 );
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
 * Gets  logo
 * As of 
 */
function get_proud_logo($use_theme = true, $logo_file = 'icon-white-1x.png') {
  if($use_theme) {
    // New media ID based logo
    $logo = get_theme_mod( 'proud_logo_id' );
    // Old url based logo
    if( !$logo ) {
      $logo = get_theme_mod( 'proud_logo' );
    }
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
 * Build responsive image meta data from the post information.
 * This is a replacement for wp_get_attachment_metadata(), which was returning no data.
 */
function build_responsive_image_metadata( $media_id ) {
  $media_post = get_post($media_id);
  $title  = !empty( $media_post->post_title ) ? $media_post->post_title : $media_post->post_name;
  $alt = get_post_meta( $media_id, '_wp_attachment_image_alt', true );
  return [
    'caption' => !empty($media_post->post_excerpt) ? $media_post->post_excerpt : null,
    //'class' => @todo
    'title' => $title,
    'alt' => $alt ? $alt : $title,
  ];
}


/** 
 * Build responsive image meta
 */
function build_responsive_image_meta( $media_id, $size_max = 'full-screen', $size_small = 'medium' ) {
  $return = [
    'srcset' => wp_get_attachment_image_srcset( $media_id, $size_max, null ),
    'size' => wp_get_attachment_image_sizes( $media_id, $size_max ),
    'src' => wp_get_attachment_image_src( $media_id, $size_small ),
    'meta' => build_responsive_image_metadata( $media_id ),
  ];
  return $return;
}


/** 
 * Print responsive image html
 */
function print_responsive_image( $resp_img, $classes = [], $skip_media = false ) {
  $classes[] = 'media';
  $image_meta = !empty( $resp_img['meta'] ) ? $resp_img['meta'] : [];
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
      <div class="media-byline text-left" onclick="jQuery(this).toggleClass('active');"><span class="media-byline-inner"><?php echo $image_meta['caption'] ?></span></div>
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
  // @todo: replace this with build_responsive_image_metadata( $media_id )?
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
      <div class="media-byline text-left" onclick="jQuery(this).toggleClass('active');"><span class="media-byline-inner"><?php echo $image_meta['caption'] ?></span></div>
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

    // @TODO figure out youtube user / channel
    case 'youtube':
      return sprintf( 'https://%s.com/channel/%s', $service, $account);
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

// Returns whether current office hours (formatted as just a plaintext string) are open
function isTimeOpen($string, &$alert, $holidays = '', $federal_holidays = true, $values = array('Closed', 'Open', 'Opening soon', 'Closing soon')) {
  /*
  $string = "Mon- Fri:2:00am -5:00pm
  Saturday: 9:00am - 12:00pm
  Sunday: Closed";
  */
  $string = str_replace( array('<br/>','<br>', '<br />'), "\n", $string);
  $classes = array('text-danger', 'text-success', 'text-warning', 'text-warning'); // Same key as $values

  $days = array( 'Mon', 'Monday', 'Tue', 'Tuesday', 'Wed', 'Wednesday', 'Thu', 'Thursday', 'Fri', 'Friday', 'Sat', 'Saturday', 'Sun', 'Sunday' );
  $nums = array( 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 7 );

  // Get the current site time
  $blogtime = current_time( 'mysql' ); 
  list( $today_year, $today_month, $today_day, $hour, $minute, $second ) = preg_split( '([^0-9])', $blogtime );
  $timestamp = current_time( 'timestamp' );
  $week_day = date('N', $timestamp);
  $daystamp = strtotime($hour .':'. $minute .':'. $second);
  $datestamp = strtotime($today_year .'-'. $today_month .'-'. $today_day);

  // Check today with the holidays
  $holidays .= $federal_holidays ? federalHolidays() ."\n" : "\n";
  $pattern = '/(.+?)\:\s?(.+?)\n/';
  $matches = array();
  $result = preg_match_all($pattern, $holidays, $matches);
  for ( $i = 0; $i < $result; $i++ ) {
    if ( strtotime($matches[2][$i]) == $datestamp ) {
      $alert = _x( 'Today is a holiday:', 'post name', 'wp-proud-core' ) .' '. $matches[1][$i];
      return false;
    }
  }

  // Deal with multiple office hours
  $pattern = "/(^|\n)([a-zA-Z\ \-\.]+)\n/";
  $type_matches = array();
  if (preg_match_all($pattern, $string, $type_matches, PREG_OFFSET_CAPTURE)) {
    $labels = array();
    //print_r($type_matches[0]);
    foreach ($type_matches[0] as $item) {
      array_push($labels, array(
        'label' => trim($item[0]),
        'value' => false,
        'class' => '',
        'index' => $item[1],
      ));
    }
  }
  else {
    $labels = array(array(
      'label' => 'Currently',
      'value' => false,
      'class' => '',
      'index' => 0,
    ));
  }
  
  $pattern = "/(^|\n)([a-zA-Z\ \-\.]+?)\:\s+?((\d+?)\:(\d+?)\s?(am|a\.m\.|pm|p\.m\.|AM|A\.M\.|PM|P\.M\.))\s?\-\s?((\d+?)\:(\d+?)\s?(am|a\.m\.|pm|p\.m\.|AM|A\.M\.|PM|P\.M\.))/";
  $matches = array();
  $result = preg_match_all($pattern, $string, $matches, PREG_OFFSET_CAPTURE);
  $status = null;

  //print_r($result);
  //print_r($matches);

  // Cycles though all of the valid days
  for ($i = 0; $i < $result; $i++) {

    // This allows us to support lunch breaks like:
    // Monday - Friday: 8:00am - 12:00pm
    // Monday - Friday: 1:00pm - 5:00pm
    if ($status == 1 || $status == 3) {
      continue;
    }


    // Do lots of clean up on the day of the week to support ranges
    $day = trim($matches[2][$i][0]);
    $day = str_replace($days, $nums, $day);
    $day = preg_replace("/[^0-9,.]/", "", $day);
    if (strlen($day) == 1) {
      $low = $high = (int)$day;
    }
    else {
      $low = substr($day, 0, 1);
      $high = substr($day, 1, 1);
    }

    // Check if Day of the week matches
    if ($week_day >= $low && $week_day <= $high) {

      // Figure out the status:
      // 0: closed
      // 1: open
      // 2: opening soon (<1 hr)
      // 3: closing soon (<1 hr)
      // $matches[2] is 9:00am; $matches[7] is 5:00pm
      $opens = strtotime($matches[3][$i][0]);
      $closes = strtotime($matches[7][$i][0]);
      //print_r($opens - $daystamp.'opens ');
      //print_r($closes - $daystamp.'closes ');
      if ( $opens - $daystamp <= 0 && $closes - $daystamp >= 0 ) {
        if ($closes - $daystamp <= 3600) {
          $status = 3;
        }
        else {
          $status = 1;
        }
      }
      elseif ( $closes - $daystamp >= 0 && $opens - $daystamp > -3600 ) {
        $status = 2;
      }
      else {
        $status = 0;
      }

      // Update the appropriate $labels value
      $found = false;
      for ($j = count($labels)-1; $j >= 0; $j--) {
        if ( $labels[$j]['index'] <= $matches[0][$i][1] && !$found) {
          $labels[$j]['value'] = $status;
          //print_r('status'.$status);
          $found = true;
        }
      }
    }

  } // for
  
  // Clean up the return
  foreach ($labels as $key => $label) {
    $labels[$key]['class'] = $classes[ $label['value'] ];
    $labels[$key]['value'] = $values[ $label['value'] ];
    unset($labels[$key]['index']);
  }

  return $labels;
}



// Returns a text string of Federal Holidays.
// Form: https://www.redcort.com/us-federal-bank-holidays/ 
function federalHolidays() {
  return 'New Year\'s Day: Monday, January 2 2017
Martin Luther King, Jr. Day: Monday, January 16 2017
George Washington’s Birthday: Monday, February 20 2017
Memorial Day: Monday, May 29 2017
Independence Day: Tuesday, July 4 2017
Labor Day: Monday, September 4 2017
Columbus Day: Monday, October 9 2017
Veterans Day: Friday, November 10 2017
Thanksgiving Day: Thursday, November 23 2017
Christmas Day: Monday, December 26 2016';
}
