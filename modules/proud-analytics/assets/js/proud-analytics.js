jQuery(document).ready(function($) {

  // Email (mailto:) link click
  var anaylticsMailto = function(email) {
    ga('send', {
      hitType: 'event',
      eventCategory: 'Score',
      eventLabel: 'Email',
      eventAction: email,
      eventValue: 5
    });
  }
  $('a[href^="mailto:"]').bind('click', function(e){
    anaylticsMailto($(this).attr('href').replace('mailto:', ''));
  });

  // Phone number (tel:) link click
  var anaylticsPhone = function(phone) {
    ga('send', {
      hitType: 'event',
      eventCategory: 'Score',
      eventLabel: 'Phone',
      eventAction: phone,
      eventValue: 10
    });
  }
  $('a[href^="tel:"]').bind('click', function(e){
    anaylticsPhone($(this).attr('href').replace('tel:', ''));
  });

  // Widget click
  var anaylticsWidgetClick = function(e) {
    var activeClass = 'btn-primary';
    e.preventDefault();
    var title = $(this).attr('data-title') ? $(this).attr('data-title') : $('h1.entry-title').length ? $('h1.entry-title').text() : document.title;
    ga('send', {
      hitType: 'event',
      eventCategory: 'Score',
      eventLabel: 'Heart',
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
  var submittedBy;
  var anaylticsSubmission = function(title, action) {
    action = window.location.protocol + '//' + window.location.host + action;
    ga('send', {
      hitType: 'event',
      eventCategory: 'Submission',
      eventLabel: title,
      eventAction: action,
      eventValue: 1
    });
    ga('send', {
      hitType: 'event',
      eventCategory: 'Score',
      eventLabel: 'Submission',
      eventAction: action,
      eventValue: 5
    });
  }
  $('.gform_wrapper form').once('proud-analytics', function() {
    var $self = $(this);
    // Process buttons to deal with multistep forms
    $.each($self.find('input[type="button"]'), function(key, button) {
      $(button).bind('click', function(e) {
        submittedBy = this.value;
      });
    });
    $self.bind('submit', function(e){
      // Allow bound click to complete
      setTimeout(function() {
        if(submittedBy !== 'Next' && submittedBy !== 'Previous') {
          anaylticsSubmission($self.attr('id'), $self.attr('action'));
        }
      }, 0);
    });
  });
  // Emitted by Gravity Forms
  // Documentation: https://www.gravityhelp.com/documentation/article/gform_confirmation_loaded/#source-code
  $(document).bind('gform_confirmation_loaded', function(event, formId){
    anaylticsSubmission('gform_' + formId);
  });


  // Social clicks
  var analyticsSocialClick = function(e) {
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
      ga('send', {
        hitType: 'event',
        eventCategory: 'Score',
        eventLabel: 'Share',
        eventAction: window.location.protocol + '//' + window.location.hostname + window.location.pathname,
        eventValue: 5
      });
    }
  }
  $('.share-dropdown ~ ul.dropdown-menu a').once('social-ga', function() {
    $(this).bind('click', analyticsSocialClick);
  });

  // AddToCalendar click
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
      
      

