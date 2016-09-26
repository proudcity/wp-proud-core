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
            return (css.match (/level-[0-9]-active/g) || []).join(' ');
        }).addClass('level-' + level + '-active');
      }

    return self.init();
  });

  // init menu sliders
  $('.menu-slider').proudMenuSlider();

})(jQuery, Proud);