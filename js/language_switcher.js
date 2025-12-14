/**
 * @file
 * Simple language switcher that redirects to URL with language parameter.
 */
(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.sectionBannerLanguageSwitcher = {
    attach: function (context, settings) {
      var languageSelector = context.querySelector('.section-banner-language-selector');
      
      if (languageSelector && drupalSettings.sectionBanner && drupalSettings.sectionBanner.languageUrls) {
        languageSelector.addEventListener('change', function(e) {
          var selectedLang = e.target.value;
          var urls = drupalSettings.sectionBanner.languageUrls;
          
          if (urls[selectedLang]) {
            // Simple page redirect to URL with language parameter.
            window.location.href = urls[selectedLang];
          }
        });
      }
    }
  };

})(Drupal, drupalSettings);
