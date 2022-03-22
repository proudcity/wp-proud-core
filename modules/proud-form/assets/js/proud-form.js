(function($, Proud) {

  var $body = $('body');

  Proud.behaviors.iconpicker = { attach: function(context, settings) {
    $body.on('click', 'input.iconpicker', function(e) {
      $(e.target).once('fontIconPicker', function() {
        //$(this).fontIconPicker(iconSettings).data('iconpicker').show();
        console.log('fonticonpicker');
      })
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