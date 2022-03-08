<?php

/**
 * Hides WP Core, Plugin, Theme notifications unless we're on a designated
 * local, development, or staging site as defined in https://make.wordpress.org/core/2020/07/24/new-wp_get_environment_type-function-in-wordpress-5-5/
 *
 * @since 2022.03.08
 * @author Curtis McHale
 *
 * @param   object      $transient          required                The current transient set in WP
 * @uses    wp_get_environment_type()                               Returns the defined environment type. If not defined it's production
 * @return  object      $transient                                  Our possibly modified transient
 */
function proud_remove_core_updates( $transient ){
	global $wp_version;

	if ( wp_get_environment_type() === 'production' ){
		$transient = array('last_checked'=> time(),'version_checked'=> $wp_version,);

	}

	return(object) $transient;
}
add_filter('pre_site_transient_update_core',	'proud_remove_core_updates'); //hide updates for WordPress itself
add_filter('pre_site_transient_update_plugins',	'proud_remove_core_updates'); //hide updates for all plugins
add_filter('pre_site_transient_update_themes',	'proud_remove_core_updates'); //hide updates for all themes

/**
 * Fighting with stupid plugins to get their notices removed from the dashboard
 * because Dashboard notifications are totally out of hand.
 *
 * @since 2022.03.08
 * @author Curtis McHale
 */
function proud_remove_extra_notices(){

	if ( wp_get_environment_type() == 'production' ){
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'disable_comments_notice', 10 );
	}

}
add_action( 'wp_loaded', 'proud_remove_extra_notices', 99 );
