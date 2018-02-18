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
//		add_filter( 'template_include', [ $this, 'page_template' ], 99 );
        $this->hook( 'template_redirect', 'template_redirect');
        $this->hook( 'query_vars', 'query_vars');
        $this->hook( 'init', 'register_rewrite_rules');

        //add_action( 'wp_enqueue_scripts', [$this, 'scripts'] );
	}

//	public function page_template( $template ) {
//        print_r('c');
//        if ( !( defined( 'WP_CLI' ) && WP_CLI ) && is_page( self::_SLUG ) ) {
//            print_r('ab');exit;
//			$new_template = locate_template( self::_PAGE_TEMPLATE );
//			if ( '' != $new_template ) {
//				return $new_template;
//			}
//		}
//
//		return $template;
//	}


    /**
     * Handle requests for form iframes.
     */
    public function template_redirect() {
        global $wp;

        if ( empty( $wp->query_vars['wp_proud_patternlibrary'] ) ) {
            return;
        }

        require_once( plugin_dir_path(__FILE__) . 'templates/page.php' );

        exit;
    }


    /**
     * Add custom rewrite rules for the templates pages.
     */
    public function register_rewrite_rules() {
        add_rewrite_rule('^patterns\/?', 'index.php?wp_proud_patternlibrary=1', 'top');
    }


    /**
     * Whitelist custom query vars.
     *
     * @param array $vars Allowed query vars.
     * @return array
     */
    public function query_vars( $vars ) {
        $vars[] = 'wp_proud_patternlibrary';
        return $vars;
    }


}

// Load it
$ProudPatternLibrary = new ProudPatternLibrary();