<?php

use Proud\Core;

class DocumentWidget extends Core\ProudWidget {

	function __construct() {
		parent::__construct(
			'proud_document_embed', // Base ID
			__( 'Embed Document', 'wp-proud-core' ), // Name
			array( 'description' => __( "Select a document and embed a document preview", 'wp-proud-core' ), ) // Args
		);
	}


	/**
	 * Define shortcode settings.
	 *
	 * @return  void
	 */
	function initialize() {
		// @todo: This shouldn't be a select (should be autocomplete).
		/*$documents = [ '' => __( '- Select -', 'wp-proud-core' ) ];
		$query_args = array(
		  'post_type'   => 'document',
		  'post_status' => 'publish',
		  'posts_per_page' => 50,
		);
		$query = new WP_Query( $query_args );
		foreach ($query as $item) {
		  $documents[$item->ID] = $item->post_title;
		}*/

		$this->settings += array(
			'post_id' => array(
				'#title'         => __( 'Document ID to embed', 'wp-proud-core' ),
				//'#type' => 'select',
				'#type'          => 'text',
				//'#options' => $documents,
				'#default_value' => '',
				'#description'   => __( '<a href="/wp-admin/edit.php?post_type=document" target="_blank">Find the document</a> to embed, and enter the URL to the document edit page (for example, ' . get_site_url() . '/wp-admin/post.php?post=11060&action=edit), or the document ID above.', 'wp-proud-core' ),
			),
		);
	}

	/**
	 * Determines if content empty, show widget, title ect?
	 *
	 * @see self::widget()
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Saved values from database.
	 *
	 * @return true;
	 */
	public function hasContent( $args, &$instance ) {
		return true;
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function printWidget( $args, $instance ) {
		extract( $instance );
		$id = $instance['post_id'];
		// Allow the edit url to be used OR the id
		$id   = preg_replace( '/(.+?)(\/wp-admin\/post\.php\?post=)([0-9]+).*/', '$3', $id );
		$file = plugin_dir_path( __FILE__ ) . 'templates/content-embed-document.php';
		// Include the template file
		include( $file );
	}
}

// register Foo_Widget widget
function register_document_widget() {
	// Only register if Document exists
	if ( class_exists( '\Proud\Document\ProudDocument' ) ) {
		register_widget( 'DocumentWidget' );
	}
}

add_action( 'widgets_init', 'register_document_widget' );