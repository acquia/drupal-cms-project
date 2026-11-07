import { ComponentType, ComponentInstance } from "../../lib/component.js";
import currentlyInCanvasEditor from "../../lib/currentlyInCanvasEditor.js";

class TabsContainer extends ComponentInstance {
  init() {
    const inCanvas = currentlyInCanvasEditor();

    // Fallback: Check if we're in any iframe (likely Canvas)
    const inIframe = window.self !== window.top;

    if (inCanvas || inIframe) {
      // In Canvas editor, add canvas class to all child tabs
      this.el.classList.add("tabs-container--canvas");
      const tabs = this.el.querySelectorAll(".tab");
      tabs.forEach((tab) => {
        tab.classList.add("tab--canvas");
      });
      // Hide the desktop tablist in Canvas
      const tablist = this.el.querySelector(".tabs-container--tablist");
      if (tablist) {
        tablist.style.display = "none";
      }
      return;
    }

    this.tablist = this.el.querySelector(".tabs-container--tablist");
    this.contentWrapper = this.el.querySelector(".tabs-container--content");
    this.tabElements = Array.from(this.contentWrapper.querySelectorAll(".tab"));
    this.tabs = [];
    this.tabButtons = [];

    // Set aria-orientation based on layout
    const orientation = this.el.dataset.orientation || "horizontal";
    this.tablist.setAttribute("aria-orientation", orientation);

    // Create desktop tab buttons
    this.createTabButtons();

    // Listen for tab activation events from mobile accordion buttons
    this.el.addEventListener("tabactivate", (e) => {
      // Find which tab was activated
      const activatedTab = this.tabElements.find((tab) => tab.contains(e.target));
      if (!activatedTab) return;

      const index = this.tabElements.indexOf(activatedTab);

      // Close all other tabs
      const otherTabInstances = window.diagnosisComponents.tab.instances.filter((tab) => this.el.contains(tab.el) && e.target !== tab.el);
      otherTabInstances.forEach((instance) => {
        instance.isActive = false;
      });

      // Update desktop tab buttons
      this.updateTabButtons(index);
    });

    // Set initial active state
    const activeIndex = this.tabElements.findIndex((tab) => tab.dataset.activeByDefault === "true");
    // If no tab is marked as active by default, activate the first tab
    const indexToActivate = activeIndex !== -1 ? activeIndex : 0;
    if (this.tabElements.length > 0) {
      this.activateTab(indexToActivate);
    }
  }

  createTabButtons() {
    this.tabElements.forEach((tabEl, index) => {
      const tabId = tabEl.dataset.tabId;
      const panelId = tabEl.dataset.panelId;
      const title = tabEl.querySelector(".tab--mobile-button").textContent.trim();
      const isActive = tabEl.dataset.activeByDefault === "true";

      // Create desktop tab button
      const button = document.createElement("button");
      button.setAttribute("role", "tab");
      button.setAttribute("id", tabId);
      button.setAttribute("aria-controls", panelId);
      button.setAttribute("aria-selected", isActive ? "true" : "false");
      button.setAttribute("tabindex", isActive ? "0" : "-1");
      button.textContent = title;

      // Styling with Tailwind classes
      button.className = `
        px-6 py-3 text-2xl
        border-b-2 transition-colors
        focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2
        ${isActive ? "border-primary text-foreground" : "border-transparent text-muted-foreground hover:text-foreground hover:border-muted"}
      `
        .trim()
        .replace(/\s+/g, " ");

      // Click handler
      button.addEventListener("click", () => {
        this.activateTab(index);
      });

      // Keyboard navigation
      button.addEventListener("keydown", (e) => {
        this.handleKeydown(e, index);
      });

      this.tablist.appendChild(button);
      this.tabButtons.push(button);
    });
  }

  activateTab(index) {
    // Deactivate all tabs
    window.diagnosisComponents.tab.instances
      .filter((tab) => this.el.contains(tab.el))
      .forEach((instance) => {
        instance.isActive = false;
      });

    // Activate selected tab
    const selectedTab = window.diagnosisComponents.tab.instances.find((instance) => instance.el === this.tabElements[index]);
    if (selectedTab) {
      selectedTab.isActive = true;
    }

    // Update desktop tab buttons
    this.updateTabButtons(index);
  }

  updateTabButtons(activeIndex) {
    this.tabButtons.forEach((button, index) => {
      const isActive = index === activeIndex;
      button.setAttribute("aria-selected", isActive ? "true" : "false");
      button.setAttribute("tabindex", isActive ? "0" : "-1");

      // Update styling
      if (isActive) {
        button.className = button.className.replace(/border-transparent/g, "border-primary").replace(/text-muted-foreground/g, "text-foreground");
      } else {
        button.className = button.className
          .replace(/border-primary/g, "border-transparent")
          .replace(/(?<!hover:)text-foreground/g, "text-muted-foreground");
      }
    });
  }

  handleKeydown(e, currentIndex) {
    let newIndex = currentIndex;
    const orientation = this.el.dataset.orientation || "horizontal";
    const isVertical = orientation === "vertical";

    // Define navigation keys based on orientation
    const prevKey = isVertical ? "ArrowUp" : "ArrowLeft";
    const nextKey = isVertical ? "ArrowDown" : "ArrowRight";

    switch (e.key) {
      case prevKey:
        e.preventDefault();
        newIndex = currentIndex > 0 ? currentIndex - 1 : this.tabButtons.length - 1;
        break;
      case nextKey:
        e.preventDefault();
        newIndex = currentIndex < this.tabButtons.length - 1 ? currentIndex + 1 : 0;
        break;
      case "Home":
        e.preventDefault();
        newIndex = 0;
        break;
      case "End":
        e.preventDefault();
        newIndex = this.tabButtons.length - 1;
        break;
      case "Enter":
      case " ":
        e.preventDefault();
        this.activateTab(currentIndex);
        return;
      default:
        return;
    }

    // Move focus to new tab and activate it
    this.tabButtons[newIndex].focus();
    this.activateTab(newIndex);
  }
}

new ComponentType(TabsContainer, "tabsContainer", ".tabs-container");
