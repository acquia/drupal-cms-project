(function (Drupal, once) {
  Drupal.behaviors.serviceTwHeader = {
    attach(context) {
      once('service-tw-header', '[data-stwl-toggle]', context).forEach(
        (btn) => {
          const targetId = btn.getAttribute('data-stwl-toggle');
          const target = document.getElementById(targetId);
          if (!target) return;

          btn.addEventListener('click', () => {
            const nowOpen = btn.getAttribute('aria-expanded') !== 'true';
            btn.setAttribute('aria-expanded', nowOpen);
            target.setAttribute('aria-hidden', !nowOpen);

            if (targetId === 'mobile-menu') {
              target.classList.toggle('is-open', nowOpen);
              btn
                .querySelector('.stwl-icon-open')
                ?.classList.toggle('hidden', nowOpen);
              btn
                .querySelector('.stwl-icon-close')
                ?.classList.toggle('hidden', !nowOpen);
              document.body.classList.toggle('overflow-hidden', nowOpen);
            } else {
              target.classList.toggle('hidden');
            }
          });
        },
      );

      once('service-tw-header-esc', 'body', context).forEach(() => {
        document.addEventListener('keydown', (e) => {
          if (e.key !== 'Escape') return;
          const mobileMenu = document.getElementById('mobile-menu');
          const mobileBtn = document.querySelector(
            '[data-stwl-toggle="mobile-menu"]',
          );
          if (!mobileMenu?.classList.contains('is-open')) return;

          mobileMenu.classList.remove('is-open');
          mobileMenu.setAttribute('aria-hidden', 'true');
          mobileBtn.setAttribute('aria-expanded', 'false');
          mobileBtn
            .querySelector('.stwl-icon-open')
            ?.classList.remove('hidden');
          mobileBtn.querySelector('.stwl-icon-close')?.classList.add('hidden');
          document.body.classList.remove('overflow-hidden');
          mobileBtn.focus();
        });
      });
    },
  };
})(Drupal, once);
