# jquery.equalizeHeight

Requires https://github.com/alexanderdickson/waitForImages.  Not included in current script.

Simple jquery height equalizer, sets a watcher on a parent and children selectors.  Default children are identified by ```[data-equalize-height]``` attribute.

### Run default height equalizer
```  
$('[data-equalizer]').equalizeHeight();
```

### Run default height equalizer with a media query requirement
```  
$('[data-equalizer]').equalizeHeight('(min-width:600px)');
```

### Run height equalizer for custom children selector
```
$('.card-columns-equalize').equalizeHeight(false, '.card');
```  