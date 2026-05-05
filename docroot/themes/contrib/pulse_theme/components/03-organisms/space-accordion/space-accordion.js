// Select all accordion title elements
const titles = document.querySelectorAll('.accordion .accordion-item-inner .ac-title');

titles.forEach((title) => {
  title.addEventListener('click', function handleAccordionClick(event) {

    event.preventDefault();
    console.log('Accordion title clicked:', event.currentTarget);
    const titleElement = event.currentTarget;
    const itemInner = titleElement.closest('.accordion-item-inner');

    if (itemInner.classList.contains('opened')) {
      itemInner.classList.remove('opened');
      itemInner.classList.add('closed');
      const accessibilityText = titleElement.querySelector('.ac-accessibilty-txt');
      if (accessibilityText) {
        accessibilityText.textContent = 'click to close accordion';
      }
    } else {
      // Close other accordion items
      const siblingItems = itemInner.closest('.accordion-item').parentNode.children;
      Array.from(siblingItems).forEach((sibling) => {
        const siblingInner = sibling.querySelector('.accordion-item-inner');
        if (siblingInner && siblingInner !== itemInner) {
          siblingInner.classList.remove('opened');
          siblingInner.classList.add('closed');
          const siblingAccessibilityText = sibling.querySelector('.ac-title .ac-accessibilty-txt');
          if (siblingAccessibilityText) {
            siblingAccessibilityText.textContent = 'click to open accordion';
          }
        }
      });

      // Toggle the clicked accordion item
      itemInner.classList.toggle('opened');
      itemInner.classList.remove('closed');

      const accessibilityText = titleElement.querySelector('.ac-accessibilty-txt');
      if (accessibilityText) {
        accessibilityText.textContent = 'click to open accordion';
      }
    }
  });
});
