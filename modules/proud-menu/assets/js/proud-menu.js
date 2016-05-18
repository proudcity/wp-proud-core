(function($, Proud) {
  // Back button clicks
  $('a[data-back-click]').on('click', function(event) {
    event.preventDefault();
    var level = $(this).data('back-click') || 1;
    // Remove other active
    $(this).parents('.menu-slider').removeClass(function (index, css) {
        return (css.match (/level-[0-9]-active/g) || []).join(' ');
    }).addClass('level-' + level + '-active');
  });

  // Parent-to-child clicks
  $('a[data-active-click]').on('click', function(event) {
    event.preventDefault();
    var level = $(this).data('active-click');
    // Remove other active
    $(this).parents('.menu-slider').removeClass(function (index, css) {
        return (css.match (/level-[0-9]-active/g) || []).join(' ');
    }).addClass('level-' + level + '-active');
  });

})(jQuery, Proud);