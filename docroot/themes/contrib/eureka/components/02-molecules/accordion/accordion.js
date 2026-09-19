/**
 * @file Accordion JS
 *
 * JS Behaviors for toggling accordion content.
 */
Drupal.behaviors.EurekaAccordion = {
  attach(context) {
    const accordions = once('EurekaAccordion', '.js-accordion', context);

    // If there are no accordions in the context, finish the execution.
    if (!accordions.length) return;

    // Loop over all the accordion objects
    accordions.forEach((accordion) => {
      const heading = accordion.querySelector('.js-accordion-heading');
      const content = accordion.querySelector('.js-accordion-content');
      const headingText = heading.querySelector('button span');
      const originalHeading = headingText.textContent;
      const expandedHeading =
        headingText.dataset.expandedHeading || originalHeading;

      // If there is no heading, content or the JS is already applied, finish the execution
      if (!heading || !content) return;

      // Add aria-expanded="false" to the button & 'hidden' attribute to the content wrapper
      heading.querySelector('button').setAttribute('aria-expanded', 'false');
      content.setAttribute('hidden', 'hidden');

      // Click handler for the accordions
      const handleClick = (e) => {
        const target = e.currentTarget;
        const button = target.querySelector('button');
        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', !isExpanded);
        if (!isExpanded) {
          content.removeAttribute('hidden');
          heading.classList.add('is-active');
          accordion.classList.add('is-open');
          headingText.textContent = expandedHeading;
        } else {
          content.setAttribute('hidden', true);
          heading.classList.remove('is-active');
          accordion.classList.remove('is-open');
          headingText.textContent = originalHeading;
        }
      };

      // Adds the click handler to the accordion
      heading.addEventListener('click', handleClick);
    });
  },
};
