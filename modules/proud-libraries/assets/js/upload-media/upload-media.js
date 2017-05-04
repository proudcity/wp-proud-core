jQuery(document).ready( function($){
  function media_upload( button_class) {
    if(wp.media && wp.media.editor) {
      var _custom_media = true,
      _orig_send_attachment = wp.media.editor.send.attachment;
      jQuery('body').on('click', button_class, function(e) {
          var $img = $(this).prev('img');
          var $input = $img.prev('input');
          var send_attachment_bkp = wp.media.editor.send.attachment;
          _custom_media = true;
          wp.media.editor.send.attachment = function(props, attachment){
              if ( _custom_media  ) {
                 $input.val(attachment.id);
                 $img.attr('src',attachment.url);   
              } else {
                  return _orig_send_attachment.apply( $input.attr('id'), [props, attachment] );
              }
          }
          wp.media.editor.open($input);
          return false;
      });
    }
  }

  media_upload( '.upload_image_button');
});