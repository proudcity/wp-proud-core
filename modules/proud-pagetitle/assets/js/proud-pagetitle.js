jQuery(document).ready(function($) {
    "use strict";
    $( "#title" ).change(function() {
        if($(this).val() !== ""){
            $('#title-alert').remove();
            var this_post_id;
            this_post_id = "";
            if($("#post_ID").val()){
                this_post_id = $("#post_ID").val();
            }
            var data = {
                'action': 'my_action',
                'title': $(this).val(),
                'post_ID' : this_post_id,
                '_wpnonce': proud_title._wpnonce
            };
            // We can also pass the url value separately from ajaxurl for front end AJAX implementations
            jQuery.post(proud_title.ajax_url, data, function(response) {
                if(response.duplicate_exists){
                    $('#titlewrap').append('<div id="title-alert" class="alert alert-danger"><p>' + response.duplicate_message + '</p></div>');
                }
            });
        }
    });
});   