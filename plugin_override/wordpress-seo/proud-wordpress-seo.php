<?php


class ProudWordpressSeo {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('wpseo_metadesc', [ $this, 'yoast_change_description']);
        // SiteOrigin adds its sitemap filter on init at priority 100.
        add_action('init', [ $this, 'disable_so_sitemap_images' ], 101);
    }

    /**
     * Stop SiteOrigin Page Builder from rendering every page in a sitemap
     * to scrape images. One page-sitemap.xml request renders hundreds of
     * pages and exhausts memory (wp-proudcity#3007). Yoast still lists
     * featured and in-content images.
     */
    public function disable_so_sitemap_images() {
        remove_filter('wpseo_sitemap_urlimages', 'siteorigin_yoast_sitemap_images_compat', 10);
    }


    /**
     * Meta description label
     */
    public function yoast_change_description( $desc ) {
        $items = [
            'documents' => 'Search, view, and download official documents'
        ];

        if ( is_page( array_keys($items) ) ) {

            global $post;
            $slug = $post->post_name;

            if (isset($items[$slug])) {
                return __( $items[$slug], 'wp-proud-core' );
            }
        }

        return $desc;
    }
}

new ProudWordpressSeo();
// @TODO Jeff was this for something?
//get_post_field( 'post_name', get_post() );