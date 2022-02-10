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

		$start = get_post_meta( absint( $event->post_id ), '_start_ts', true );
		$end = get_post_meta( absint( $event->post_id ), '_end_ts', true );

		$new_events_array[] = $event;

		// if event exceets 24 hours (86400 seconds) we'll need to duplicate it
		if ( ( $end - $start ) > 86400 ){

			$new_event = clone $event;

			// adjust start date time
			$start_date = $new_event->start_date;
			$new_event->start_date = date( 'Y-m-d', strtotime( $start_date . '+1 day' ) );

			// duplicate event
			$new_events_array[] = $new_event;
		}

	}

	return $new_events_array;
}
add_filter( 'proud_events_duplicator', 'proud_events_filter' );
