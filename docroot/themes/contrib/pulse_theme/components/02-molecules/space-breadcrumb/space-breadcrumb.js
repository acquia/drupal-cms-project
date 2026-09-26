/**
 * Breadcrumb JS
 */

// Wait until DOM content is fully loaded
document.addEventListener('DOMContentLoaded', () => {
  const breadcrumb = document.querySelector('.breadcrumb');

  // Check if breadcrumb exists and has at least 4 children
  if (
    breadcrumb &&
    breadcrumb.children.length >= 4 &&
    window.breadcrumb_variation_options === 'collapse'
  ) {
    // Detach breadcrumb items (except first 3 and last)
    const breadcrumbItems = Array.from(breadcrumb.children);
    const detachedChildren = breadcrumbItems.slice(3, -1);

    detachedChildren.forEach((child) => child.remove());

    // Create the expand breadcrumb element
    const expandBreadcrumb = document.createElement('li');
    expandBreadcrumb.innerHTML = `
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16" fill="none"/><polyline points="96 48 176 128 96 208" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg>
      <a class="dotitem ml-1 mr-2" href="#" title="Show all breadcrumbs"><strong>... </strong></a>
      <span class="dotitemwrap"></span>
    `;

    // Append the expand breadcrumb before the last breadcrumb item
    breadcrumb.insertBefore(expandBreadcrumb, breadcrumb.lastElementChild);

    // Click outside handler to close expanded breadcrumbs
    document.addEventListener('click', (event) => {
      if (!breadcrumb.contains(event.target)) {
        document.querySelector('.dotitemwrap')?.classList.remove('active');
        expandBreadcrumb.classList.remove('activedot');
      }
    });

    // Click event to expand/collapse breadcrumbs
    const dotItem = expandBreadcrumb.querySelector('.dotitem');
    const dotItemWrap = expandBreadcrumb.querySelector('.dotitemwrap');

    dotItem.addEventListener('click', (event) => {
      event.preventDefault();

      // Append detached breadcrumb items back to the wrapper
      detachedChildren.forEach((child) => dotItemWrap.appendChild(child));

      // Toggle active state
      dotItemWrap.classList.toggle('active');
      expandBreadcrumb.classList.toggle('activedot');
    });
  }
});
