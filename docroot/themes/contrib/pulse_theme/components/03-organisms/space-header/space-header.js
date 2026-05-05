/**
 * @file
 * Global utilities.
 *
 */
(function (Drupal) {
  Drupal.behaviors.spaceNavbar = {
    attach(context) {
      const navbars = context.querySelectorAll('.navbar');

      navbars.forEach(navbar => {
        const hamburgerBtn = navbar.querySelector('.navbar--hamburger');
        const menu = navbar.querySelector('.navbar--menu');

        if (hamburgerBtn && menu) {
          hamburgerBtn.addEventListener('click', () => {
            const isExpanded = hamburgerBtn.getAttribute('aria-expanded') === 'true';
            
            hamburgerBtn.setAttribute('aria-expanded', !isExpanded);
            hamburgerBtn.classList.toggle('is-active');
            menu.classList.toggle('is-open');
          });
        }
      });
    },
  };
})(Drupal);
