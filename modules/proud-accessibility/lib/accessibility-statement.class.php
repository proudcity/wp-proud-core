<?php
/**
 * @author ProudCity
 */

class ProudAccessibilityPage {

	// Page Content
	const _ACCESS_PAGE_TEMPLATE = 'proud-accessibility/statement-page.php';

	// Search page slug
	const _ACCESS_PAGE_SLUG = 'accessibility-commitment';

	// Add js?
	static $add_js;

	function __construct() {
		// Load our page
		// @TODO modify this so we're not loading every page load
		add_action( 'init', [ $this, 'get_accessibility_page' ] );
		// Add our template
		add_filter( 'template_include', [ $this, 'page_template' ], 99 );
		// Create default page template for accessibility results
		add_shortcode( 'proud_accessibility_shortcode', [ $this, 'accessibility_content' ] );
		// Load scripts
		add_action( 'wp_enqueue_scripts', [$this, 'scripts'] );
	}

	public function page_template( $template ) {

		if ( is_page( self::_ACCESS_PAGE_SLUG ) ) {
			$new_template = locate_template( self::_ACCESS_PAGE_TEMPLATE );
			if ( '' != $new_template ) {
				return $new_template;
			}
		}

		return $template;
	}

	/**
	 * Retrieve or create the accessibility page
	 */
	public function get_accessibility_page() {

		// Let other plugins (POLYLANG, ...) modify the accessibility page slug
		$access_page_slug = apply_filters( 'proud_filter_accessibility_page_slug', self::_ACCESS_PAGE_SLUG );

		// Search page is found by it's path (hard-coded).
		$access_page = get_page_by_path( $access_page_slug );

		if ( ! $access_page ) {

			$access_page = self::create_default_accessibility_page();

		} else {

			if ( $access_page->post_status != 'publish' ) {

				$access_page->post_status = 'publish';

				wp_update_post( $access_page );
			}
		}

		return $access_page;
	}


	/**
	 * Create a default accessibility page
	 *
	 * @return WP_Post The accessibility page
	 */
	static function create_default_accessibility_page() {

		// Let other plugins (POLYLANG, ...) modify the accessibility page slug
		$access_page_slug = apply_filters( 'proud_filter_accessibility_page_slug', self::_ACCESS_PAGE_SLUG );

		$_accessibility_page = array(
			'post_type'      => 'page',
			'post_title'     => 'Accessibility',
			'post_content'   => '[proud_accessibility_shortcode]',
			'post_status'    => 'publish',
			'post_author'    => 1,
			'comment_status' => 'closed',
			'post_name'      => $access_page_slug
		);

		// Let other plugins (POLYLANG, ...) modify the accessibility page
		$_accessibility_page = apply_filters( 'proud_filter_before_create_accessibility_page', $_accessibility_page );

		$access_page_id = wp_insert_post( $_accessibility_page );

		return get_post( $access_page_id );
	}

	public function scripts() {
		if (self::$add_js) {
			$path = plugins_url('../includes/js/',__FILE__);
			wp_register_script( 'proud-accessibility-page', $path . 'accessibility-page.js', ['proud-core'], false, true);
			wp_enqueue_script('proud-accessibility-page');
		}
	}

	public function accessibility_content() {
		$entity_name = apply_filters( 'proud_accessibility_entity_name', get_bloginfo('name') );
		include apply_filters('proud_accessibility_page_template', plugin_dir_path(__FILE__) . '../templates/statement-page.php');
	}
}

// Load it
$proudaccessibilitypage = new ProudAccessibilityPage();