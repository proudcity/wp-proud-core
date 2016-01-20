(function($) {
  // Function normalizes height
  // looks for [data-equalizer], and equals 
  // height for all [data-equalize-height] children
  // 
  // var mq (optional)
  //     mq = a media query, test for true 
  //     mq = null, then run resize for all windows
  $.fn.equalizeHeight = (function(mq, childrenSel) {

      var self      = this,
          $children = [],
          childrenSel = childrenSel || '[data-equalize-height]',
          mq = mq || false;

      self.init = function() {
          $children = $(this).find(childrenSel);
          if($children.length) {
              self.equalize(true);
              self.setWatch();
          }
      }

      self.reset = function() {
          $.each($children, function (index, element) {
              $(element).css("height", "");
          });
      }

      self.runCalc = function() {
        var largestHeight = 0;
        if(  !mq || window.matchMedia(mq).matches ) {
            $.each($children, function (index, element) {
                var thisHeight = $(element).outerHeight();
                if (thisHeight > largestHeight) {
                    largestHeight = thisHeight;
                }
            });
        }
        $.each($children, function(index, element) {
            $(element).css({ 'height': largestHeight + "px" });
        });
      }

      self.equalize = function(firstCall) {
          if(firstCall) {
              $(self).waitForImages({
                  finished: function() {
                      self.runCalc();
                  },
                  each: $.noop,
                  waitForAll: true
              });
          }
          else {
              self.reset();
              self.runCalc();
          }
            
      }

      // Description:
      //    Executes a function a max of once every n milliseconds
      self.throttle = function (func, delay) {
          var timer = null;

          return function () {
              var context = this, args = arguments;

              if (timer == null) {
                timer = setTimeout(function () {
                  func.apply(context, args);
                  timer = null;
                }, delay);
              }
          };
      }

      self.setWatch = function() {
          // Set watch
          $(window).on('resize.equalizer', self.throttle(function () {
              self.equalize();
          }, 50));
      }

      return self.init();
  });
})(jQuery);
