jQuery(document).ready(function($) {

  // Widget click
  var anaylticsWidgetClick = function(e) {
    var activeClass = 'btn-primary';
    e.preventDefault();
    var title = $(this).attr('data-title') ? $(this).attr('data-title') : $('h1.entry-title').length ? $('h1.entry-title').text() : document.title;
    ga('send', {
      hitType: 'event',
      eventCategory: 'Score',
      eventLabel: title,
      eventAction: window.location.href,
      eventValue: jQuery(this).hasClass(activeClass) ? -5 : +5
    });

    if (!$(this).hasClass(activeClass)) { 
      ga('send', {
        hitType: 'event',
        eventCategory: 'Heart',
        eventLabel: title,
        eventAction: window.location.href,
        eventValue: 1
      });
    }

    $(this).toggleClass(activeClass);
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
    ga('send', {
      hitType: 'event',
      eventCategory: 'Score',
      eventLabel: 'Submission',
      eventAction: window.location.href,
      eventValue: 5
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


  // Track events: AddToCalendar click
  var anaylticsCalendarShare = function(title, slug) {
    ga('send', {
      hitType: 'event',
      eventCategory: 'AddEvent',
      eventLabel: title,
      eventAction: slug,
      eventValue: 1
    });
    ga('send', {
      hitType: 'event',
      eventCategory: 'Score',
      eventLabel: 'AddEvent',
      eventAction: slug,
      eventValue: 5
    });
  }
  if ($('.addtocalendar').length) {
    setTimeout(function(){
      $('.atcb-item-link').bind('click', function(e){
        var $parent = $(this).parents('.addtocalendar');
        anaylticsCalendarShare($parent.attr('data-title'), $parent.attr('data-slug'));
      });
    }, 1000);
  }
  


}); //ready
      
      

