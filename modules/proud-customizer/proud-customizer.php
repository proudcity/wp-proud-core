<?php

namespace Proud\Core;

class Proud_Customizer extends \ProudPlugin{

	private static $instance;

	public function __construct(){

		parent::__construct( array(
			'textdomain'     => 'proud_customizer',
			'plugin_path'    => __FILE__,
		) );

	}

	/**
	 * Spins up the instance of the plugin so that we don't get many instances running at once
	 *
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 *
	 * @uses $instance->init()                      The main get it running function
	 */
	public static function instance(){

		if ( ! self::$instance ){
			self::$instance = new Proud_Customizer();
			self::$instance->init();
		}

	} // instance

	/**
	 * Spins up all the actions/filters in the plugin to really get the engine running
	 *
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 */
	public function init(){
		add_action( 'customize_register', array( $this, 'site_icon_capabilities' ), 999 );
	} // init

	/**
	 * Changing the setting for the customizer so that editors can change the site icon
	 *
	 * @since 2022.11.24
	 * @author Curtis
	 * @access public
	 *
	 * @param       object          $customize          required            The registered customizer settings
	 * @uses        get_setting()                                           Returns the settings objects
	 * @return      object          $customize                              Our modified customizer setting
	 */
	public static function site_icon_capabilities( $customize ){

		// letting editors change the site icon in the customizer
		$customize->get_setting( 'site_icon' )->capability = 'edit_posts';

		return $customize;

	}

}

Proud_Customizer::instance();
