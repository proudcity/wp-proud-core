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
    public function yoast_change_description( $type ) {
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
    }


}

new ProudWordpressSeo();
get_post_field( 'post_name', get_post() );