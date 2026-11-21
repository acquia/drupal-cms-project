/**
 * @file
 * Trials countdown banner.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  // Initialize Qualified command queue so calls buffer until the SDK loads.
  (function (w, q) {
    w['QualifiedObject'] = q;
    w[q] = w[q] || function () {
      (w[q].q = w[q].q || []).push(arguments);
    };
  })(window, 'qualified');

  var endTimestamp = drupalSettings.trialsCountdown.endTimestamp;
  var bannerId = 'trials-countdown-banner';
  var heightVar = '--acquia-trials-banner-height';
  var intervalId;

  function getTimeLeft() {
    var now = Math.floor(Date.now() / 1000);
    return Math.max(0, endTimestamp - now);
  }

  function formatTimeLeft(seconds) {
    var days = Math.ceil(seconds / 86400);
    if (days > 1) {
      return days + ' days left in your trial.';
    }
    return 'Expires today.';
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

    // CTA triggers Qualified meeting scheduler experience.
    var cta = document.createElement('a');
    cta.href = '#';
    cta.onclick = function (e) {
      e.preventDefault();
      window.qualified('showExperience', 'experience-1777986568977');
    };
    cta.className = 'trials-countdown-cta';
    cta.textContent = 'Upgrade Now';

    var arrow = document.createElement('span');
    arrow.setAttribute('aria-hidden', 'true');
    arrow.textContent = ' ›';
    cta.appendChild(arrow);

    inner.appendChild(textWrap);
    inner.appendChild(cta);
    banner.appendChild(inner);
    document.body.insertBefore(banner, document.body.firstChild);

    return timeLeft;
  }

  /**
   * Publishes the banner's measured height as a CSS custom property on <html>.
   *
   * CSS rules keyed off this variable reserve space for the banner (body
   * padding) and offset the fixed admin chrome. Re-running this is the actual
   * fix for content being covered after a re-render: the CSS recomputes the
   * offsets from the up-to-date height regardless of what the theme/toolbar
   * did during its own re-render.
   */
  function writeBannerHeight() {
    var banner = document.getElementById(bannerId);
    var visible = banner && banner.style.display !== 'none';
    var height = visible ? banner.getBoundingClientRect().height : 0;
    document.documentElement.style.setProperty(heightVar, (height || 0) + 'px');
  }

  function updateBanner(timeLeftEl) {
    var seconds = getTimeLeft();
    if (seconds <= 0) {
      var banner = document.getElementById(bannerId);
      if (banner) {
        banner.style.display = 'none';
      }
      // Collapse the reserved space so the layout reverts cleanly.
      writeBannerHeight();
      clearInterval(intervalId);
      return;
    }
    timeLeftEl.textContent = formatTimeLeft(seconds);
  }

  Drupal.behaviors.acquiaTrialsCountdown = {
    attach: function () {
      // The banner is a singleton living on <body>; create it (and start the
      // countdown) exactly once even though attach() runs on every AJAX/
      // BigPipe response.
      once('acquia-trials-banner', 'body').forEach(function () {
        var timeLeftEl = createBanner();
        updateBanner(timeLeftEl);
        writeBannerHeight();

        // Keep the published height accurate as the banner reflows (e.g. text
        // wrapping at narrow widths) or the viewport changes.
        if (window.ResizeObserver) {
          var observer = new ResizeObserver(writeBannerHeight);
          observer.observe(document.getElementById(bannerId));
        }
        window.addEventListener('resize', writeBannerHeight);
        document.addEventListener('drupalViewportOffsetChange', writeBannerHeight);

        intervalId = setInterval(function () {
          updateBanner(timeLeftEl);
        }, 60000);
      });

      // Re-assert the height on every attach. After a form save the toolbar
      // re-renders and resets its position; re-publishing the height makes the
      // CSS offsets re-apply so content is never left under the banner.
      writeBannerHeight();
    }
  };

})(Drupal, drupalSettings, once);
