// Font size script from http://www.webupd8.org/2009/05/jquery-how-to-add-resize-text-font-size.html
(function($, Proud) {
  Proud.behaviors.font_size = {
    attach: function(context, settings) {
      // Reset Font Size
      var originalFontSize = $('body > .wrap p').css('font-size');
      $(".resetFont").click(function(){
        $('body > .wrap').css('font-size', originalFontSize);
        return false;
      });
      // Increase Font Size
      $(".increaseFont").click(function(){
        var currentFontSize = $('body > .wrap p').css('font-size');
        var currentFontSizeNum = parseFloat(currentFontSize, 10);
        var newFontSize = currentFontSizeNum*1.2;
        $('body > .wrap').css('font-size', newFontSize);
        return false;
      });
      // Decrease Font Size
      $(".decreaseFont").click(function(){
        var currentFontSize = $('body > .wrap p').css('font-size');
        var currentFontSizeNum = parseFloat(currentFontSize, 10);
        var newFontSize = currentFontSizeNum*0.8;
        $('body > .wrap').css('font-size', newFontSize);
        return false;
      });
    }
  };
})(jQuery, Proud);
