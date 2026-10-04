/**
 * @file
 * Global behaviors.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.donationThemeHeader = {
    attach: function (context, settings) {
      const toggles = once('mobile-menu-toggle', '.mobile-menu-toggle', context);
      toggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
          const header = toggle.closest('.region-header');
          const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
          toggle.setAttribute('aria-expanded', !isExpanded ? 'true' : 'false');
          header.classList.toggle('mobile-menu-open', !isExpanded);
        });
      });

      const subMenuToggles = once('mobile-submenu-toggle', '.region-header .menu__item--has-children > svg', context);
      subMenuToggles.forEach(svg => {
        svg.style.cursor = 'pointer';
        svg.addEventListener('click', (e) => {
          const parentItem = e.currentTarget.closest('.menu__item--has-children');
          if (parentItem) {
            parentItem.classList.toggle('is-expanded');
          }
        });
      });
    }
  };

  /**
   * Tabs filter: filter cards by tab_id when using Tabs + Section Grid (or any layout).
   * Scope: same section or [data-tabs-container]. Cards (e.g. card-donation) use data-tab-id to match tab labels.
   * "View all" or empty tab shows all cards.
   */
  Drupal.behaviors.donationThemeTabsFilter = {
    attach: function (context, settings) {
      const tabsContainers = once('tabs-filter', '.tabs[data-tabs]', context);
      tabsContainers.forEach((tabsEl) => {
        const tabButtons = tabsEl.querySelectorAll('[data-tab-id]');
        const container = tabsEl.closest('section') || tabsEl.closest('[data-tabs-container]') || tabsEl.parentElement;
        const allWithTabId = container ? container.querySelectorAll('[data-tab-id]') : [];
        const cards = Array.from(allWithTabId).filter((el) => !el.closest('.tabs'));
        const gridColumns = container ? Array.from(container.querySelectorAll('.section-grid__col')) : [];
        const grids = container ? Array.from(container.querySelectorAll('.section-grid')) : [];
        if (!tabButtons.length) return;

        function setActiveTab(selectedTabId) {
          tabButtons.forEach((btn) => {
            const tabId = (btn.getAttribute('data-tab-id') || '').trim();
            const isActive = tabId === selectedTabId;
            btn.classList.toggle('tabs__tab--active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
          });

          const showAll = !selectedTabId || selectedTabId.toLowerCase() === 'view all';
          const selectedLower = selectedTabId.toLowerCase();
          cards.forEach((card) => {
            const cardTabId = (card.getAttribute('data-tab-id') || '').trim();
            const match = showAll || cardTabId.toLowerCase() === selectedLower;
            card.classList.toggle('tabs-filterable--hidden', !match);
          });

          // When a filter is active, flatten grid columns so visible cards
          // reflow into the grid instead of staying locked to their original column.
          grids.forEach((grid) => {
            grid.classList.toggle('section-grid--filtered', !showAll);
          });
          gridColumns.forEach((col) => {
            col.classList.remove('tabs-filterable--hidden');
          });
        }

        tabButtons.forEach((btn) => {
          btn.addEventListener('click', () => {
            const tabId = (btn.getAttribute('data-tab-id') || '').trim();
            setActiveTab(tabId);
          });
        });

        const activeBtn = tabsEl.querySelector('.tabs__tab--active') || tabButtons[0];
        const initialTabId = (activeBtn.getAttribute('data-tab-id') || '').trim();
        setActiveTab(initialTabId);
      });
    }
  };

  /**
   * Global smooth scroll for same-page hash links.
   * Uses Drupal behaviors + once for AJAX-safe, duplicate-free bindings.
   */
  Drupal.behaviors.donationThemeSmoothScroll = {
    attach: function (context, settings) {
      const hashLinks = once('global-smooth-scroll', 'a[href*="#"]', context);
      const extraSpacing = 12;

      function getStickyHeaderOffset() {
        const headerCandidates = document.querySelectorAll(
          '.region-header, [data-sticky-header], .sticky-header'
        );

        for (const header of headerCandidates) {
          const style = window.getComputedStyle(header);
          const isSticky = style.position === 'sticky' || style.position === 'fixed';
          if (isSticky) {
            return header.offsetHeight || 0;
          }
        }

        return 0;
      }

      function getTargetFromHash(hash) {
        if (!hash || hash === '#') {
          return null;
        }

        const rawId = decodeURIComponent(hash.slice(1)).trim();
        if (!rawId) {
          return null;
        }

        const escapedId = window.CSS && typeof window.CSS.escape === 'function'
          ? window.CSS.escape(rawId)
          : rawId.replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');

        return document.getElementById(rawId)
          || document.querySelector(`[name="${escapedId}"]`)
          || document.querySelector(`#${escapedId}`);
      }

      hashLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
          const href = link.getAttribute('href') || '';
          if (!href.includes('#')) {
            return;
          }

          const url = new URL(href, window.location.href);
          const isSamePage = url.origin === window.location.origin
            && url.pathname === window.location.pathname;

          if (!isSamePage) {
            return;
          }

          const target = getTargetFromHash(url.hash);
          if (!target) {
            return;
          }

          event.preventDefault();

          const headerOffset = getStickyHeaderOffset();
          const targetTop = target.getBoundingClientRect().top + window.pageYOffset;
          const scrollTop = Math.max(0, targetTop - headerOffset - extraSpacing);

          window.scrollTo({
            top: scrollTop,
            behavior: 'smooth'
          });
        });
      });
    }
  };
})(Drupal, once);
