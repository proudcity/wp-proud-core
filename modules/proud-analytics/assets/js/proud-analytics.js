jQuery(document).ready(function($) {

  // Widget click
  var anaylticsWidgetClick = function(e) {
    e.preventDefault();
    var title = $(this).attr('data-title') ? $(this).attr('data-title') : $('h1.entry-title').length ? $('h1.entry-title').text() : document.title;
    ga('send', {
      hitType: 'event',
      eventCategory: 'Score',
      eventLabel: title,
      eventAction: window.location.href,
      eventValue: jQuery(this).hasClass('active') ? -5 : +5
    });

    if (!$(this).hasClass('active')) { 
      ga('send', {
        hitType: 'event',
        eventCategory: 'Heart',
        eventLabel: title,
        eventAction: window.location.href,
        eventValue: 1
      });
    }

    $(this).toggleClass('active');
  }

  $('.proudscore-widget').bind('click', anaylticsWidgetClick);


  // Gravity form submission
  var anaylticsSubmission = function(title) {
    ga('send', {
      hitType: 'event',
      eventCategory: 'Submission',
      eventLabel: title,
      eventAction: window.location.href,
      eventValue: 1
    });
  }
  $('.gform_wrapper form').bind('submit', function(e){
    anaylticsSubmission($(this).attr('id'));
  });
  // Emitted by Gravity Forms
  // Documentation: https://www.gravityhelp.com/documentation/article/gform_confirmation_loaded/#source-code
  $(document).bind('gform_confirmation_loaded', function(event, formId){
    anaylticsSubmission('gform_' + formId);
  });


}); //ready
      
      

