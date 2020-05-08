function googleTranslateElementInit() {
  for (var i = 0; i < window.google_translate_init_ids.length; i++) {
    var id = window.google_translate_init_ids[i];
    console.log(i, id);
    new google.translate.TranslateElement(
      {
        pageLanguage: "en",
      },
      "google_" + id + "_element"
    );
    document
      .getElementsByClassName("goog-te-combo")[0]
      .setAttribute("aria-labelledby", "google-" + id + "-label");
  }
}
