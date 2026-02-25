<?php

namespace Proud\Gform;

if (class_exists('GFCommon')) {

    // Load our downloading class
    require_once plugin_dir_path(__FILE__) . 'class-gc-gf-download.php';

    function proud_gravityforms_init()
    {
        // Always alter:

        add_filter('gform_confirmation_anchor', __NAMESPACE__ . '\\gform_confirmation_anchor_alter');
        add_filter("gform_init_scripts_footer", __NAMESPACE__ . '\\gform_force_footer_scripts');
        add_action('gform_enqueue_scripts', __NAMESPACE__ . '\\gform_css_dequeue', 100);
        add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\gform_admin_css_dequeue', 100);
        // Enable ability to controll label visibilit
        add_filter('gform_enable_field_label_visibility_settings', '__return_true');

        // dealing with entry export
        add_action('gform_post_export_entries', __NAMESPACE__ . '\\sync_entry_export_file', 10, 5);
        //add_action( 'wp_ajax_gf_download_export', __NAMESPACE__ . '\\gf_hijack_download_export' );
        remove_all_filters('wp_ajax_gf_download_export', 10);
        add_filter('wp_ajax_gf_download_export', __NAMESPACE__ . '\\gf_hijack_download_export', 1);

        //add_filter('gform_enable_legacy_markup', '__return_true');

        add_filter('gform_field_content', __NAMESPACE__ . '\\gf_remove_aria_required', 999, 5);

        add_filter('gform_add_field_buttons', __NAMESPACE__ . '\\proud_remove_gf_post_fields', 10, 1);
        add_filter('gform_disable_post_creation', '__return_true'); // stops all post creation

        add_action('gform_field_appearance_settings', __NAMESPACE__ . '\\proud_action_button', 10, 2);
        add_action('gform_editor_js', __NAMESPACE__ . '\\proud_action_button_editor_js');

        // Only alter if gravityforms <> stateless not enabled
        $statelessModuleActive = false;
        try {
            $statelessModuleActive = filter_var(\wpCloud\StatelessMedia\Module::get_module('gravity-form')['enabled'], FILTER_VALIDATE_BOOLEAN);
        } catch (\Throwable $t) {
            // don't care
        }

        if ($statelessModuleActive) {
            // Let stateless handle
            return;
        }

        \GC_GF_Download::maybe_process();
        add_filter('gform_secure_file_download_url', __NAMESPACE__ . '\\gform_secure_file_download_url', 100, 4);
        add_action('gform_save_field_value', __NAMESPACE__ . '\\gform_handle_file_upload', 100, 4);
    }

    add_action('init', __NAMESPACE__ . '\\proud_gravityforms_init', 11);

    function proud_action_button($position, $form_id)
    {
        // Position 250 sits just before the submit_width_setting in the Appearance tab.
        // GF renders one shared settings panel; JS (see proud_action_button_editor_js)
        // controls which field types actually see this <li> via fieldSettings.
        if ($position == 250) {
?>
            <li class="submit_action_button_setting field_setting">
                <input type="checkbox" id="field_submit_action_button" onclick="SetFieldProperty('submitActionButton', this.checked);">
                <label for="field_submit_action_button" class="inline">Apply Action Button Color</label>
            </li>
<?php
        }
    }

    function proud_action_button_editor_js()
    {
        ?>
        <script type="text/javascript">
            // Register our custom setting so GF shows it only for submit fields.
            jQuery(document).ready(function ($) {
                if (typeof fieldSettings !== 'undefined' && typeof fieldSettings['submit'] !== 'undefined') {
                    fieldSettings['submit'] += ', .submit_action_button_setting';
                }
            });

            // Populate the checkbox when the submit field's settings panel opens.
            jQuery(document).on('gform_load_field_settings', function (event, field, form) {
                jQuery('#field_submit_action_button').prop('checked', field.submitActionButton == true);
            });
        </script>
        <?php
    }

    /**
     * Removes the aria-required="true" field as it makes screen readers state "required" twice when the
     * field is also labeled required.
     *
     * Issue: https://github.com/proudcity/wp-proudcity/issues/2527
     * Docs link: https://docs.gravityforms.com/gform_field_content/
     *
     * @since 2024.09.04.0715
     * @author Curtis
     * @access public
     *
     * @param	string			$content		required			The entire printed content of the field
     * @return	string			$content							Our modified content
     */
    function gf_remove_aria_required($content, $field, $value, $lead_id, $form_id)
    {
        /**
         * We get the form first so we can check the setting for the requiredIndicator
         * and if it's text then we'll have the `required` text in the field label
         * and we can remove the `aria-required` field from the input.
         *
         * I think there are too many other options to test for if a user chooses
         * `custom` and then puts any arbitrary text in place for `required` text and thus
         * we're not testing further
         */
        $form = \GFAPI::get_form(absint($form_id));

        if (isset($form['requiredIndicator']) && 'text' === $form['requiredIndicator']) {
            $content = str_replace("aria-required='true'", '', $content);
        }
        return $content;
    }

    /**
     * Removes the fields specified in the $fields_to_remove array
     * from visibility in the GF creation
     *
     * @since 2024.07.24
     * @author Curtis
     *
     * @param	$field_buttons		array			required				The array of available field buttons
     * @return	$field_buttons												Our modified array of buttons
     */
    function proud_remove_gf_post_fields($field_buttons)
    {

        // removes the heading we don't need once we've removed button
        $field_buttons = array_filter($field_buttons, function ($group) {

            return ! in_array($group['name'], ['post_fields']);
        });

        return $field_buttons;


        // if you need to remove individual buttons then you can use this code below
        // clearly you'll need to work it into the filter above which removes an entire section
        foreach ($field_buttons as &$field_group) {

            if (is_array($field_group) && array_key_exists('fields', $field_group)) {

                // removes fields inside the group first
                $field_group['fields'] = array_filter($field_group['fields'], function ($field) {

                    $fields_to_remove = [
                        'post_title',
                        'post_content',
                        'post_excerpt',
                        'post_tags',
                        'post_category',
                        'post_image',
                        'post_custom_field',
                    ];

                    return ! in_array($field['data-type'], $fields_to_remove);
                });
            } // if

        } // foreach

        return $field_buttons;
    }

    /**
     * Hijacks the GF download function and sends it the file from WP Stateless
     *
     * @since 2024.02.21
     * @author Curtis
     *
     * @uses 	check_ajax_referer() 				verifies that the AJAX request is valid
     * @uses 	current_user_can() 					true if the user has required permissions
     * @uses 	getenv() 							gets k8s environment var
     * @uses 	rgget() 							GF function to get form data
     * @uses 	GFAPI::get_form() 					GF - returns form object
     * @uses 	sanitize_title_with_dashes() 		sanitizes title
     * @uses 	esc_attr() 							keeping content safe
     * @uses 	get_option() 						returns data from wp_options
     * @uses 	readfile() 							pushes file download
     */
    function gf_hijack_download_export()
    {

        check_ajax_referer('gform_download_export');

        if (! current_user_can('edit_posts')) {
            error_log('not allow to export gf entries');
            // not allow to export entries
            exit;
        }

        // defining the relative path starting point
        if (getenv('WORDPRESS_DB_NAME')) {
            $name = getenv('WORDPRESS_DB_NAME');
        } else {
            $name = 'wwwproudcity';
        }

        $form_id = rgget('form-id');
        $form = \GFAPI::get_form(absint($form_id));
        $form_title = $form['title'];

        $filename =  sanitize_title_with_dashes($form['title']) . '-' . gmdate('Y-m-d', \GFCommon::get_local_timestamp(time())) . '.csv';
        $url = 'https://storage.googleapis.com/proudcity/' . esc_attr($name) . '/uploads/gravity_forms/export/export-' . esc_attr(rgget('export-id')) . '.csv';

        $charset = get_option('blog_charset');
        header('Content-Description: File Transfer');
        header("Content-Disposition: attachment; filename=$filename");
        header('Content-Type: text/csv; charset=' . $charset, true);
        $result        = readfile($url);

        /**
         * Logging code if we need to check this in the future
        $logging = array(
            'url' => $url,
            'form_title' => $form_title,
            'export_id' => rgget('export-id'),
            'form_id' => rgget('form-id'),
        );

        error_log( print_r( $logging, true ) );

        update_option( 'sfn_test_download_url', $logging );
         */

        exit;
    }

    /**
     * Pushes the generated entry export file to cloud storage
     *
     * @since 2024.02.21
     * @author Curtis
     *
     * @param 		object 		$form 			optional 			GF form object
     * @param 		string 		$start_date 	optional 			start date for export
     * @param 		string 		$end_date 		optional 			end date for export
     * @param 		array 		$fields 		optional 			fields to include in export
     * @param 		string 		$exort_id 		required 			ID of the export we're dealing with
     * @uses 		wp_upload_dir() 								returns WP upload directory path
     * @uses 		esc_attr() 										keepin content safe
     * @uses 		getenv() 										returns k8s environment var
     * @uses 		sm:sync::syncFile 								hooks in with WP Stateless and syncs the given file
     */
    function sync_entry_export_file($form, $start_date, $end_date, $fields, $export_id)
    {

        $uploads_dir = wp_upload_dir();
        $absolutePath = $uploads_dir['basedir'] . '/gravity_forms/export/export-' . esc_attr($export_id) . '.csv';

        // defining the relative path starting point
        if (getenv('WORDPRESS_DB_NAME')) {
            $name = getenv('WORDPRESS_DB_NAME');
        } else {
            $name = 'wwwproudcity';
        }

        $relativePath = esc_attr($name) . '/uploads/gravity_forms/export/export-' . esc_attr($export_id) . '.csv';
        $relativePath = apply_filters('wp_stateless_filename', $relativePath, 0);

        do_action('sm:sync::syncFile', $relativePath, $absolutePath, true);
    }

    function get_upload_root_url()
    {
        // Get wordpress base;
        $dir = wp_upload_dir();
        if ($dir['error']) {
            return null;
        }

        // WP core upload
        return trailingslashit($dir['baseurl']);
    }

    function get_upload_root_dir()
    {
        // Get wordpress base;
        $dir = wp_upload_dir();
        if ($dir['error']) {
            return null;
        }

        // WP core upload
        return trailingslashit($dir['basedir']);
    }

    // Handle file field uploads to googlestorage
    function gform_secure_file_download_url($file, $form)
    {

        $bucketLink = trailingslashit('https://storage.googleapis.com/' . ud_get_stateless_media()->get('sm.bucket'));
        if (strpos($file, $bucketLink) !== false) {
            // Take out google storage
            $file = str_replace($bucketLink, '', $file);
            // Take out stateless root
            $file = str_replace(ud_get_stateless_media()->get('sm.root_dir'), '', $file);
            // WP core upload dir
            $upload_root_dir = get_upload_root_dir();
            // Gform upload dir
            $gform_upload = trailingslashit(str_replace($upload_root_dir, '', \GFFormsModel::get_upload_path($form->formId)));
            $file         = str_replace($gform_upload, '', $file);
            // Build hashed download
            $download_url = site_url('index.php');
            // Build args
            $args = array(
                'gc-gf-download' => urlencode($file),
                'form-id'        => $form->formId,
                'field-id'       => $form->id,
                'hash'           => \GFCommon::generate_download_hash($form->formId, $form->id, $file),
            );
            // @TODO force download?
            // if ( $force_download ) {
            //   $args['dl'] = 1;
            // }
            $file = add_query_arg($args, $download_url);
        }

        return $file;
    }

    function gform_get_gcloud_file($value)
    {

        // WP core upload url
        $upload_root_url = get_upload_root_url();
        if (strpos($value, $upload_root_url) !== false) {
            // Init WP-Stateless client
            $client = ud_get_stateless_media()->get_client();
            // Get file name (/wp-content/uploads/gravity_forms/[hash]/)
            $file = wp_normalize_path(str_replace($upload_root_url, '', $value));
            // Try to randomize filename to avoid conflicts
            $info = pathinfo($file);
            if (! empty($info['basename'])) {
                $file = trailingslashit($info['dirname']) . \wpCloud\StatelessMedia\Utility::randomize_filename($info['basename']);
            }
            // Gform upload dir (/var/www/html/wp-content/uploads/gravity_forms)
            $gform_upload = \GFFormsModel::get_upload_root();
            // Gform upload url (https://thesite.com/wp-content/uploads/gravity_forms)
            $gform_upload_url = \GFFormsModel::get_upload_url_root();
            // Path on WP system
            $absolute = wp_normalize_path(str_replace($gform_upload_url, $gform_upload, $value));
            // Send file to Google
            $media = $client->add_media(array_filter(array(
                'name'         => $file,
                'absolutePath' => $absolute,
                'cacheControl' => \wpCloud\StatelessMedia\Utility::getCacheControl(null, [], null),
            )));
            // Break if we have errors.
            // @note Errors could be due to key being invalid or now having sufficient permissions in which case should notify user.
            if (is_wp_error($media)) {
                return $value;
            }
            // Build our url again
            $bucketLink = 'https://storage.googleapis.com/' . ud_get_stateless_media()->get('sm.bucket');

            return $bucketLink . '/' . (! empty($media['name']) ? $media['name'] : $file);
        }
    }

    // Handle file field uploads to googlestorage
    function gform_handle_file_upload($value, $lead, $field, $form)
    {

        if (! function_exists('ud_get_stateless_media')) {
            return $value;
        }

        if (! empty($value) && $field->type === 'fileupload') {
            if ($field['multipleFiles']) {
                try {
                    $values = json_decode($value);
                } catch (\Exception $exception) {
                    // @TODO log this?
                    return $value;
                }

                if (! empty($values)) {
                    foreach ($values as $key => $v) {
                        $values[$key] = gform_get_gcloud_file($v);
                    }
                    return json_encode($values);
                }
            } else {
                return gform_get_gcloud_file($value);
            }
        }

        return $value;
    }


    // On ajax anchors, this adds a offset for the scroll
    function gform_confirmation_anchor_alter()
    {
        return 0;
    }

    function gform_force_footer_scripts()
    {
        return true;
    }


    function gform_css_dequeue()
    {
        wp_deregister_style('gforms_datepicker_css');
        wp_dequeue_style('gforms_datepicker_css');
    }

    function gform_admin_css_dequeue()
    {
        wp_deregister_style('gform_font_awesome');
        wp_dequeue_style('gform_font_awesome');
        global $wp_styles;
        if (! empty($wp_styles->registered['gform_tooltip']->deps)) {
            $wp_styles->registered['gform_tooltip']->deps = [];
        }
    }
}
