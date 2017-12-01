<?php

namespace Proud\SOPagebuilder;

class ProudSOPagebuilder {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'siteorigin_panels_widgets', array( $this, 'so_panels_widgets' ), 11 );
		//add_filter( 'siteorigin_panels_css_object', array($this, 'siteorigin_panels_css_object'), -11, 3);
		add_filter( 'siteorigin_panels_prebuilt_layouts', array( $this, 'proud_prebuilt_layouts' ), 10, 2 );
		// filter row styles
		// add_filter('siteorigin_panels_layout_classes', array($this, 'alter_class'), 10);
		// add_filter('siteorigin_panels_layout_attributes', array($this, 'alter_attr'), 10);
		add_filter( 'siteorigin_panels_row_style_attributes', array( $this, 'so_row_style_attributes' ), 10, 2 );
		add_filter( 'siteorigin_panels_row_classes', array( $this, 'so_row_style_classes' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
	}

	// Restrict widgets available
	function so_panels_widgets( $widgets ) {
		//return $widgets;
		unset( $widgets['WP_Auth0_Embed_Widget'] );
		unset( $widgets['WP_Auth0_Popup_Widget'] );
		unset( $widgets['WP_Nav_Menu_Widget'] );
		unset( $widgets['EM_Widget'] );
		unset( $widgets['EM_Locations_Widget'] );
		unset( $widgets['EM_Widget_Calendar'] );
		unset( $widgets['search_FAQ_Widget'] );
		unset( $widgets['random_FAQ_Widget'] );
		unset( $widgets['recent_FAQ_Widget'] );
		unset( $widgets['topics_FAQ_Widget'] );
		unset( $widgets['cloud_FAQ_Widget'] );
		unset( $widgets['SiteOrigin_Panels_Widgets_PostContent'] );
		unset( $widgets['SiteOrigin_Panels_Widgets_PostLoop'] );
		unset( $widgets['SiteOrigin_Panels_Widgets_Layout'] );
		unset( $widgets['WP_Widget_Tag_Cloud'] );
		unset( $widgets['WP_Widget_Recent_Posts'] );
		unset( $widgets['WP_Widget_Categories'] );
		unset( $widgets['WP_Widget_Text'] );
		unset( $widgets['WP_Widget_Custom_HTML'] );
		unset( $widgets['WP_Widget_Text'] );
		unset( $widgets['WP_Widget_Search'] );
		unset( $widgets['WP_Widget_Meta'] );
		unset( $widgets['WP_Widget_Archives'] );
		unset( $widgets['WP_Widget_Calendar'] );
		unset( $widgets['WP_Widget_Pages'] );
		$widgets['WP_Widget_RSS']['icon'] = 'fa fa-rss';

		$widgets['SiteOrigin_Widget_Editor_Widget']['title'] = 'Text';
		$widgets['SiteOrigin_Widget_Editor_Widget']['icon']  = 'fa fa-font';

		$widgets['SiteOrigin_Widget_Image_Widget']['title'] = 'Image';
		$widgets['SiteOrigin_Widget_Image_Widget']['icon']  = 'fa fa-camera';

		//unset($widgets['SiteOrigin_Widget_Image_Widget']);

		unset( $widgets['SiteOrigin_Widget_Slider_Widget'] );
		unset( $widgets['SiteOrigin_Widget_Button_Widget'] );
		unset( $widgets['SiteOrigin_Widget_Features_Widget'] );
		unset( $widgets['SiteOrigin_Widget_PostCarousel_Widget'] );
		unset( $widgets['SiteOrigin_Widget_GoogleMap_Widget'] );
		unset( $widgets['SiteOrigin_Widget_SocialMediaButtons_Widget'] );
		unset( $widgets['SiteOrigin_Widget_PostCarousel_Widget'] );

		$widgets['ActionsBox']['icon'] = 'fa fa-wrench';
		$widgets['LocalMap']['icon']   = 'fa fa-map-marker';
		unset( $widgets['ActionsMenu'] );
		unset( $widgets['MainMenuList'] );
		unset( $widgets['FooterInfo'] );
		unset( $widgets['GoogleTranslate'] );
		unset( $widgets['ShareLinks'] );
		unset( $widgets['FontSize'] );
		unset( $widgets['GoogleTranslate'] );
		unset( $widgets['GoogleTranslate'] );
		$widgets['SearchBox']['icon']        = 'fa fa-search';
		$widgets['SocialFeed']['icon']       = 'fa fa-comments';
		$widgets['Submenu']['icon']          = 'fa fa-list-ul';
		$widgets['TeaserListWidget']['icon'] = 'fa fa-th-list';

		$widgets['ProudEmbedWidget']['icon'] = 'fa fa-code';

		$widgets['AgencyMenu']['icon']      = 'fa fa-list';
		$widgets['AgencySocial']['icon']    = 'fa fa-comment';
		$widgets['AgencyHours']['icon']     = 'fa fa-clock-o';
		$widgets['AgencyContact']['icon']   = 'fa fa-envelope';
		$widgets['JumbotronHeader']['icon'] = 'fa fa-picture-o';
		$widgets['IconSet']['icon']         = 'fa fa-th-large';
		$widgets['ImageSet']['icon']        = 'fa fa-th-large';
		$widgets['IconLink']['icon']        = 'fa fa-university';
		$widgets['PageTitle']['icon']       = 'fa fa-header';

		/*$widgets['AgencyMenu']['icon'] = 'fa fa-list';
		$widgets['AgencySocial']['icon'] = 'fa fa-comment';
		$widgets['AgencyHours']['icon'] = 'fa fa-clock-o';
		$widgets['AgencyContact']['icon'] = 'fa fa-envelope';
	*/
		$widgets['AgencyMenu']['icon']             = 'fa fa-building';
		$widgets['AgencySocial']['icon']           = 'fa fa-building';
		$widgets['AgencyHours']['icon']            = 'fa fa-building';
		$widgets['AgencyContact']['icon']          = 'fa fa-building';
		$widgets['AgencyTeaserListWidget']['icon'] = 'fa fa-building';

		/*$widgets['AgencyMenu']['icon'] = 'fa fa-building';
		$widgets['AgencySocial']['icon'] = 'fa fa-building';
		$widgets['AgencyHours']['icon'] = 'fa fa-building';
		$widgets['AgencyContact']['icon'] = 'fa fa-building';*/

		unset( $widgets['WP_Job_Manager_Widget_Recent_Jobs'] );
		unset( $widgets['WP_Job_Manager_Widget_Featured_Jobs'] );

		// hide gravity forms in favor of our own
		unset( $widgets['GFWidget'] );

		// hide ProudCity widgets from SO
		unset( $widgets['PoweredByWidget'] );
		unset( $widgets['LogoWidget'] );
		unset( $widgets['SocialLinksWidget'] );

		foreach ( $widgets as $key => $widget ) {
			if ( empty( $widget['class'] ) ) {
				unset( $widgets[ $key ] );
			}
		}

		//print_r($widgets);
		return $widgets;
	}

	// Load scripts
	function scripts() {
		$path = plugins_url( 'assets/js/', __FILE__ ) . 'proud-so-pagebuilder.js';
		wp_register_script( 'proud-so-admin-js', $path );
        wp_enqueue_script( 'proud-so-admin-js' );
    }

	// Try to alter some of the css code that is outputted
	/*function siteorigin_panels_css_object($css, $panels_data, $post_id) {
	  print_r($post_id);
	  print_r($css);
	  print_r($panels_data);
	  return $css;
	}*/

	// Add container class to rows that aren't full width
	function so_row_style_attributes( $attributes, $args ) {

		if ( empty( $args['row_stretch'] ) ) {
			array_push( $attributes['class'], 'container' );
		}

		return $attributes;
	}

	// Add container class to rows that aren't full width
	function so_row_style_classes( $classes, $args ) {
		if ( ! empty( $args['cells'] ) && $args['cells'] > 1 ) {
			$classes[] = 'panel-row-multiple';
		}

		return $classes;
	}


	function proud_prebuilt_layouts( $layouts ) {

		$layouts['agency-page'] = array(
			'name'        => __( 'Agency home page', 'proud' ),
			'description' => __( 'Agency header and sidebar with contact info', 'proud' ),    // Optional
			'widgets'     =>
				array(
					0 =>
						array(
							'text'         => '<h1>[title]</h1>',
							'headertype'   => 'header',
							'background'   => 'image',
							'pattern'      => '',
							'repeat'       => 'full',
							'image'        => '[featured-image]',
							'make_inverse' => 'no',
							'panels_info'  =>
								array(
									'class' => 'JumbotronHeader',
									'grid'  => 0,
									'cell'  => 0,
									'id'    => 0,
								),
						),
					1 =>
						array(
							'title'       => '',
							'panels_info' =>
								array(
									'class' => 'AgencyMenu',
									'raw'   => false,
									'grid'  => 1,
									'cell'  => 0,
									'id'    => 1,
								),
						),
					2 =>
						array(
							'title'       => '',
							'panels_info' =>
								array(
									'class' => 'AgencySocial',
									'raw'   => false,
									'grid'  => 1,
									'cell'  => 0,
									'id'    => 2,
								),
						),
					3 =>
						array(
							'title'       => 'Contact',
							'panels_info' =>
								array(
									'class' => 'AgencyContact',
									'raw'   => false,
									'grid'  => 1,
									'cell'  => 0,
									'id'    => 3,
								),
						),
					4 =>
						array(
							'title'       => 'Hours',
							'panels_info' =>
								array(
									'class' => 'AgencyHours',
									'raw'   => false,
									'grid'  => 1,
									'cell'  => 0,
									'id'    => 4,
								),
						),
					5 =>
						array(
							'title'                => '',
							'text'                 => '',
							'text_selected_editor' => 'tinymce',
							'autop'                => true,
							'_sow_form_id'         => '56ab38067a600',
							'panels_info'          =>
								array(
									'class' => 'SiteOrigin_Widget_Editor_Widget',
									'grid'  => 1,
									'cell'  => 1,
									'id'    => 5,
									'style' =>
										array(
											'background_image_attachment' => false,
											'background_display'          => 'tile',
										),
								),
						),
				),
			'grids'       =>
				array(
					0 =>
						array(
							'cells' => 1,
							'style' =>
								array(
									'row_stretch'        => 'full',
									'background_display' => 'tile',
								),
						),
					1 =>
						array(
							'cells' => 2,
							'style' =>
								array(),
						),
				),
			'grid_cells'  =>
				array(
					0 =>
						array(
							'grid'   => 0,
							'weight' => 1,
						),
					1 =>
						array(
							'grid'   => 1,
							'weight' => 0.33345145287029998,
						),
					2 =>
						array(
							'grid'   => 1,
							'weight' => 0.66654854712970002,
						),
				),
		);

		$layouts['landing-page'] = array(
			'name'        => __( 'Landing page', 'proud' ),
			'description' => __( 'Used on the homepage and other similar pages', 'proud' ),    // Optional
			'widgets'     => array(
				0 =>
					array(
						'title'       => '',
						'text'        => 'teafd',
						'filter'      => false,
						'panels_info' =>
							array(
								'class' => 'WP_Widget_Text',
								'raw'   => false,
								'grid'  => 0,
								'cell'  => 0,
								'id'    => 0,
								'style' =>
									array(
										'background_display' => 'tile',
									),
							),
					),
				1 =>
					array(
						'title'            => '',
						'active_tabs'      =>
							array(
								'faq'      => 'faq',
								'payments' => 'payments',
								'report'   => 'report',
								'status'   => 'status',
							),
						'category_section' =>
							array(
								0 => true,
							),
						'panels_info'      =>
							array(
								'class' => 'ActionsBox',
								'raw'   => false,
								'grid'  => 1,
								'cell'  => 0,
								'id'    => 1,
							),
					),
				2 =>
					array(
						'title'                => 'Recent news',
						'proud_teaser_content' => 'post',
						'proud_teaser_display' => 'mini',
						'post_count'           => '3',
						'link_title'           => 'More news',
						'link_url'             => '/news',
						'panels_info'          =>
							array(
								'class' => 'TeaserListWidget',
								'raw'   => false,
								'grid'  => 2,
								'cell'  => 0,
								'id'    => 2,
								'style' =>
									array(
										'background_display' => 'tile',
									),
							),
					),
				3 =>
					array(
						'title'                => 'Upcoming events',
						'proud_teaser_content' => 'event',
						'proud_teaser_display' => 'list',
						'post_count'           => '3',
						'link_title'           => 'More events',
						'link_url'             => '/events',
						'panels_info'          =>
							array(
								'class' => 'TeaserListWidget',
								'raw'   => false,
								'grid'  => 2,
								'cell'  => 1,
								'id'    => 3,
								'style' =>
									array(
										'background_display' => 'tile',
									),
							),
					),
				4 =>
					array(
						'title'       => '',
						'accounts'    => 'all',
						'custom'      => '',
						'services'    =>
							array(
								'facebook'  => 'facebook',
								'twitter'   => 'twitter',
								'youtube'   => 'youtube',
								'instagram' => 'instagram',
								'ical'      => 'ical',
								'rss'       => 'rss',
							),
						'widget_type' => 'static',
						'post_count'  => '20',
						'panels_info' =>
							array(
								'class' => 'SocialFeed',
								'raw'   => false,
								'grid'  => 3,
								'cell'  => 0,
								'id'    => 4,
							),
					),
				5 =>
					array(
						'title'         => '',
						'active_layers' =>
							array(
								'all' => 'all',
							),
						'panels_info'   =>
							array(
								'class' => 'LocalMap',
								'raw'   => false,
								'grid'  => 4,
								'cell'  => 0,
								'id'    => 5,
								'style' =>
									array(
										'background_display' => 'tile',
									),
							),
					),
			),
			'grids'       =>
				array(
					0 =>
						array(
							'cells' => 1,
							'style' =>
								array(
									'row_stretch'        => 'full',
									'background_display' => 'tile',
								),
						),
					1 =>
						array(
							'cells' => 1,
							'style' =>
								array(),
						),
					2 =>
						array(
							'cells' => 2,
							'style' =>
								array(),
						),
					3 =>
						array(
							'cells' => 1,
							'style' =>
								array(),
						),
					4 =>
						array(
							'cells' => 1,
							'style' =>
								array(
									'row_stretch'        => 'full',
									'background_display' => 'tile',
								),
						),
				),
			'grid_cells'  =>
				array(
					0 =>
						array(
							'grid'   => 0,
							'weight' => 1,
						),
					1 =>
						array(
							'grid'   => 1,
							'weight' => 1,
						),
					2 =>
						array(
							'grid'   => 2,
							'weight' => 0.5,
						),
					3 =>
						array(
							'grid'   => 2,
							'weight' => 0.5,
						),
					4 =>
						array(
							'grid'   => 3,
							'weight' => 1,
						),
					5 =>
						array(
							'grid'   => 4,
							'weight' => 1,
						),
				)
		);

		return $layouts;
	}
}

new ProudSOPagebuilder;
