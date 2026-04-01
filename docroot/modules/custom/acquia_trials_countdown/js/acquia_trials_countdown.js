/**
 * @file
 * Trials countdown banner.
 */
(function (Drupal, drupalSettings) {
  'use strict';

  var endTimestamp = drupalSettings.trialsCountdown.endTimestamp;
  var bannerId = 'trials-countdown-banner';
  var intervalId;
  var adminUISelectors = [
    '.top-bar.gin--navigation-top-bar',
    'aside#admin-toolbar'
  ];

  function getTimeLeft() {
    var now = Math.floor(Date.now() / 1000);
    return Math.max(0, endTimestamp - now);
  }

  function formatTimeLeft(seconds) {
    var days = Math.floor(seconds / 86400);
    if (days >= 1) {
      return days + (days === 1 ? ' day' : ' days') + ' left in your trial.';
    }
    var hours = Math.floor(seconds / 3600);
    if (hours >= 1) {
      return hours + (hours === 1 ? ' hour' : ' hours') + ' left in your trial.';
    }
    var minutes = Math.max(1, Math.floor(seconds / 60));
    return minutes + (minutes === 1 ? ' minute' : ' minutes') + ' left in your trial.';
  }

  function createBanner() {
    var banner = document.createElement('div');
    banner.id = bannerId;
    banner.setAttribute('role', 'status');

    var inner = document.createElement('div');
    inner.className = 'trials-countdown-inner';

    // Icon + text.
    var textWrap = document.createElement('div');
    textWrap.className = 'trials-countdown-text';

    var icon = document.createElement('span');
    icon.className = 'trials-countdown-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0zm-7-4a1 1 0 1 0-2 0v4a1 1 0 0 0 .293.707l2.828 2.829a1 1 0 1 0 1.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>';

    var timeLeft = document.createElement('span');
    timeLeft.className = 'trials-countdown-time';

    var desc = document.createElement('span');
    desc.className = 'trials-countdown-desc';
    desc.textContent = ' Maintain access to your Drupal site and unlock full Acquia Cloud Platform features after your trial ends.';

    textWrap.appendChild(icon);
    textWrap.appendChild(timeLeft);
    textWrap.appendChild(desc);

    // CTA link.
    var cta = document.createElement('a');
    cta.href = 'https://www.acquia.com/upgrade';
    cta.className = 'trials-countdown-cta';
    cta.textContent = 'Upgrade Now';
    cta.target = '_blank';
    cta.rel = 'noopener';

    var arrow = document.createElement('span');
    arrow.setAttribute('aria-hidden', 'true');
    arrow.textContent = ' \u203A';
    cta.appendChild(arrow);

    inner.appendChild(textWrap);
    inner.appendChild(cta);
    banner.appendChild(inner);
    document.body.insertBefore(banner, document.body.firstChild);

    return timeLeft;
  }

  function offsetAdminUI() {
    var banner = document.getElementById(bannerId);
    var height = banner && banner.offsetHeight ? banner.offsetHeight : 0;
    var value = height ? height + 'px' : '';
    adminUISelectors.forEach(function (selector) {
      var el = document.querySelector(selector);
      if (el) {
        el.style.marginTop = value;
      }
    });
  }

  function updateBanner(timeLeftEl) {
    var seconds = getTimeLeft();
    if (seconds <= 0) {
      timeLeftEl.parentElement.closest('#' + bannerId) &&
        (document.getElementById(bannerId).style.display = 'none');
      offsetAdminUI();
      clearInterval(intervalId);
      return;
    }
    timeLeftEl.textContent = formatTimeLeft(seconds);
  }

  // Initialize once the DOM is ready.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    if (document.getElementById(bannerId)) {
      return;
    }
    var timeLeftEl = createBanner();
    updateBanner(timeLeftEl);
    offsetAdminUI();

    // Recalculate offset on resize in case banner height changes.
    window.addEventListener('resize', offsetAdminUI);

    intervalId = setInterval(function () {
      updateBanner(timeLeftEl);
    }, 60000);
  }

})(Drupal, drupalSettings);
