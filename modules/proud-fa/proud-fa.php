<?php

namespace Proud\Core\Proud_FA;

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

	} // init

    public static function list_fonts(){
        $fonts = array(
            'fa-regular fa-address-card', 'fa-brands fa-airbnb', 'fa-brands fa-accessible-icon', 'fa-brands fa-amazon',
        );

        return $fonts;
    }

}

Proud_FA::instance();