jQuery(document).ready( function($){
  function media_upload( button_class, button_type ) {
    alert('a')
    if(wp.media && wp.media.editor) {
      var _custom_media = true,
      _orig_send_attachment = wp.media.editor.send.attachment;
      alert('s')
      jQuery('body').on('click', button_class, function(e) {
        alert('j')
          if (type === 'image') {
            var $img = $(this).prev('img');
            var $input = $img.prev('input');
          }
          else {
            var $filename = $(this).prev('div.upload_file_name');
            var $meta_input = $filename.prev('input');
            var $input = $meta_input.prev('input');
          }
          var send_attachment_bkp = wp.media.editor.send.attachment;
          _custom_media = true;
          wp.media.editor.send.attachment = function(props, attachment){
              if ( _custom_media  ) {
                 $input.val(attachment.id);
                 if (type === 'image') {
                   $img.attr('src', attachment.url);
                 }
                 else {
                   $filename.html('<a href="'+ attachment.url +'">'+ attachment.url +'</a>');
                   $meta_input.val(JSON.stringify(attachment));
                 }
              } else {
                  return _orig_send_attachment.apply( $input.attr('id'), [props, attachment] );
              }
          }
          wp.media.editor.open($input);
          return false;
      });
    }
  }

  media_upload('.upload_image_button', 'image');
  media_upload('.upload_file_button', 'file');
});