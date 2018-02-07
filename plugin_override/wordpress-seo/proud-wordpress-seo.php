<?php


class ProudWordpressSeo {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('wpseo_metadesc', [ $this, 'yoast_change_description']);
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