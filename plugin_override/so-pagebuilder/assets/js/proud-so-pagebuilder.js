(function ($) {
  // Page builder ready
  $(document).ajaxComplete(function () {
    // Set to changed if they click around
    $('#so-panels-panels').on('click', function() {
      // Enable navigation prompt?
      if (!window.onbeforeunload) {
        window.onbeforeunload = function() {
          return true;
        };
      }
    });

    // Reset on button
    $('#publishing-action [type="submit"]').on('click', function() {
      window.onbeforeunload = null;
    });
  });
})(jQuery);
