(function($, Proud) {

  var $body = $('body');

  // Handle accordion toggle clicks (Bootstrap 5 or fallback)
  Proud.behaviors.accordionToggle = {
    attach: function(context, settings) {
      // Delegate click handler for accordion toggles
      $body.once('accordion-toggle-handler', function() {
        $(this).on('click', '.panel-title a, .panel-heading a, [data-bs-toggle="collapse"], [data-toggle="collapse"]', function(e) {
          e.preventDefault();
          e.stopPropagation();

          var $trigger = $(this);
          var targetSelector = $trigger.attr('href') || $trigger.data('bs-target') || $trigger.data('target');
          var $target = $(targetSelector);

          // Check for Bootstrap 5 (could be on window or global)
          var bs = (typeof bootstrap !== 'undefined') ? bootstrap : window.bootstrap;

          if ($target.length && bs && bs.Collapse) {
            // Use Bootstrap 5 Collapse
            var collapseInstance = bs.Collapse.getOrCreateInstance($target[0], {
              toggle: false
            });
            collapseInstance.toggle();
          } else if ($target.length) {
            // Fallback: manually toggle the collapse class
            var isOpen = $target.hasClass('show') || $target.hasClass('in');

            if (isOpen) {
              $target.removeClass('show in');
              $trigger.addClass('collapsed');
              $trigger.attr('aria-expanded', 'false');
            } else {
              $target.addClass('show in');
              $trigger.removeClass('collapsed');
              $trigger.attr('aria-expanded', 'true');
            }
          }
        });
      });
    }
  };

  Proud.behaviors.iconpicker = {
    reRunAjax: true, // runs the code below after a panel loads
    attach: function(context, settings) {

    /* testing var for icons
    var icons = [ 'fa-regular fa-address-card', 'fa-brands fa-airbnb'];
    */

    // runs the iconpicker on pageload
    $('input.iconpicker').once('fontIconPicker', function(){
        $(this).fontIconPicker({
          theme: 'proudcity-iconpicker',
          source: ProudFA.icons
        });
    });

    // attaches to a trigger to run iconpicker once we add a row
    $body.on('proudGroupAddRow', function(){
      $('input.iconpicker').once('fontIconPicker', function(){
          $(this).fontIconPicker({
            theme: 'proudcity-iconpicker',
            source: ProudFA.icons
          });
      });
    });

  }};

  Proud.behaviors.draggableCheckboxes = {
    reRunAjax: true,
    attach: function(context, settings) {
      // Init Dragula
      $('[data-draggable-checkboxes]').once('drag-checkbox', function() {
        dragula([this], {
          moves: function (el, container, handle) {
            return handle.className.indexOf('handle') > 0;
          }
        });
      });
    }
  };

  Proud.behaviors.draggableGroups = {
    reRunAjax: true,
    attach: function(context, settings) {
      // Init drag
      $('[data-draggable-group]').once('panelsopen', function() {
        var that = this;
        dragula([that], {
          moves: function (el, container, handle) {
            return handle.className.indexOf('handle') > 0;
          }
        }).on('drop', function (el) {
          Proud.behaviors.groups.recalulateWeight($(that));
        });
      });
    }
  };

  Proud.behaviors.groups = {
    attach: function(context, settings) {
      // Add row button
      $body.on('click', '.group-add-row', function(e) {
        e.preventDefault();
        var draggable = $(e.target).siblings('[data-draggable-group]'),
            count = draggable.children().length,
            template = draggable.data('json_template')
                        .replace(/GROUP\_REPLACE\_KEY/g, count)
                        .replace(/GROUP\_REPLACE\_TITLE/g, count + 1);
        draggable.append(template);

        // Initialize collapse on the newly added panel
        var $newPanel = draggable.children().last();
        var $collapseTarget = $newPanel.find('.panel-collapse');
        var $newTrigger = $newPanel.find('.panel-title a');

        // Check for Bootstrap 5 (could be on window or global)
        var bs = (typeof bootstrap !== 'undefined') ? bootstrap : window.bootstrap;

        if ($collapseTarget.length && bs && bs.Collapse) {
          var collapseInstance = bs.Collapse.getOrCreateInstance($collapseTarget[0], {
            toggle: false
          });
          collapseInstance.show();
        } else {
          // Fallback: manually expand the new panel
          $collapseTarget.addClass('show in');
          $newTrigger.removeClass('collapsed').attr('aria-expanded', 'true');
        }

        // Trigger event so other behaviors can initialize on new row
        $body.trigger('proudGroupAddRow');
      });

      // Delete row button
      $body.on('click', '.group-delete-row', function(e) {
        e.preventDefault();
        // Item being deleted
        var item = $(e.target).closest('div.panel');
        item.remove();
        // Recaculate weights
        Proud.behaviors.groups.recalulateWeight(item.parent());
      });
    }
  };

  Proud.behaviors.groups.recalulateWeight = function($draggable) {
    $.each($draggable.children(), function(id, child) {
      $($.find('input.group-weight', child)).val(id);
    });
  }

})(jQuery, Proud);