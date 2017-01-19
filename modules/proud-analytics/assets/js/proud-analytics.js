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
  }
  $('.gform_wrapper form').bind('submit', function(e){
    anaylticsSubmission($(this).attr('id'));
  });
  // Emitted by Gravity Forms
  // Documentation: https://www.gravityhelp.com/documentation/article/gform_confirmation_loaded/#source-code
  $(document).bind('gform_confirmation_loaded', function(event, formId){
    anaylticsSubmission('gform_' + formId);
  });

  // Social clicks
  $('.share-dropdown ~ ul.dropdown-menu a').once('social-ga', function() {
    $(this).click(function(e) {
      if(e.target.href) {
        var label;
        if(e.target.href.match(/facebook\.com/i)) {
          label = 'Facebook';
        }
        else if(e.target.href.match(/twitter\.com/i)) {
          label = 'Twitter';
        }
        else if(e.target.href.match(/mailto\:/i)) {
          label = 'Email';
        }
        if(label) {
          ga('send', {
            hitType: 'event',
            eventCategory: 'Share',
            eventLabel: label,
            eventAction: window.location.protocol + '//' + window.location.hostname + window.location.pathname,
          });
        }
      }
    })
  });


}); //ready
      
      

