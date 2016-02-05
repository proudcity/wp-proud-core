(function($) {
  // Reset Font Size
  var $container = $('.wrap > .content'), 
      originalFontSize = $container.css('font-size');
  $(".resetFont").click(function(){
   $container.css('font-size', originalFontSize);
    return false;
  });
  // Increase Font Size
  $(".increaseFont").click(function(){
    var currentFontSize = $container.css('font-size'),
        currentFontSizeNum = parseFloat(currentFontSize, 10),
        newFontSize = currentFontSizeNum*1.2;
    $container.css('font-size', newFontSize);
    return false;
  });
  // Decrease Font Size
  $(".decreaseFont").click(function(){
    var currentFontSize = $container.css('font-size'),
        currentFontSizeNum = parseFloat(currentFontSize, 10),
        newFontSize = currentFontSizeNum*0.8;
    $container.css('font-size', newFontSize);
    return false;
  });
})(jQuery);