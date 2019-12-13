jQuery(document).ready( function($){
  function media_upload( button_class, button_type ) {
    if(wp.media && wp.media.editor) {
      var _custom_media = true,
      _orig_send_attachment = wp.media.editor.send.attachment;
      jQuery('body').on('click', button_class, function(e) {
          if (button_type === 'image') {
            var $img = $(this).prev('img');
            var $input = $img.prev('input');
          }
          else {
            var $filename = $(this).prev('div.upload_file_name');
            var $removeBtn = $(this).parent().find('.remove_file_button');
            var $input = $filename.prev('input');
          }
          var send_attachment_bkp = wp.media.editor.send.attachment;
          _custom_media = true;
          wp.media.editor.send.attachment = function(props, attachment){
              if ( _custom_media  ) {
                 $input.val(attachment.id);
                 if (button_type === 'image') {
                   $img.attr('src', attachment.url);
                 }
                 else {
                   $filename.html('<a href="'+ attachment.url +'">'+ attachment.filename +'</a>');
                   console.log(' $removeBtn',  $removeBtn.html());
                   $removeBtn.show();
                   $input.val(attachment.id);
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

  $('.upload_image_button, .upload_file_button, .remove_file_button').css({
    'min-width': 0,
    'max-width': '120px',
  });
  $('.remove_file_button').bind('click', function(e) {
    e.preventDefault();
    var $parent = $(this).parent();
    $parent.find('.form-control').val('');
    $parent.find('.upload_file_name').html('');
    $parent.find('.upload_file_button').val('Select File');
    $(this).hide();
  })
  $('.remove_file_button').css({'margin': 0});
});