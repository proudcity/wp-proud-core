(function($, Proud) {

  $.fn.proudMenuSlider = (function() {
      var self = this,
          active = 1,
          height = 0,
          $slider = $(this);

      self.init = function() {
        // Set active
        active = $slider.data('level-active');
        // Set height
        self.alterHeight(active, false);
        // Init clicks
        // @TODO evaluate if the navigation forward is important
        $('[data-active-click]', $slider).on('click', self.sliderClick);
        $('[data-back-click]', $slider).on('click', self.sliderClick);
        // Set watch on resize
        self.setWatch();
      }

      self.sliderClick = function(event) {
        event.preventDefault();
        var level = $(this).data('active-click') || $(this).data('back-click') || 1;
        // Alter items
        self.alterHeight(level, true);
        self.alterClass(level);
        // Save active
        active = level;
      }

      self.alterHeight = function(levelTo, animate) {
        var toHeight = $slider.find('.level-' + levelTo).height();
        if(!animate) {
          $slider.height(toHeight);
        }
        else {
          $slider.animate({
            height: toHeight
          }, 250 );
        }
        height = toHeight;
      }

      self.alterClass = function(level) {
        $slider.removeClass(function (index, css) {
            return (css.match (/level-[0-9]+-active/g) || []).join(' ');
        }).addClass('level-' + level + '-active');
      }

      // Description:
      //  Executes a function a max of once every n milliseconds
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
          $(window).on('resize.proudMenu', self.throttle(function () {
              self.alterHeight(active, false);
          }, 50));
      }

    return self.init();
  });

  // init menu sliders
  $('.menu-slider').proudMenuSlider();

})(jQuery, Proud);