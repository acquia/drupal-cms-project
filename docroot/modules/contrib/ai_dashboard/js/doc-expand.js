(function (Drupal, once) {
  Drupal.behaviors.aiDashboardDoc = {
    attach (context) {
      once('ai-dashboard-doc', '.block-ai-dashboard.block-ai-documentation', context).forEach(
        ($docBlock) => {
          const $expandButton = $docBlock.querySelector('.expand-ai-documentation')
          const softLimit = $expandButton?.dataset.softLimit || 4

          const $docItems = $docBlock.querySelectorAll('.admin-item')

          $docItems.forEach(($item, index) => {
            if (index >= softLimit) {
              $item.style.display = 'none'
            }
          })

          $expandButton?.addEventListener('click', (event) => {
            event.preventDefault()
            
            const isExpanded = $expandButton.dataset.expanded === 'true'
            
            if (isExpanded) {
              $docItems.forEach(($item, index) => {
                if (index >= softLimit) {
                  $item.style.display = 'none'
                }
              })
              $expandButton.textContent = Drupal.t('Expand')
              $expandButton.setAttribute('aria-label', Drupal.t('Show all documentation links'))
              $expandButton.dataset.expanded = 'false'
            } else {
              $docItems.forEach(($item) => {
                $item.style.display = 'block'
              })
              $expandButton.textContent = Drupal.t('Collapse')
              $expandButton.dataset.expanded = 'true'
              $expandButton.setAttribute('aria-label', Drupal.t('Show fewer documentation links'))
              Drupal.announce(Drupal.t('More documentation items added.'), 'polite')
            }
          })
      })
    }
  }
}(Drupal, once))
