/**
 * @file
 * Expand/collapse details for custom field tables with auto-collapse.
 */
(function (Drupal, once) {
  Drupal.behaviors.customFieldExpandDetails = {
    attach(context, settings) {
      const autoCollapseSettings = settings?.custom_field?.auto_collapse || {};

      // --- Attach per-table behavior ---
      once('custom-field-table', 'table.custom-field-multi', context).forEach(
        (table) => {
          // Determine if auto-collapse is enabled
          let shouldAutoCollapse = false;
          const attrAuto = table.dataset.autoCollapse;
          if (attrAuto === '1') {
            shouldAutoCollapse = true;
          } else {
            const fieldClass = Array.from(table.classList).find((c) =>
              c.startsWith('field-table--'),
            );
            if (fieldClass) {
              const key = fieldClass.replace('field-table--', '');
              shouldAutoCollapse = !!autoCollapseSettings[key];
            }
          }

          // --- Expand / Collapse All button ---
          const btn = table.querySelector('.expand-all-details');
          if (btn && !btn.dataset.expandAttached) {
            btn.dataset.expandAttached = '1';
            btn.addEventListener('click', (e) => {
              e.preventDefault();

              const details = Array.from(
                table.querySelectorAll('details.custom-field-collapsible'),
              );
              if (!details.length) return;

              const allOpen = details.every((d) => d.open);
              details.forEach((d) => {
                d.open = !allOpen;
              });

              // Update button text
              btn.textContent = allOpen
                ? Drupal.t('Edit all')
                : Drupal.t('Collapse all');
            });
          }

          // --- Attach auto-collapse only to our collapsible details ---
          const attachDetailsBehavior = (detailsEl) => {
            if (detailsEl.dataset.autoCollapseAttached) return;
            detailsEl.dataset.autoCollapseAttached = '1';

            const summary = detailsEl.querySelector('summary');
            if (!summary) return;

            summary.addEventListener('click', () => {
              // Only handle if auto-collapse is enabled
              if (!shouldAutoCollapse) return;

              // Wait for the browser to toggle open/closed first
              setTimeout(() => {
                // Ignore if user is closing an already-open one
                if (!detailsEl.open) return;

                // Collapse only *sibling* custom-field-collapsible details in this table
                table
                  .querySelectorAll('details.custom-field-collapsible')
                  .forEach((other) => {
                    if (other !== detailsEl && other.open) {
                      other.open = false;
                    }
                  });
              });
            });
          };

          // Attach behavior for existing target details
          once(
            'custom-field-details',
            'details.custom-field-collapsible',
            table,
          ).forEach(attachDetailsBehavior);
        },
      );
    },
  };
})(Drupal, once);
