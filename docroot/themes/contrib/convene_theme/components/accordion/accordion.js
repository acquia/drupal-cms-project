(function (Drupal) {
  Drupal.behaviors.convene_themeAccordion = {
    attach(context) {
      const triggers = context.querySelectorAll('.accordion-item__trigger');

      triggers.forEach((trigger) => {
        if (trigger.hasAttribute('data-accordion-init')) return;
        trigger.setAttribute('data-accordion-init', 'true');

        const toggleAccordion = () => {
          const expanded = trigger.getAttribute('aria-expanded') === 'true';
          trigger.setAttribute('aria-expanded', !expanded);
        };

        trigger.addEventListener('click', toggleAccordion);

        trigger.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleAccordion();
          }
        });
      });
    },
  };
})(Drupal);
