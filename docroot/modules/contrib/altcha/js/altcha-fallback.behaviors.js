(function (Drupal, once) {
  Drupal.behaviors.altchaFallback = {
    attach(context, settings) {
      once('altcha', 'altcha-widget', context).forEach((altcha_widget) => {
        altcha_widget.addEventListener('statechange', (e) => {
          if (e.detail.state === 'error') {
            altcha_widget.configure({'challengeurl': settings.altcha.fallbackChallengeUrl});

            // Respect auto-submit configuration when fallback is used.
            if (altcha_widget.getConfiguration().auto === 'onsubmit') {
              altcha_widget.addEventListener('verified', () => {
                const form = altcha_widget.closest('form');
                if (form) {
                  if (form.requestSubmit) {
                    form.requestSubmit();
                  }
                  // Fallback for older browser compatibility.
                  else {
                    form.submit();
                  }
                }
              }, {once: true});
            }

            // Start verification after any event listeners are
            // bound to the widget and the original submit handler
            // has finished processing the error state.
            setTimeout(() => {
              altcha_widget.verify();
            });
          }
        });
      });
    },
  };
})(Drupal, once);
