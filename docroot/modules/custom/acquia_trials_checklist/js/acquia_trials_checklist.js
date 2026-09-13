/**
 * @file
 * Acquia Trials Checklist interactivity.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acquiaTrialsChecklist = {
    attach: function (context) {
      once('acquia-trials-checklist', '.acquia-trials-checklist', context).forEach(function (container) {
        container.querySelectorAll('.checklist-item__checkbox').forEach(function (btn) {
          btn.addEventListener('click', function () {
            toggleItem(btn.dataset.itemKey, container);
          });
        });
      });
    }
  };

  function toggleItem(itemKey, container) {
    // Fetch CSRF token, then POST the toggle.
    fetch(Drupal.url('session/token'))
      .then(function (r) { return r.text(); })
      .then(function (token) {
        return fetch(Drupal.url('api/acquia-trials-checklist/toggle/' + itemKey), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': token
          }
        });
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        updateUI(container, data);
      });
  }

  function updateUI(container, data) {
    var items = container.querySelectorAll('.checklist-item');

    items.forEach(function (el) {
      var key = el.dataset.itemKey;
      var completed = data.completed[key] || false;
      el.classList.toggle('checklist-item--completed', completed);

      var startLink = el.querySelector('.checklist-item__start');
      if (completed && startLink) {
        startLink.remove();
      }
      else if (!completed && !startLink) {
        var link = document.createElement('a');
        link.className = 'checklist-item__start';
        link.textContent = Drupal.t('START');
        link.href = el.dataset.url || '#';
        el.appendChild(link);
      }
    });

    // Update progress bar.
    var fill = container.querySelector('.checklist-progress__fill');
    var pct = container.querySelector('.checklist-progress__percentage');
    if (fill) {
      fill.style.width = data.percentage + '%';
    }
    if (pct) {
      pct.textContent = data.percentage + '%';
    }
  }

})(Drupal, once);
