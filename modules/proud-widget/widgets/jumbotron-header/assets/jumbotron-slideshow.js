/**
 * Pause/play control for the Hero unit slideshow (WCAG 2.2.2).
 *
 * Relies on Bootstrap 3 carousel internals ($el.data('bs.carousel').options).
 * Setting options.interval to false is the only lever that stops every Bootstrap
 * code path (mouseleave, clicking the active indicator, the window.load init)
 * from restarting the timer. Revisit if the theme ever moves off Bootstrap 3.
 */
(function ($) {
  'use strict';

  $(function () {
    if (!$.fn.carousel || !$.fn.carousel.Constructor) {
      return;
    }

    var mq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    $('.jumbotron-slideshow').each(function () {
      var $el = $(this);
      var $button = $el.children('.carousel-pause-toggle');
      if (!$button.length) {
        return;
      }

      var interval = $el.data('interval');
      if (undefined === interval) {
        interval = $.fn.carousel.Constructor.DEFAULTS.interval;
      }

      var reduced = !!(mq && mq.matches);
      $el.carousel({ interval: reduced ? false : interval });
      var inst = $el.data('bs.carousel');

      var playing = !reduced; // what the visitor asked for
      var focusInside = false;
      var $icon = $button.find('.fa');
      var $label = $button.find('.sr-only');
      var $inner = $el.find('.carousel-inner');

      function apply() {
        if (playing && !focusInside) {
          inst.options.interval = interval;
          inst.cycle();
        } else {
          inst.options.interval = false;
          inst.pause();
        }

        $icon.toggleClass('fa-pause', playing).toggleClass('fa-play', !playing);
        $label.text($button.attr(playing ? 'data-label-pause' : 'data-label-play'));
        // Announce manual slide changes only while not auto-rotating.
        $inner.attr('aria-live', playing ? 'off' : 'polite');
      }

      $button.on('click', function () {
        playing = !playing;
        // Pressing Play is an explicit request to rotate, so it overrides the
        // focus pause even though focus is still on this button (APG carousel).
        // Focus leaving and re-entering the carousel pauses it again.
        if (playing) {
          focusInside = false;
        }
        apply();
      });

      $el.on('focusin', function () {
        focusInside = true;
        apply();
      });
      $el.on('focusout', function (e) {
        if (e.relatedTarget && $.contains($el[0], e.relatedTarget)) {
          return;
        }
        focusInside = false;
        apply();
      });

      if (mq) {
        var onChange = function (e) {
          if (e.matches) {
            playing = false;
            apply();
          }
        };
        if (mq.addEventListener) {
          mq.addEventListener('change', onChange);
        } else if (mq.addListener) {
          mq.addListener(onChange);
        }
      }

      apply();
      $button.prop('hidden', false);
    });
  });
})(jQuery);
