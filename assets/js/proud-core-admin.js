jQuery(document).ready(function ($) {

  (function ($) {
    const defaults = {
      selector: 'input[maxlength], textarea[maxlength]',
      warn: 10,
      danger: 2,
      counterClass: 'ccount',
      ariaLive: 'polite',
    };

    function ensureId($el) {
      if (!$el.attr('id')) {
        $el.attr('id', 'cc-' + Math.random().toString(36).slice(2, 9));
      }
      return $el.attr('id');
    }

    function renderOrGetCounter($el, opts) {
      // Reuse if already present right after the field
      let $next = $el.next('.' + opts.counterClass);
      if ($next.length) return $next;
      const $c = $('<span>', {
        class: opts.counterClass + ' cc-normal',
        'aria-live': opts.ariaLive
      });
      $el.after($c);
      // Link for accessibility
      const id = ensureId($el);
      const describedby = ($el.attr('aria-describedby') || '').trim();
      const newId = id + '-counter';
      $c.attr('id', newId);
      $el.attr('aria-describedby', (describedby ? describedby + ' ' : '') + newId);
      return $c;
    }

    function updateCounter($el, opts) {
      const max = parseInt($el.attr('maxlength'), 10);
      if (!Number.isFinite(max)) return;
      const val = $el.val() || '';
      const len = [...val].length; // handle surrogate pairs
      const remaining = max - len;

      const $counter = renderOrGetCounter($el, opts);
      $counter
        .text(len + ' / ' + max)
        .removeClass('cc-normal cc-warning cc-danger')
        .addClass(
          remaining <= opts.danger ? 'cc-danger' :
            remaining <= opts.warn ? 'cc-warning' :
              'cc-normal'
        );
    }

    function bindField($el, opts) {
      if ($el.data('cc-initialized')) return;
      $el.data('cc-initialized', true);

      // Initial paint
      updateCounter($el, opts);

      // Live updates
      $el.on('input.cc', () => updateCounter($el, opts));

      // In case value is changed programmatically (rare but possible)
      const obs = new MutationObserver(() => updateCounter($el, opts));
      obs.observe($el.get(0), { attributes: true, attributeFilter: ['value'] });
      $el.data('cc-obs', obs);
    }

    function scanAndBind(root = document, userOpts = {}) {
      const opts = { ...defaults, ...userOpts };
      $(root).query ? 0 : 0; // noop for clarity
      $(root).find
        ? $(root).find(opts.selector).each(function () { bindField($(this), opts); })
        : $(opts.selector, root).each(function () { bindField($(this), opts); });
    }

    // Public init
    $.charCounter = function (options = {}) {
      const opts = { ...defaults, ...options };

      // Bind existing
      scanAndBind(document, opts);

      // Observe future inserts
      if (!window.__ccObserver__) {
        window.__ccObserver__ = new MutationObserver((mutations) => {
          for (const m of mutations) {
            m.addedNodes && Array.prototype.forEach.call(m.addedNodes, (node) => {
              if (!(node instanceof Element)) return;
              if (node.matches && node.matches(opts.selector)) bindField($(node), opts);
              // Also scan descendants
              $(node).find && $(node).find(opts.selector).each(function () {
                bindField($(this), opts);
              });
            });
          }
        });
        window.__ccObserver__.observe(document.body, { childList: true, subtree: true });
      }

      // jQuery AJAX fallback (if DOM is replaced via success handlers)
      $(document).off('ajaxComplete.cc').on('ajaxComplete.cc', function () {
        scanAndBind(document, opts);
      });
    };

    // Auto-init on DOM ready with defaults
    $(function () { $.charCounter(); });

  })(jQuery);

  /** --- Demo: dynamically add a field via AJAX-like insert --- **/
  setTimeout(() => {
    // Simulate an AJAX response that injects a new field
    $('#dynamic-zone').html(
      '<label>Summary<textarea maxlength="120" placeholder="Dynamically added…"></textarea></label>'
    );
    // Nothing else needed — observer will auto-bind it
  }, 1500);

});
