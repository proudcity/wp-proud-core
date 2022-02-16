<?php

// Load Event scripts
function events_manager_script_enqueue() {
  global $post;

  if( !empty($post) && preg_match( '/id\=\"event\-form\"/', $post->post_content ) ) {
    global $proudcore;
    $proudcore::$libraries->addBundleToLoad('select2');
    wp_enqueue_script('proud-events-manager/js', plugins_url( 'assets/js/',__FILE__ ) . 'proud-events-manager.js', ['select2', 'proud'], true);
  }
}
add_action( 'wp_enqueue_scripts', 'events_manager_script_enqueue', 1);

// Unload module scripts
function events_manager_script_dequeue() {
  global $wp_scripts;

  // Unset the jqueryui code from events manager
  if( !empty( $wp_scripts->registered['events-manager']->extra['data'] ) ) {
    $css_url = json_encode( plugins_url() . '/events-manager/includes/css/jquery-ui.css' );
    $wp_scripts->registered['events-manager']->extra['data'] = str_replace($css_url, '""', $wp_scripts->registered['events-manager']->extra['data']);
  }
}
add_action( 'wp_print_scripts', __NAMESPACE__ . '\\events_manager_script_dequeue', 100 );

/**
 * Detects if we have an event spanning 2 days and modifies the data
 * so it shows up twice
 *
 */
function proud_events_filter( $events ){

	$new_events_array = array();
	foreach( $events as $event ){

		$start_local = get_post_meta( absint( $event->post_id ), '_event_start_local', true );
		$end_local = get_post_meta( absint( $event->post_id ), '_event_end_local', true );

		$start_stamp = strtotime( $start_local );
		$end_stamp = strtotime( $end_local );

		$new_events_array[] = $event;

		// if event exceets 24 hours (86400 seconds) we'll need to duplicate it
		if ( ( $end_stamp - $start_stamp ) >= 86400 ){

			$how_many_days = proud_how_many_days( $end_stamp, $start_stamp );
			echo 'how_many '. $how_many_days;
			$counter = 0;

			while( $counter <= $how_many_days ){
				$new_event = clone $event;

				// adjust start date time
				$start_date = $new_event->start_date;
				$date_adjust = $counter + 1;
				$new_event->start_date = date( 'Y-m-d', strtotime( $start_date . '+'.$date_adjust.' day' ) );

				// duplicate event
				$new_events_array[] = $new_event;

				$counter++;

			} // while

		} // if $end_stamp - $start_stam

	} // foreach

	return $new_events_array;

} // proud_events_filter
add_filter( 'proud_events_duplicator', 'proud_events_filter' );

/**
 * Returns the number of days that an event repeats so we can have events that span many days
 *
 * @since 2022.02.16
 * @author Curtis
 *
 * @param   int         $end            required                UNIX timestamp for the end of the event
 * @param   int         $start          required                UNIX timestamp for the start of the event
 * @return  int         $number_of_days                         The number of days we need to repeat an event
 */
function proud_how_many_days( $end, $start ){

	$difference = $end - $start;
	$number_of_days = $difference / 86400;

	return $number_of_days;

} // proud_how_many_days
