<?php
/**
 * @author ProudCity
 */

class ProudPatternLibrary {
    
	// Search page slug
	const _SLUG = 'pattern';

	const _PAGE_TEMPLATE = 'proud-patternlibrary/page.php';

	// Add js?
	static $add_js;

	function __construct() {
		// @TODO modify this so we're not loading every page load
		// Add our template
		add_filter( 'template_include', [ $this, 'page_template' ], 99 );
		//add_action( 'wp_enqueue_scripts', [$this, 'scripts'] );
	}

	public function page_template( $template ) {
        print_r('c');
        if ( !( defined( 'WP_CLI' ) && WP_CLI ) && is_page( self::_SLUG ) ) {
            print_r('ab');exit;
			$new_template = locate_template( self::_PAGE_TEMPLATE );
			if ( '' != $new_template ) {
				return $new_template;
			}
		}

		return $template;
	}


}

// Load it
$ProudPatternLibrary = new ProudPatternLibrary();