(function (Drupal, once) {
  Drupal.behaviors.tabsComponent = {
    attach(context) {
      once('tabsComponent', '[data-tabs]', context).forEach((container) => {
        const tabs = container.querySelectorAll('.tab-item');
        const nav = container.querySelector('[data-tabs-nav]');

        if (!tabs.length || !nav) return;

        nav.innerHTML = '';

        const activateTab = (index) => {
          tabs.forEach((tab) => {
            tab.classList.remove('is-active', 'show');
          });

          nav.querySelectorAll('button').forEach((btn) => {
            btn.classList.remove('is-active');
          });

          const activeTab = tabs[index];
          const activeBtn = nav.children[index];

          if (!activeTab || !activeBtn) return;

          activeTab.classList.add('is-active');

          requestAnimationFrame(() => {
            activeTab.classList.add('show');
          });

          activeBtn.classList.add('is-active');
        };

        tabs.forEach((tab, index) => {
          const label = tab.dataset.label || `Tab ${index + 1}`;

          const btn = document.createElement('button');
          btn.className = 'tabs__button';
          btn.textContent = label;

          btn.setAttribute('role', 'tab');

          btn.addEventListener('click', () => {
            activateTab(index);
          });

          nav.appendChild(btn);
        });

        activateTab(0);
      });
    }
  };
})(Drupal, once);
