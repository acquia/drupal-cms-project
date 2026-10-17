((Drupal, once) => {
  Drupal.behaviors.convene_themeNavigation = {
    attach: (context) => {
      const navBlock =
        context.querySelector(".block-convene-theme-mainnavigation") ||
        document.querySelector(".block-convene-theme-mainnavigation");
      if (!navBlock) return;

      const navItems = once(
        "convene-theme-navigation",
        ".block-convene-theme-mainnavigation li",
        context,
      );

      navItems.forEach((item) => {
        const submenu = item.querySelector("ul");
        if (submenu) {
          // Pre-emptively add has-submenu if it's not already there (though Twig should handle it)
          item.classList.add("has-submenu");

          // Mobile back button logic
          if (!submenu.querySelector(".mobile-back-item")) {
            const backBtnLi = document.createElement("li");
            backBtnLi.classList.add("mobile-back-item");
            const backBtn = document.createElement("button");
            backBtn.textContent = "Back";
            backBtn.classList.add("mobile-back-btn");

            backBtnLi.appendChild(backBtn);
            submenu.insertBefore(backBtnLi, submenu.firstChild);

            backBtn.addEventListener("click", (e) => {
              e.stopPropagation();
              item.classList.remove("is-open");
              const parentList = item.parentElement;
              if (parentList && !parentList.closest("li.has-submenu")) {
                navBlock.classList.remove("submenu-active");
              }
            });
          }

          const link = item.querySelector("a");
          if (link) {
            link.addEventListener("click", (e) => {
              if (
                window.innerWidth < 992 &&
                item.classList.contains("has-submenu")
              ) {
                // Only prevent default if it's a mobile click on a parent with submenu
                e.preventDefault();
                item.classList.add("is-open");
                navBlock.classList.add("submenu-active");
              }
            });
          }
        }
      });

      const headerInner = document.querySelector(".site-header__inner");
      if (headerInner && navBlock) {
        let mobileToggle = headerInner.querySelector(".mobile-toggle");
        if (!mobileToggle) {
          mobileToggle = document.createElement("button");
          mobileToggle.classList.add("mobile-toggle");
          mobileToggle.setAttribute("aria-label", "Open menu");
          mobileToggle.setAttribute("aria-expanded", "false");
          mobileToggle.innerHTML = `<span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>`;

          headerInner.appendChild(mobileToggle);

          mobileToggle.addEventListener("click", () => {
            const isOpen = document.body.classList.toggle("mobile-menu-open");
            mobileToggle.setAttribute("aria-expanded", isOpen.toString());
            mobileToggle.classList.toggle("is-active");
          });
        }

        let mobileRegister = headerInner.querySelector(".mobile-register-btn");
        if (!mobileRegister) {
          const mainUl = navBlock.querySelector("ul:not(.contextual-links)");
          if (mainUl && mainUl.children.length > 0) {
            const lastLi = mainUl.children[mainUl.children.length - 1];
            const link = lastLi.querySelector("a");
            if (link) {
              mobileRegister = link.cloneNode(true);
              mobileRegister.classList.add("mobile-register-btn");
              headerInner.insertBefore(mobileRegister, mobileToggle);
            }
          }
        }
      }

      // Close submenus on outside click
      document.addEventListener("click", (e) => {
        if (
          !e.target.closest(".block-convene-theme-mainnavigation") &&
          !e.target.closest(".mobile-toggle")
        ) {
          const openItems = document.querySelectorAll(
            ".block-convene-theme-mainnavigation li.is-open",
          );
          openItems.forEach((item) => {
            item.classList.remove("is-open");
          });
          navBlock.classList.remove("submenu-active");
        }
      });
    },
  };
})(Drupal, once);