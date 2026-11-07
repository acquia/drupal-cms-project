// eslint-disable-next-line func-names
(function ($, Drupal, drupalSettings) {
  /**
   * Behaviors for tabs in entity edit forms.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behavior for tabs in entity edit forms.
   */
  Drupal.behaviors.entityDetailsSummaries = {
    attach: function attach(context) {
      const $context = $(context);

      // eslint-disable-next-line no-shadow,func-names
      const element = $context.find('.node-form-author, .media-form-author');
      if (element.length) {
        element.drupalSetSummary((authorContext) => {
          const $authorContext = $(authorContext);
          const $authorInput = $authorContext.find('.field--name-uid input');
          const $createdInput = $authorContext.find(
            '.field--name-created input',
          );

          let name = null;
          if ($authorInput.length) {
            const value = $authorInput.val();
            if (value) {
              try {
                const parsed = JSON.parse(value);
                if (Array.isArray(parsed) && parsed[0]?.label) {
                  name = parsed[0].label;
                }
              } catch (e) {
                console.error('Invalid JSON in $authorInput:', value, e);
              }
            }
          }

          let date = null;
          if ($createdInput.length) {
            date = $createdInput.val();
          }

          if (name && date) {
            return Drupal.t('By @name on @date', {
              '@name': name,
              '@date': date,
            });
          }
          if (name) {
            return Drupal.t('By @name', { '@name': name });
          }
          if (date) {
            return Drupal.t('Authored on @date', { '@date': date });
          }
        });
      }
    },
  };
})(jQuery, Drupal, drupalSettings);
