(function($, Proud) {

  var $body = $('body');

  Proud.behaviors.iconpicker = { attach: function(context, settings) {
    var iconSettings = [];
    // We have custom icons ?
    if(settings.proud_form
    && settings.proud_form.iconpicker 
    && settings.proud_form.iconpicker.icons 
    && settings.proud_form.iconpicker['icon-prefix']) {
      iconSettings['icons'] = $.merge(settings.proud_form.iconpicker.icons, $.iconpicker.defaultOptions.icons);
      iconSettings['fullClassFormatter'] = function(val) {
        if(val.match(/^fa-/)){
            return 'fa '+val;
        }else{
            return settings.proud_form.iconpicker['icon-prefix'] + ' ' +val;
        }
      };
    }
    $body.on('click', '.iconpicker', function(e) { 
      $(e.target).once('iconpicker', function() {
        $(this).iconpicker(iconSettings).data('iconpicker').show();
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