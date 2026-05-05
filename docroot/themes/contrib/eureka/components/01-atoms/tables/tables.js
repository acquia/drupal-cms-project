/**
 * Tables JS
 *
 * Responsive tables tweaks.
 */

Drupal.behaviors.ResponsiveTables = {
  attach(context) {
    const tables = [].slice.call(
      context.querySelectorAll('table:not(.table--non-responsive)'),
    );

    const tablesScrollable = [].slice.call(
      context.querySelectorAll('.table--scroll'),
    );

    if (tables.length) {
      tables.forEach(Drupal.behaviors.ResponsiveTables.addMobileHeaders);
    }

    if (tablesScrollable.length) {
      tablesScrollable.forEach(
        Drupal.behaviors.ResponsiveTables.addTableWrapper,
      );
    }

    return tables;
  },
  /**
   * Add a copy of the header label to each cell, that's displayed on mobile.
   * Tables appear stacked on mobile.
   */
  addMobileHeaders(table) {
    // display: block will remove default table roles so we need to add them
    // back in with ARIA.
    if (table.classList.contains('table--details')) {
      return true;
    }

    table.setAttribute('role', 'table');
    table.classList.add('responsive--processed');
    const theads = [].slice.call(table.querySelectorAll('thead'));
    const tfoots = [].slice.call(table.querySelectorAll('tfoot'));
    theads.forEach((thead) => thead.setAttribute('role', 'rowgroup'));
    tfoots.forEach((thead) => thead.setAttribute('role', 'rowgroup'));

    // Get all the headers.
    let headers = [].slice.call(table.querySelectorAll('thead th'));
    headers.forEach((thead) => thead.setAttribute('role', 'columnheader'));
    // Get all the rows.
    const rows = [].slice.call(table.querySelectorAll('tbody tr'));

    // If table has no thead, it adds in the thead element.
    if (theads.length === 0) {
      const newTableHead = document.createElement('thead');
      newTableHead.innerHTML = rows[0].innerHTML;
      const tbody = table.querySelector('tbody');

      tbody.parentElement.prepend(newTableHead);
      rows[0].remove();

      const cells = newTableHead.querySelectorAll('td');

      cells.forEach((cell) => {
        const header = document.createElement('th');
        header.innerHTML = cell.innerHTML;

        cell.replaceWith(header);
        header.classList.add('columnheader');
      });

      // Redfined headers so mobile JS works.
      headers = [].slice.call(table.querySelectorAll('thead th'));
    }

    rows.forEach((row) => {
      // Re-add roles.
      row.setAttribute('role', 'row');
      // Whack the table header for that cell at the beginning of the cell.
      const cells = row.querySelectorAll('td');

      cells.forEach((cell, cellIndex) => {
        // Re-add roles.
        cell.setAttribute('role', 'cell');
        const mobileHeader = document.createElement('span');
        mobileHeader.className = 'table__mobile-header';
        mobileHeader.innerHTML = headers[cellIndex].innerHTML;
        cell.insertBefore(mobileHeader, cell.firstChild);
      });
    });

    return table;
  },

  // Add a div wrapping the table to be able to scroll into the table
  addTableWrapper(table) {
    const parent = table.parentNode;
    const divWrapper = document.createElement('div');
    divWrapper.className = 'table-container';
    let parentWithTabIndex = parent;

    if (!parent.classList.contains('table-container')) {
      divWrapper.append(table.cloneNode(true));
      table.replaceWith(divWrapper);
      parentWithTabIndex = divWrapper;
    }
    // Added focus to tables that has a visible scrollbar
    // to be able to scroll through with the keyboard
    if (parentWithTabIndex.scrollWidth > parentWithTabIndex.clientWidth) {
      parentWithTabIndex.setAttribute('tabindex', 0);
    }
  },
};
