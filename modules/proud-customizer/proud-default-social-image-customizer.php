<?php

namespace Proud\Core;
use WP_Customize_Cropped_Image_Control;

class Proud_Default_Social_Image_Customizer extends \ProudPlugin{

	private static $instance;

	public function __construct(){

		parent::__construct( array(
			'textdomain'     => 'proud_default_social_imagecustomizer',
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
			self::$instance = new Proud_Default_Social_Image_Customizer();
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
		add_action( 'customize_register', array( $this, 'default_social_image' ) );
	} // init

	public static function default_social_image( $customize ){

		$customize->add_setting(
			'proud_default_social_image',
			array(
				'default' => '',
				'type' => 'option',
				'capability' => 'edit_posts',
			),
		);

		$customize->add_control( new WP_Customize_Cropped_Image_Control(
			$customize,
			'proud_default_social_image',
			array(
				'label' => 'Default Social Image',
				'description' => 'Displays when there is no featured image set for content and when content is posted to social sites. Image should be 512px X 512px.',
				'priority' => 100,
				'section' => 'title_tagline',
				'type' => 'image',
			),
		));

		return $customize;

	} // default_social_image

}

Proud_Default_Social_Image_Customizer::instance();
