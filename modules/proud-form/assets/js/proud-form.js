(function($, Proud) {

  var $body = $('body');

  Proud.behaviors.iconpicker = { attach: function(context, settings) {
      $body.on('click', '.iconpicker', function(e) { 
        $(e.target).once('iconpicker', function() {
          $(this).iconpicker().data('iconpicker').show();
        })
      });
  }};

  Proud.behaviors.groups = { attach: function(context, settings) {
      $body.on('click', '.group-add-row', function(e) { 
        e.preventDefault();
        var draggable = $(e.target).siblings('[data-draggable]'),
            count = draggable.children().length,
            template = draggable.data('json_template')
                        .replace(/GROUP\_REPLACE\_KEY/g, count)
                        .replace(/GROUP\_REPLACE\_TITLE/g, count + 1);
        draggable.append(template);
      });

      $body.on('click', '.group-delete-row', function(e) {
        e.preventDefault();
        // Item being deleted
        var item = $(e.target).closest('div.panel');
        item.remove();
        // Recaculate weights
        Proud.behaviors.groups.recalulateWeight(item.parent());
      }); 
  }};

  Proud.behaviors.groups.recalulateWeight = function($draggable) {
      $.each($draggable.children(), function(id, child) {
        $($.find('input.group-weight', child)).val(id);
      });
  }

})(jQuery, Proud);