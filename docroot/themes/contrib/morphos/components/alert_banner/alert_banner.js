/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/alert_banner/alert_banner.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

(function (Drupal, once) {
  Drupal.behaviors.alertBanner = {
    attach: function (context) {
      // Helper function to get dismissed alerts array from cookies
      const getDismissedAlerts = () => {
        const match = document.cookie.match(/alerts-dismissed=([^;]*)/);
        return match && match[1] ? match[1].split(',').filter(Boolean) : [];
      };

      // Process each alert banner once
      once('alert_banner', '.alert-banner', context).forEach(function (banner) {
        // Use data-alert-id
        const alertId = banner.getAttribute('data-alert-id');
        if (!alertId) return;

        const dismissed = getDismissedAlerts();

        // If this id hasn't been dismissed yet, show the alert
        if (!dismissed.includes(alertId)) {
          banner.classList.remove('hidden');

          // Find the close button inside this specific banner
          const closeButton = banner.querySelector('.alert-banner__btn-close');

          if (closeButton) {
            closeButton.addEventListener('click', function () {
              const currentDismissed = getDismissedAlerts();
              if (!currentDismissed.includes(alertId)) {
                currentDismissed.push(alertId);

                const date = new Date();
                // Set expiry date to 999 years from now
                date.setFullYear(date.getFullYear() + 999);

                document.cookie = `alerts-dismissed=${currentDismissed.join(',')}; expires=${date.toUTCString()}; path=/; SameSite=Lax`;
              }

              // Hide the banner
              banner.classList.add('hidden');
            });
          }
        }
      });
    },
  };
})(Drupal, once);
