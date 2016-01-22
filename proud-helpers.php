<?php
/**
 * @author ProudCity
 */

namespace Proud\Core;

// Hacky copied function to produce exerpt
function wp_trim_excerpt( $text = '' ) {
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
    $excerpt_more = apply_filters( 'excerpt_more', ' ' . '[&hellip;]' );
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
    $instance['text'] = do_shortcode( $instance['text'] );
  }
  return $text;
}