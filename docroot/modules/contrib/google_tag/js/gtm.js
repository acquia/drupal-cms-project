(function (drupalSettings) {
  const dl = drupalSettings.gtm
    ? drupalSettings.gtm.settings.data_layer
    : 'dataLayer';
  window[dl] = window[dl] || [];

  if (!drupalSettings.gtm) {
    return;
  }
  const config = drupalSettings.gtm;

  window[dl].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
  const gtmSettings = config.settings;
  if (gtmSettings.include_classes === true) {
    // An empty allowlist blocks every tag, so only push non-empty lists.
    const classes = {};
    if (gtmSettings.allowlist_classes?.length) {
      classes['gtm.allowlist'] = gtmSettings.allowlist_classes;
    }
    if (gtmSettings.blocklist_classes?.length) {
      classes['gtm.blocklist'] = gtmSettings.blocklist_classes;
    }
    if (Object.keys(classes).length) {
      window[dl].push(classes);
    }
  }

  let gtmEnvironment = '';
  if (gtmSettings.include_environment === true) {
    const gtmAuth = gtmSettings.environment_token ?? '';
    const gtmPreview = gtmSettings.environment_id ?? '';
    gtmEnvironment = `&gtm_auth=${gtmAuth}&gtm_preview=${gtmPreview}&gtm_cookies_win=x`;
  }
  config.tagIds.forEach(function (tagId) {
    const script = document.createElement('script');
    script.async = true;
    const dLink = dl !== 'dataLayer' ? `&l=${dl}` : '';
    script.src = `https://www.googletagmanager.com/gtm.js?id=${tagId}${gtmEnvironment}${dLink}`;
    script.type = 'text/javascript';
    document.getElementsByTagName('head')[0].appendChild(script);
  });
})(drupalSettings);
