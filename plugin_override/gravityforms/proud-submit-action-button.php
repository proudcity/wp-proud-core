<?php

/**
 * Adds an "Apply Action Button Color" checkbox to Form Settings → Form Button.
 * GF's Settings API natively handles saving and restoring the value on the form object.
 */
function proud_action_button_form_setting($placement, $form_id)
{
    if ($placement == 50) { ?>
        <li class="action_setting field_setting">
            <input type="checkbox" id="proudActionButton" onclick="SetFieldProperty('proudActionButton', this.checked);" />
            <label for="proudActionButton" style="display:inline;">
                <?php _e("Action Button", "your_text_domain"); ?>
                <?php gform_tooltip("form_field_action_color") ?>
            </label>
        </li>
    <?php
    }
}
add_filter('gform_field_appearance_settings', 'proud_action_button_form_setting', 10, 2);

function proud_editor_script()
{
    ?>
    <script type='text/javascript'>
        //adding setting to fields of type "text"
        fieldSettings.submit += ", .action_setting";
        //binding to the load field settings event to initialize the checkbox
        jQuery(document).on("gform_load_field_settings", function(event, field, form) {
            jQuery('#proudActionButton').prop('checked', Boolean(rgar(field, 'proudActionButton')));
        });
    </script>
<?php
}
add_action('gform_editor_js', 'proud_editor_script');

function proud_add_action_button_tooltips($tooltips)
{
    $tooltips['form_field_action_color'] = "Checking this will apply the action button color from the Customizer to the form submit button.";
    return $tooltips;
}
add_filter('gform_tooltips', 'proud_add_action_button_tooltips');

/**
 * This worked at one point. Pretty sure we hooked the submit button
 *
 * https://docs.gravityforms.com/gform_submit_button/
 */
function proud_apply_action_button_color($button, $form)
{

    echo '<pre>';
    print_r($form);
    echo '</pre>';

    if (isset($form['proudActionButton']) && $form['proudActionButton']) {
        $actionColor = get_theme_mod('color_action_button', '#e49c11');

        $style = '<style type="text/css">.gform_button.button{background-color:' . $actionColor . ' !important; border-color:' . $actionColor . ' !important;}</style>';
        return $style . ' ' . $button;
    } else {
        return $button;
    }
}
