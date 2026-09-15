(function (Drupal, once) {
  Drupal.behaviors.aiProviderAmazeeioKeysTable = {
    attach(context) {
      function syncSelectedStates(table) {
        table.querySelectorAll('tbody tr').forEach(function (tr) {
          const radio = tr.querySelector('input[type="radio"]');
          if (radio && radio.checked) {
            tr.classList.add('selected');
          } else {
            tr.classList.remove('selected');
          }
        });
      }

      const tables = once('ai-keys-table-init', '.ai-keys-table', context);
      tables.forEach(function (table) {
        // Initial sync
        syncSelectedStates(table);

        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(function (row) {
          row.addEventListener('click', function (e) {
            // Only trigger if we aren't clicking the actual input or a label
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'LABEL') {
              const radio = row.querySelector('input[type="radio"]');
              if (radio && !radio.checked) {
                radio.checked = true;
                // Native change events don't bubble, but we manually sync state anyway
                syncSelectedStates(table);
              }
            }
          });
        });

        // Also handle keyboard navigation or direct clicks on radio buttons
        const radios = table.querySelectorAll('input[type="radio"]');
        radios.forEach(function (radio) {
          radio.addEventListener('change', function () {
            syncSelectedStates(table);
          });
        });
      });
    },
  };
})(Drupal, once);
