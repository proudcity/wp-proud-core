function googleTranslateElementInit() {
  if (!window.google_translate_init_ids) {
    return;
  }
  for (var i = 0; i < window.google_translate_init_ids.length; i++) {
    var id = window.google_translate_init_ids[i];
    new google.translate.TranslateElement(
      {
        pageLanguage: 'en',
      },
      'google_' + id + '_element'
    );
  }
  var els = document.getElementsByClassName('goog-te-combo');
  [].forEach.call(els, function (element, index) {
    element.setAttribute(
      'aria-labelledby',
      'google-' + window.google_translate_init_ids[index] + '-label'
    );
  });
}
