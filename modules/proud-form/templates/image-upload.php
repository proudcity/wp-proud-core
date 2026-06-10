<img class="custom_media_image" src="<?php if(!empty($url)){echo esc_url($url);} ?>" style="margin:0;padding:0;max-width:100px;float:left;display:inline-block" />
<input class="upload_image_button" type="button" value="<?php if(!empty($media_id)){ echo esc_attr(__('Change Image', $translate)); } else {echo esc_attr(__( 'Select Image', $translate)); }?>" />
<?php do_action( 'proud_form_after_image_upload', $media_id, $url, $field ); ?>
