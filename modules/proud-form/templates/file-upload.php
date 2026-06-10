<div class="upload_file_name"><?php if(!empty($url)){ echo "<a href='" . esc_url($url) . "' target='_blank'>" . esc_html(basename($url)) . "</a>"; } ?></div>
<input class="upload_file_button" type="button" value="<?php if(!empty($media_id)){ echo esc_attr(__('Change File', $translate)); } else {echo esc_attr(__( 'Select File', $translate)); }?>" />
<input class="remove_file_button" type="button" value="Remove File" style="<?php if(empty($media_id)){ ?>display:none;<?php }?>" />
<?php do_action( 'proud_form_after_file_upload', $media_id, $url, $field ); ?>
