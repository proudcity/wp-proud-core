<?php

use Proud\Core;

require_once __DIR__ . '/document-widget-ajax.php';

class DocumentWidget extends Core\ProudWidget {

	function __construct() {
		parent::__construct(
			'proud_document_embed', // Base ID
			__( 'Embed Document', 'wp-proud-core' ), // Name
			array( 'description' => __( "Select a document and embed a document preview", 'wp-proud-core' ), ) // Args
		);
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}


	/**
	 * Define shortcode settings.
	 *
	 * @return  void
	 */
	function initialize() {
		$this->settings += array(
			'post_id' => array(
				'#title'         => __( 'Document ID to embed', 'wp-proud-core' ),
				'#type'          => 'text',
				'#default_value' => '',
			),
		);
	}

	/**
	 * Enqueue admin picker assets on the widget screen and customizer.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$allowed_hooks = array( 'widgets.php', 'customize.php', 'post.php', 'post-new.php' );
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		$assets_url = plugins_url( 'assets/', __FILE__ );
		$assets_dir = __DIR__ . '/assets/';

		wp_enqueue_style(
			'proud-document-widget-admin',
			$assets_url . 'document-widget-admin.css',
			array(),
			filemtime( $assets_dir . 'document-widget-admin.css' )
		);

		wp_enqueue_script(
			'proud-document-widget-admin',
			$assets_url . 'document-widget-admin.js',
			array( 'jquery' ),
			filemtime( $assets_dir . 'document-widget-admin.js' ),
			true
		);

		wp_localize_script(
			'proud-document-widget-admin',
			'proudDocumentWidget',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'proud_document_search' ),
				'i18n'     => array(
					'search_placeholder' => __( 'Type to search documents...', 'wp-proud-core' ),
					'no_results'         => __( 'No documents found.', 'wp-proud-core' ),
					'loading'            => __( 'Searching...', 'wp-proud-core' ),
				),
			)
		);
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$instance  = $this->addSettingDefaults( $instance );
		$post_id   = isset( $instance['post_id'] ) ? $instance['post_id'] : '';
		$field_id  = $this->get_field_id( 'post_id' );
		$field_name = $this->get_field_name( 'post_id' );

		// Render the title field via the parent FormHelper.
		$title_settings = array( 'title' => $this->settings['title'] );
		$this->form->printFields( $instance, $title_settings, $this->number, 'widget' );
		?>
		<div data-proud-doc-picker>
			<p>
				<label for="<?php echo esc_attr( $field_id ); ?>-search">
					<?php esc_html_e( 'Search documents', 'wp-proud-core' ); ?>
				</label>
				<input
					type="text"
					id="<?php echo esc_attr( $field_id ); ?>-search"
					class="widefat"
					placeholder="<?php esc_attr_e( 'Type to search documents...', 'wp-proud-core' ); ?>"
					data-input
				/>
			</p>
			<ul data-results style="list-style:none;margin:0;padding:0;"></ul>
			<input
				type="hidden"
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="<?php echo esc_attr( $post_id ); ?>"
				data-hidden-id
			/>
			<div data-preview style="margin-top:8px;"></div>
		</div>
		<?php
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
