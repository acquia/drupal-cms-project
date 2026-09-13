Drupal.behaviors.selectAutocomplete = {
  attach(context) {
    const element = context.querySelector(
      'select#edit-major-works-neighbourhood',
    );

    if (!element) {
      return;
    }

    accessibleAutocomplete.enhanceSelectElement({
      selectElement: element,
      onConfirm: (val) => {
        const form = element.closest('form');
        for (let i = 0; i < element.options.length; i += 1) {
          if (element.options[i].text === val) {
            element.selectedIndex = i;
            break;
          }
        }
        form.submit();
      },
    });
  },
};
