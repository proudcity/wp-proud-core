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
        // Slight timeout ot make sure we get height right
        setTimeout(function () {
          self.alterHeight(active, false);
          // make visible
          $slider.css({ 'opacity': 1 });
        }, 500);
        // Init clicks
        // @TODO evaluate if the navigation forward is important
        // $('[data-active-click]', $slider).on('click', self.sliderClick);
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

			// resetting tabindex on backclick so that users
			// can still navigate through the rest of the menu
			if ( $(this).data('back-click') ){ 
				var items = $slider.find('.inner div');
				var tindex = 1;

				//reset tab index
				items.each( function( index ){
					var links = $(this).find('a');

					links.each( function( index ) {
						$(this).attr('tabindex', tindex );
						tindex = tindex + 1;
					}); // each links

				}); // each

				// refocusing on the first item
				$('[tabindex=1]').focus();

			} // if back-click

		} // sliderClick

	// migrates the menu visually to the desired location
	self.alterHeight = function(levelTo, animate) {
	// on bigger screens this is getting cut off, so add 2
	var toHeight = $slider.find('.level-' + levelTo).height() + 2;
		// @todo set all links to tabindex=-1 but skip the level-$levelTo item
        if(!animate) {
          $slider.height(toHeight);
        }
        else {
          $slider.animate({
            height: toHeight
          }, 250 );
        }

		/**
		 * This messes with the tab index of slider menu items. A bunch of it is offscreen
		 *  but it still gets a tabindex setting. Here we remove it for all but the
		 * active item.
		 */
		var items = $slider.find('.inner div');
		var activeClass = 'level-' + levelTo;
		  // loop through items removing tabindex
		  items.each( function( index ) {

			  var itemClass = $(this).attr('class');
			  //console.log(itemClass);

			  // loops through items removing tabindex from those not visible
			  if ( itemClass != activeClass ){
					$(this).find('a').attr( 'tabindex', -1 );
			  }
		  });

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
