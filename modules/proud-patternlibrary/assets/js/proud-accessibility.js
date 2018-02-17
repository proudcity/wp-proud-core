(function($, Proud) {

  /**
   * Forces alt text on image insert
   */

  $(document).ready( function(){

    // Extend the WP backbone model for the image button
    if (window.wp && window.wp.media && window.wp.media.view && window.wp.media.view.Button) {
      window.wp.media.view.Button = window.wp.media.view.Button.extend({
        // Save orig
        oldInitialize: window.wp.media.view.Button.prototype.initialize,
        // Add our click processing
        click: function(event){
          // In insert mode
          if (this.$el.hasClass('media-button-insert') || this.$el.hasClass('media-button-select')) {
            // Check if alt has value
            var $alt = $('[data-setting="alt"] input:visible');
            if ($alt.length && !$alt.val()) {
              // Make input obvious
              $alt.css({ 'border-color': 'red' })
              // Make label red, add required
              $alt.siblings('label, span')
                .css({ color: 'red' })
                .append('<abbr class="required">*</abbr>');
              // Append msg
              $alt.after(
                $('<p class="help-block">See <a href="http://webaim.org/techniques/alttext/" target="_blank">this for more information</a>.</p>')
                  .css({ 
                    clear: 'both',
                    color: 'red',
                    float: 'right',
                    width: '65%',
                  })
              );
              alert('Please provide the "alt" text for the image.')
              event.preventDefault();
              return false;
            }
          }
          this.oldClick(event);
        },
        // Save orig
        oldClick: window.wp.media.view.Button.prototype.click,
      });
    }
  });
})(jQuery, Proud);