<?php

namespace Proud\Core;

class Proud_FA extends \ProudPlugin{

	private static $instance;

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
			self::$instance = new Proud_FA();
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
        add_action( 'font_awesome_enqueued', array( $this, 'get_fa_fonts' ) );
		add_action( 'font_awesome_preferences', array( $this, 'fa_prefs' ) );
	} // init

	/**
	 * Registers the plugin as something that is using FA in the FA plugin troubleshooting tab
	 * 
	 * @since 2022.04.05
	 * @author Curtis McHale
	 * @access public
	 * @link: https://github.com/FortAwesome/wordpress-fontawesome#adding-as-a-composer-package
	 */
	public static function fa_prefs(){

		\FortAwesome\fa()->register(
			array(
				'name' => 'Proud Core',
			)
		);

	} // fa_prefs

    public static function list_fonts(){

        $fonts = self::get_fa_fonts();

        return $fonts;

    } // list_fonts

	/**
	 * Returns the array of available icons
	 */
    public static function get_fa_fonts(){
		
		$fonts = array();

		if ( true === \FortAwesome\fa()->pro() ){
			$fa_pro = get_transient( 'fa_pro_icon_trans' );

			if ( false === $fa_pro ){
				$fa_pro = get_option( 'fa_pro_icons' );
			}

			// check the transient first because it should be faster
			$fa_basic = get_transient( 'fa_basic_icons_trans' );

			if ( false === $fa_basic ){
				$fa_basic = get_option( 'fa_basic_icons' );
			}
			
			// merging pro and basic icons together for pro users
			$fonts = array_merge( $fa_pro, $fa_basic );
		} else {

			// check the transient first because it should be faster
			$fa_trans = get_transient( 'fa_basic_icons_trans' );

			if ( false === $fa_trans ){
				$fa_trans = get_option( 'fa_basic_icons' );
			}

			$fonts = $fa_trans;
		}

		return $fonts;

    } // get_fa_fonts

}

Proud_FA::instance();