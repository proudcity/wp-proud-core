(function($, Proud) {

  // Add addBack to 1.7
  if(typeof jQuery.fn.addBack !== 'function') {
    jQuery.fn.addBack = jQuery.fn.andSelf;
  }

  Proud.behaviors.proud_common = {
    attach: function(context, settings) {
      // Run height equalizer
      $('[data-equalizer]').once('equalize-default', function() {
        $(this).equalizeHeight();
      });
      // For card columns
      $('.card-columns-equalize').once('card-columns-equal', function() {
        $(this).equalizeHeight(false, '.card');
      });
      // Styles popup
      $('#panels-edit-style-type-form').once('card-columns-equal', function() {
        $(this).equalizeHeight(false, '.card');
      });
    }
  }
})(jQuery, Proud);