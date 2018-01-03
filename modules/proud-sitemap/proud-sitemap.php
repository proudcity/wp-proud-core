<?php

namespace Proud\Core;

if ( ! class_exists( 'SiteMap' ) ) {
	class SiteMap {

		// Sitemap page slug
		const _SITEMAP_PAGE_SLUG = 'sitemap';

		/**
		 * Constructor
		 */
		public function __construct() {
			// Create page if it doesn't exist
			add_action( 'init', array( $this, 'create_default_sitemap_page' ) );
			// Add Form Shortcode
			add_shortcode( 'wp-proud-sitemap', array( $this, 'show_output' ), 10, 3 );
		}

		/**
		 * Output from shortcode
		 */
		public function show_output( $atts, $content = null, $code = '' ) {
			// Only perform plugin functionality if post/page text has the shortcode in the page.
			if ( preg_match( '|wp-proud-sitemap|', $code ) ) {
				// Get menus from ProudMenu
				global $proud_menu_util;
				$menu_items = $proud_menu_util::$menus;
				if ( class_exists( '\Proud\Agency\Agency' ) ) {
					// Try to get agencies
					$args     = [
						'post_type'              => 'agency',
						'post_status'            => 'publish',
						'update_post_term_cache' => true, // don't retrieve post terms
						'update_post_meta_cache' => true, // don't retrieve post meta
						'posts_per_page'         => 100,
						'meta_query'             => [
							[
								'key'     => 'post_menu',
								'compare' => 'EXISTS'
							]
						],
					];
					$query    = new \WP_Query( $args );
					$agencies = $query->posts;
					// Attach agency to menu
					if ( ! empty( $agencies ) ) {
						foreach ( $agencies as $agency ) {
							$menu_slug = get_post_meta( $agency->ID, 'post_menu', true );
							if ( ! empty( $menu_items[ $menu_slug ] ) ) {
								// Build agency ID
								$menu_items[ $menu_slug ]->ageny_link = \Proud\Agency\get_agency_permalink( $agency->ID );
							}
						}
					}
				}
				// Build menus
				$menus = '';
				foreach ( $menu_items as $menu ) {
					$menu_header = ! empty( $menu->ageny_link )
						? "<a href=\"$menu->ageny_link\" rel=\"bookmark\">$menu->name</a>"
						: esc_html( $menu->name );
					$menus       .= '<h3>' . $menu_header . '</h3>';
					$menus       .= '<ul>' . wp_nav_menu( array(
							'menu'       => $menu->term_id,
							'container'  => 'false',
							'items_wrap' => '%3$s',
							'echo'       => '0'
						) ) . '</ul>';
				}
			}

			return $menus;
		}

		/**
		 * Create a default sitemap page
		 */
		static function create_default_sitemap_page() {

			// Let other plugins (POLYLANG, ...) modify the page slug
			$sitemap_page_slug = apply_filters( 'proud_filter_sitemap_page_slug', self::_SITEMAP_PAGE_SLUG );

			// Sitemap page is found by it's path (hard-coded).
			$sitemap_page = get_page_by_path( $sitemap_page_slug );
			// Already exists
			if ( ! empty( $sitemap_page ) ) {
				return;
			}

			$_sitemap_page = array(
				'post_type'      => 'page',
				'post_title'     => 'Sitemap',
				'post_content'   => '[wp-proud-sitemap]',
				'post_status'    => 'publish',
				'post_author'    => 1,
				'comment_status' => 'closed',
				'post_name'      => $sitemap_page_slug
			);

			// Let other plugins (POLYLANG, ...) modify the page
			$_sitemap_page = apply_filters( 'proud_filter_before_create_sitemap_page', $_sitemap_page );

			$sitemap_page_id = wp_insert_post( $_sitemap_page );

			update_post_meta( $sitemap_page_id, 'bwps_enable_ssl', '1' );
		}
	}

	new SiteMap();
}