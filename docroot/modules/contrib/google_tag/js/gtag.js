window.dataLayer = window.dataLayer || [];
function gtag() {
  // gtag.js only processes Arguments objects, so a rest parameter (an Array)
  // must not be used here.
  // eslint-disable-next-line prefer-rest-params
  dataLayer.push(arguments);
}

(function (drupalSettings) {
  if (!drupalSettings.gtag) {
    return;
  }
  const config = drupalSettings.gtag;
  // A GTM-only container has no gtag.js tag ID. Its events are still pushed
  // to the data layer for GTM, but the gtag.js bootstrap is skipped: GTM
  // treats the 'js' command as a page load, ahead of the default consent.
  const hasTagId = Boolean(config.tagId);

  if (hasTagId) {
    gtag('js', new Date());
    // Drupal's global gtag ID. For more info: https://developers.google.com/tag-platform/devguides/gtag-integration
    gtag('set', 'developer_id.dMDhkMT', true);
  }

  if (config.consentMode === true) {
    gtag('consent', 'default', {
      ad_storage: 'denied',
      analytics_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
      wait_for_update: 500,
    });
  }

  if (hasTagId) {
    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${config.tagId}`;
    script.type = 'text/javascript';
    document.getElementsByTagName('head')[0].appendChild(script);

    const additionalConfigInfo = config.additionalConfigInfo || [];
    if (additionalConfigInfo.length === 0) {
      gtag('config', config.tagId);
    } else {
      gtag('config', config.tagId, additionalConfigInfo);
    }

    const otherIds = config.otherIds || [];
    otherIds.forEach((id) => gtag('config', id));
  }

  const events = config.events || [];
  events.forEach((event) => gtag('event', event.name, event.data));
})(drupalSettings);
