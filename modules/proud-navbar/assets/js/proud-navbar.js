(function($, Proud) {

  // Proudnav
  var proudNav = (function() {

    var layerOpen = {
      menu: false,
      search: false,
      answers: false,
    },
    layerClasses = {
      'menu': 'menu-nav-open',
      'search': 'search-active',
      'answers': 'active-311'
    },
    $body,
    self = {};

    // Closes overlays + menus
    // localLayers = ['menu', 'search']
    self.closeLayers = function(localLayers, callback) {
      // Default is all
      localLayers = localLayers || _.keys(layerOpen);
      var classes = [];
      _.map(localLayers, function(layer) {
        classes.push(layerClasses[layer]);
        layerOpen[layer] = false;
      });
      // Remove
      $body.removeClass(classes.join(' '));
      if(callback) {
        callback();
      }
    };

    // Closes overlays + menus
    self.openLayer = function(layer, callback) {
      setTimeout(function() {
        $body.addClass(layerClasses[layer]);
        layerOpen[layer] = true;
        $body.trigger('scroll');
        if(callback) {
          callback();
        }
      }, 50);
    };

    self.toggleMenu = function() {
      // Just close everything
      if(layerOpen.menu) {
        self.closeLayers();
        return;
      }
      else {
        self.closeLayers(['answers', 'search']);
        self.openLayer('menu');
      }
    };

    self.toggleOverlay = function(item, callback) {
      var thisLayer, otherLayer;
      switch(item) {
        case 'answers':
        case 'payments':
        case 'report':
        case 'status':
          thisLayer = 'answers';
          otherLayer = 'search';
          break;

        case 'search':
          thisLayer = 'search';
          otherLayer = 'answers';
          break;
      }

      // Close all
      if(layerOpen[thisLayer]) {
        self.closeLayers(null, callback);
      }
      else {
        // Close others, open ours
        self.closeLayers([otherLayer, 'menu'], callback);
        self.openLayer(thisLayer, callback);
      }
    };

    self.triggerOverlay = function(data, hash, classOverride) {
      if(classOverride) {
        layerClasses[data] = classOverride;
      }

      $body.trigger({
        type:     "proudNavClick",
        event:    data,
        hash:     hash,
        callback: function(open, scrollId, scrollOffset, forceClose, callback) {
          // Scrollto id
          if(scrollId) {
            var $scroll = $("#" + scrollId),
                scrollOffset = scrollOffset || 0,
                offset = $body.hasClass('admin-bar') ? 170 : 100;
            if($scroll.length) {
              $('html, body').animate({
                  scrollTop: $scroll.offset().top - (offset + scrollOffset)
              }, 200);
            }
          }
          // Oper layer
          if(open) {
            self.toggleOverlay(data, callback);
          }
          // Force close layers
          if(forceClose) {
            self.closeLayers(forceClose, callback);
          }
        }
      });
    };

    $(document).ready(function() {
      $body = $('body');

      // Listen for esc key
      $body.keydown(function(e) {  //keypress did not work with ESC;
        if (e.which == '27') {
          var closing = true;
          _.map(layerOpen, function(val) {
            if(val && closing) {
              self.closeLayers();
              closing = false;
            }
          });
        }
      }); 
    })

    return self;
  })();

  Proud.proudNav = proudNav;

  Proud.behaviors.proudNavbar = { attach: function(context, settings) {

    // Click top buttons
    $('[data-proud-navbar]').once('proud-navbar', function() {
      var $self = $(this);
      $self.click(function(e) {
        // Allow for link override
        if(!$self.data('click-external')) {
          e.preventDefault();
          var data = $self.data('proud-navbar');

          if(data) {
            proudNav.triggerOverlay(data, $self.data('proud-navbar-hash'));
          }
        }
      });
    });

    // mobile menu open
    $('#menu-button').once('proud-navbar', function() {
      $(this).click(function(e) {
        e.preventDefault();
        proudNav.toggleMenu();
      });
    });

    // close overlay
    $('#overlay-311-close, #overlay-search-close').once('proud-navbar', function() {
      $(this).click(function(e) {
        e.preventDefault();
        proudNav.closeLayers();
      });
    });

  }};
})(jQuery, Proud);

