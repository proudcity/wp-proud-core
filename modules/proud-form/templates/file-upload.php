<div class="upload_file_name"><?php if(!empty($url)){ echo "<a href='$url' target='_blank'>". basename($url) ."</a>"; } ?></div>
<input class="upload_file_button" type="button" value="<?php if(!empty($media_id)){ echo __('Change File', $translate); } else {echo __( 'Select File', $translate); }?>" />
<input class="remove_file_button" type="button" value="Remove File" style="<?php if(empty($media_id)){ ?>display:none;<?php }?>" />
