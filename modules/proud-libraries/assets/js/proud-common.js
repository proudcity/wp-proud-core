(function($, Proud) {

  // Add addBack to 1.7
  if(typeof jQuery.fn.addBack !== 'function') {
    jQuery.fn.addBack = jQuery.fn.andSelf;
  }

  // Placeholder watch function
  Proud.behaviors.shadowWatch = {
    attach: function() {
      $('[data-shadow-watch]').once('shadow-watch', function() {
        var $shadow = $(this);
        $shadow.prev().each(function() {
          var toWatch = this;
          var checkingCount = 0;
          var checkingShadow = setInterval(function() {
            console.log(toWatch.offsetHeight);
            // Check if we have (arbitrary) height yet
            if (toWatch.offsetHeight > 20 || checkingCount > 20) {
              clearInterval(checkingShadow);
              $shadow.remove();
            }
            checkingCount++;
          }, 10);
        });
      });
    }
  }

  Proud.behaviors.height_equalize = {
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