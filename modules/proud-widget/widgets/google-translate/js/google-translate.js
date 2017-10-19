
function googleTranslateElementInit() {
  new google.translate.TranslateElement({
    pageLanguage: 'en',
  }, 'google_translate_element');

  jQuery('#google_translate_element')
    .attr('id', 'google_translate_element-select')
    .prepend('<label for="google_translate_element-select" class="sr-only">Translate language select</label>');
}