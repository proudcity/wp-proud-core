<?php

namespace Proud\Gform;

if ( class_exists( 'GFCommon' ) ) {

	// Load our downloading class
	require_once plugin_dir_path( __FILE__ ) . 'class-gc-gf-download.php';

	// Add processing for download links
	add_action( 'init', array( 'GC_GF_Download', 'maybe_process' ), 11 );

	function get_upload_root_url() {
		// Get wordpress base;
		$dir = wp_upload_dir();
		if ( $dir['error'] ) {
			return null;
		}

		// WP core upload
		return trailingslashit( $dir['baseurl'] );
	}

	function get_upload_root_dir() {
		// Get wordpress base;
		$dir = wp_upload_dir();
		if ( $dir['error'] ) {
			return null;
		}

		// WP core upload
		return trailingslashit( $dir['basedir'] );
	}

	// Handle file field uploads to googlestorage
	function gform_secure_file_download_url( $file, $form ) {
		$bucketLink = trailingslashit( 'https://storage.googleapis.com/' . ud_get_stateless_media()->get( 'sm.bucket' ) );
		if ( strpos( $file, $bucketLink ) !== false ) {
			// Take out google storage
			$file = str_replace( $bucketLink, '', $file );
			// Take out stateless root
			$file = str_replace( ud_get_stateless_media()->get( 'sm.root_dir' ), '', $file );
			// WP core upload dir
			$upload_root_dir = get_upload_root_dir();
			// Gform upload dir
			$gform_upload = trailingslashit( str_replace( $upload_root_dir, '', \GFFormsModel::get_upload_path( $form->formId ) ) );
			$file         = str_replace( $gform_upload, '', $file );
			// Build hashed download
			$download_url = site_url( 'index.php' );
			// Build args
			$args = array(
				'gc-gf-download' => urlencode( $file ),
				'form-id'        => $form->formId,
				'field-id'       => $form->id,
				'hash'           => \GFCommon::generate_download_hash( $form->formId, $form->id, $file ),
			);
			// @TODO force download?
			// if ( $force_download ) {
			//   $args['dl'] = 1;
			// }
			$file = add_query_arg( $args, $download_url );
		}

		return $file;
	}

	add_filter( 'gform_secure_file_download_url', __NAMESPACE__ . '\\gform_secure_file_download_url', 100, 4 );

	function gform_get_gcloud_file( $value ) {

		// WP core upload url
		$upload_root_url = get_upload_root_url();
		if ( strpos( $value, $upload_root_url ) !== false ) {
			// Init WP-Stateless client
			$client = ud_get_stateless_media()->get_client();
			// Get file name (/wp-content/uploads/gravity_forms/[hash]/)
			$file = wp_normalize_path( str_replace( $upload_root_url, '', $value ) );
			// Try to randomize filename to avoid conflicts
			$info = pathinfo( $file );
			if ( ! empty( $info['basename'] ) ) {
				$file = trailingslashit( $info['dirname'] ) . \wpCloud\StatelessMedia\Utility::randomize_filename( $info['basename'] );
			}
			// Gform upload dir (/var/www/html/wp-content/uploads/gravity_forms)
			$gform_upload = \GFFormsModel::get_upload_root();
			// Gform upload url (https://thesite.com/wp-content/uploads/gravity_forms)
			$gform_upload_url = \GFFormsModel::get_upload_url_root();
			// Path on WP system
			$absolute = wp_normalize_path( str_replace( $gform_upload_url, $gform_upload, $value ) );
			// Send file to Google
			$media = $client->add_media( array_filter( array(
				'name'         => $file,
				'absolutePath' => $absolute,
				'cacheControl' => \wpCloud\StatelessMedia\Utility::getCacheControl( null, [], null ),
			) ) );
			// Break if we have errors.
			// @note Errors could be due to key being invalid or now having sufficient permissions in which case should notify user.
			if ( is_wp_error( $media ) ) {
				return $value;
			}
			// Build our url again
			$bucketLink = 'https://storage.googleapis.com/' . ud_get_stateless_media()->get( 'sm.bucket' );

			return $bucketLink . '/' . ( ! empty( $media['name'] ) ? $media['name'] : $file );
		}
	}

	// Handle file field uploads to googlestorage
	function gform_handle_file_upload( $value, $lead, $field, $form ) {

		if ( ! function_exists( 'ud_get_stateless_media' ) ) {
			return $value;
		}

		if ( ! empty( $value ) && $field->type === 'fileupload' ) {
			if ( $field['multipleFiles'] ) {
				try {
					$values = json_decode( $value );
				} catch ( \Exception $exception ) {
					// @TODO log this?
					return $value;
				}

				if ( ! empty( $values ) ) {
					foreach ( $values as $key => $v ) {
						$values[ $key ] = gform_get_gcloud_file( $v );
					}

					return json_encode( $values );
				}
			} else {
				return gform_get_gcloud_file( $value );
			}
		}

		return $value;
	}

	add_action( 'gform_save_field_value', __NAMESPACE__ . '\\gform_handle_file_upload', 100, 4 );


	// On ajax anchors, this adds a offset for the scroll
	function gform_confirmation_anchor_alter() {
		return 0;
	}

	add_filter( 'gform_confirmation_anchor', __NAMESPACE__ . '\\gform_confirmation_anchor_alter' );

	function gform_force_footer_scripts() {
		return true;
	}

	add_filter( "gform_init_scripts_footer", __NAMESPACE__ . '\\gform_force_footer_scripts' );


	function gform_css_dequeue() {
		wp_deregister_style( 'gforms_datepicker_css' );
		wp_dequeue_style( 'gforms_datepicker_css' );
	}

	add_action( 'gform_enqueue_scripts', __NAMESPACE__ . '\\gform_css_dequeue', 100 );

	function gform_admin_css_dequeue() {
		wp_deregister_style( 'gform_font_awesome' );
		wp_dequeue_style( 'gform_font_awesome' );
		global $wp_styles;
		if ( ! empty( $wp_styles->registered['gform_tooltip']->deps ) ) {
			$wp_styles->registered['gform_tooltip']->deps = [];
		}
	}

	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\gform_admin_css_dequeue', 100 );


	// Enable ability to controll label visibility
	add_filter( 'gform_enable_field_label_visibility_settings', '__return_true' );
}