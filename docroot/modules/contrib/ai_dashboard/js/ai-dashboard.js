(function(Drupal, once) {
  Drupal.behaviors.aiDashboardTooltip = {
    attach (context) {
      once('ai-dashboard-tooltip', '[data-ai-tooltip]', context).forEach(($tooltip) => {
        $tooltip.addEventListener('mouseenter', () => {
          const $popovertarget = document.querySelector(`#${$tooltip.getAttribute('popovertarget')}`);

          // Removes native 'title' tooltip if custom AI Tooltip is present
          if ($popovertarget) {
            $tooltip.removeAttribute('title');
          }
        }, { once: true });
      })
    }
  }
}(Drupal, once))
