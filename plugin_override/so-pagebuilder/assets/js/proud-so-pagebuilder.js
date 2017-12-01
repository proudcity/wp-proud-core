(function ($) {
  // Page builder ready
  $(document).ajaxComplete(function () {
    $('#so-panels-panels').on('click', function() {
      // Enable navigation prompt?
      if (!window.onbeforeunload) {
        window.onbeforeunload = function() {
          return true;
        };
      }
    });
  });
})(jQuery);
