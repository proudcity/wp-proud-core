
function googleTranslateElementInit() {
  new google.translate.TranslateElement({
    pageLanguage: 'en',
  }, 'google_translate_element');
  document.getElementsByClassName('goog-te-combo')[0].setAttribute('aria-labelledby', 'google-translate-label');
}