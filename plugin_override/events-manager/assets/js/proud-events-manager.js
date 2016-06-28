(function($, Proud) {
  Proud.behaviors.events_manager = {
    attach: function(context, settings) {
      
      // Add select2 for events forms
      $('#event-form select[multiple]').select2();
    }
  }
})(jQuery, Proud);