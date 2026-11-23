/**
 * @file
 * Defines Javascript behaviors for widget settings form.
 */

((Drupal) => {
  /**
   * Add the selected column class when one is selected on the widget
   * settings form.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *  Attaches the behavior for column classes.
   */
  Drupal.behaviors.customFieldFlexWidgetSettings = {
    attach(context) {
      const selects = context.querySelectorAll(
        '.custom-field-flex--widget-settings select',
      );
      Array.prototype.forEach.call(selects, (select) => {
        select.addEventListener('change', function selector() {
          const parent = this.closest('.custom-field-col');
          parent.className = parent.className.replace(
            /(^|\s)custom-field-col-.*?\S+/g,
            '',
          );
          parent.classList.add(`custom-field-col-${this.value}`);
        });
      });
    },
  };
})(Drupal);
