/**
 * @file
 *
 * Registers localized ALTCHA labels through Drupal config translations.
 */
(function (Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.altchaDrupalTranslations = {
    attach() {
      const i18n = globalThis.altchaI18n;
      if (!i18n) {
        return;
      }

      const currentLanguage = drupalSettings?.altcha.i18n?.currentLanguage || null;
      const overriddenLabels = drupalSettings?.altcha.i18n?.labels || {};
      if (currentLanguage && overriddenLabels) {
        const existingLabels = typeof i18n.get === 'function' ? i18n.get(currentLanguage) || {} : {};
        i18n.set(currentLanguage, {
          ...existingLabels,
          ...overriddenLabels
        });
      }

      globalThis.altchaI18n = i18n;
    }
  };

})(Drupal, drupalSettings);
