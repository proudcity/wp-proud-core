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

/** 
 * Sanitize text input
 */
function build_responsive_image_meta($media_id, $size_max = 'full', $size_small = 'medium') {
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
 * Sanitize text input
 */
function print_responsive_image($resp_img, $classes = [], $skip_media = false) {
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