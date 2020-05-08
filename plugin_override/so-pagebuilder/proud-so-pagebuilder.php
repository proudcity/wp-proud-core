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
        add_filter( 'siteorigin_panels_css_row_gutter', array( $this, 'so_css_row_gutter' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
        add_filter( 'siteorigin_panels_widget_args', array( $this, 'so_widget_titles' ) );
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

        unset( $widgets['WP_Widget_Media_Image'] );

		unset( $widgets['SiteOrigin_Widget_Slider_Widget'] );
		unset( $widgets['SiteOrigin_Widget_Button_Widget'] );
		unset( $widgets['SiteOrigin_Widget_Features_Widget'] );
		unset( $widgets['SiteOrigin_Widget_PostCarousel_Widget'] );
		unset( $widgets['SiteOrigin_Widget_GoogleMap_Widget'] );
		unset( $widgets['SiteOrigin_Widget_SocialMediaButtons_Widget'] );
		unset( $widgets['SiteOrigin_Widget_PostCarousel_Widget'] );

		$widgets['ActionsBox']['icon'] = 'fa fa-exclamation-triangle';
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

		return $widgets;
	}

	// Load scripts
	function scripts() {
		$path = plugins_url( 'assets/js/', __FILE__ ) . 'proud-sobuilder.js';
		wp_register_script( 'proud-so-admin-js', $path );
        wp_enqueue_script( 'proud-so-admin-js' );
    }

    // Alter widget titles to be h2
    function so_widget_titles($args) {
        if (!empty($args['before_title'])) {
            $args['before_title'] = str_replace('<h3', '<h2', $args['before_title']);
        }
        if (!empty($args['after_title'])) {
            $args['after_title'] = str_replace('<h3', '<h2', $args['after_title']);
        }
        return $args;
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

	// Reduce SO panels margin to 0, allow bootstrap to handle
	function so_css_row_gutter () {
	    return 0;
    }


    // To generate these exports, follow steps on https://siteorigin.com/thread/programmatically-define-prebuilt-layouts/
    // Or, just enable Debug Bar and Debug Bar Consolr, and enter
    //   <pre><?php var_export( get_post_meta(30746, 'panels_data', true));
	function proud_prebuilt_layouts( $layouts ) {

        $dir = '/wp-content/plugins/wp-proud-core/plugin_override/so-pagebuilder/assets/images';
        $layoutKeys = [
            'homepage' => [
                'name'        => __( 'Homepage', 'proud' ),
                'description' => __( 'The standard ProudCity homepage', 'proud' ),   
                'screenshot' => "$dir/homepage.png",
      
            ],
            'landing' => [
                'name'        => __( 'Landing page', 'proud' ),
                'description' => __( 'Used on the homepage and other similar pages', 'proud' ),
                'screenshot' => "$dir/landing.png",
            ],
            'connect' => [
                'name'        => __( 'Connect page', 'proud' ),
                'description' => __( 'Links to Social networks and Facebook and Twitter embeds', 'proud' ),
                'screenshot' => "$dir/connect.png",
            ],
            'directory' => [
                'name'        => __( 'Directory page', 'proud' ),
                'description' => __( 'A list of Department contact information', 'proud' ),
                'screenshot' => "$dir/directory.png",
            ],
            'contact' => [
                'name'        => __( 'Contact page', 'proud' ),
                'description' => __( 'Contact links, form, phone number, address and hours', 'proud' ),
                'screenshot' => "$dir/contact.png",
            ],
            'events' => [
                'name'        => __( 'Events page', 'proud' ),
                'description' => __( 'A searchable list of upcoming events', 'proud' ),
                'screenshot' => "$dir/events.png",
            ],
            'news' => [
                'name'        => __( 'News page', 'proud' ),
                'description' => __( 'A searchable list recent news posts', 'proud' ),
                'screenshot' => "$dir/news.png",
            ],
            'government' => [
                'name'        => __( 'Government page', 'proud' ),
                'description' => __( 'A list of officials, deparments, and documents', 'proud' ),
                'screenshot' => "$dir/government.png",
            ],
            'services' => [
                'name'        => __( 'Services page', 'proud' ),
                'description' => __( 'A page dedicated to the ProudCity Service Center', 'proud' ),
                'screenshot' => "$dir/services.png",
            ],
            'service' => [
                'name'        => __( 'Service page', 'proud' ),
                'description' => __( 'Details and FAQ for an individual service', 'proud' ), //@todo
                'screenshot' => "$dir/service.png",
            ],
            'live' => [
                'name'        => __( 'Watch live page', 'proud' ),
                'description' => __( 'Stream your meetings live online', 'proud' ),
                'screenshot' => "$dir/live.png",
            ],
            'department' => [
                'name'        => __( 'Department home page', 'proud' ),
                'description' => __( 'Department header and sidebar with contact info', 'proud' ),    // Optional
                'screenshot' => "$dir/department.png",
            ],
            'division' => [
                'name'        => __( 'Division page', 'proud' ),
                'description' => __( 'Display a Department page with custom contact information', 'proud' ),    // Optional
                'screenshot' => "$dir/division.png",
            ],
            'department-documents' => [
                'name'        => __( 'Department documents', 'proud' ),
                'description' => __( 'A searchable list of documents for a specific department', 'proud' ),
                'screenshot' => "$dir/department-documents.png",
            ],
            'department-faq' => [
                'name'        => __( 'Department FAQ page', 'proud' ),
                'description' => __( 'Frequently asked questions for a specific department', 'proud' ),
                'screenshot' => "$dir/department-faq.png",
            ],
            'department-staff' => [
                'name'        => __( 'Department staff page', 'proud' ),
                'description' => __( 'A staff list for a specific department', 'proud' ),
                'screenshot' => "$dir/department-staff.png",
            ],
            'meeting-category' => [
                'name'        => __( 'Meeting category page', 'proud' ),
                'description' => __( 'A list of meetings for a specific category', 'proud' ),
                'screenshot' => "$dir/meeting-category.png",
            ],
            'meeting-archive' => [
                'name'        => __( 'Meeting archive page', 'proud' ),
                'description' => __( 'A list of meetings that is ideal for annual archives', 'proud' ),
                'screenshot' => "$dir/meeting-archive.png",
            ],
            
        ];

        foreach ($layoutKeys as $key => $meta) {
            include_once __DIR__ . "/layouts/{$key}.php";
            $layouts[$key] = array_merge($out, $meta);
        }

		return $layouts;
	}
}

new ProudSOPagebuilder;
