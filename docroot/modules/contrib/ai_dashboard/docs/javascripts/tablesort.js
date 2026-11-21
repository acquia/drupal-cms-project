/**
 * Turns the two ecosystem tables (rendered by ecosystem.py) into a single
 * tabbed view: flip between Modules and Recipes, search by name, sort any
 * column (Tablesort), filter Modules by stability and security coverage, and
 * filter both tabs by category (multi-select, enhanced by Chosen.js).
 */
document$.subscribe(function () {
  var modulesTable = document.getElementById("ecosystem-modules")
  var recipesTable = document.getElementById("ecosystem-recipes")
  if (!modulesTable || !recipesTable) return

  // document$ re-fires on instant navigation; only build the UI once per table.
  if (modulesTable.dataset.ecosystemReady) return
  modulesTable.dataset.ecosystemReady = "1"

  // Sorting comes from the Tablesort library + number plugin (see zensical.toml).
  new Tablesort(modulesTable)
  new Tablesort(recipesTable)

  // The stability levels and security states offered as Modules filters, in
  // display order. Values match the data-stability / data-security attributes.
  var STABILITY = [["stable", "Stable"], ["rc", "RC"], ["beta", "Beta"], ["alpha", "Alpha"], ["dev", "Dev"]]
  var SECURITY = [["covered", "Covered"], ["not-covered", "Not covered"]]

  // Category names in canonical display order — mirrors CATEGORIES in
  // drupal_ai_dependents.py. Any category not listed (e.g. "Uncategorized" or a
  // future addition) still appears in the filter, sorted alphabetically last.
  var CATEGORY_ORDER = [
    "Tool", "Cloud Providers", "Local Providers", "Editorial", "Content",
    "Search", "Chat", "Automation", "Agents", "Analytics", "Media",
    "Vector Database", "SEO & Metadata", "Translation", "Safety & Governance",
    "Accessibility", "Developer Tools", "Evaluation & Testing"
  ]

  function el(tag, className, text) {
    var node = document.createElement(tag)
    if (className) node.className = className
    if (text != null) node.textContent = text
    return node
  }

  function bodyRows(table) {
    return table.tBodies.length ? Array.prototype.slice.call(table.tBodies[0].rows) : []
  }

  function searchInput(placeholder) {
    var input = el("input", "tablesort-filter md-search__input")
    input.type = "search"
    input.placeholder = placeholder
    return input
  }

  // Build a labelled checkbox group; every box starts checked. Returns the
  // wrapping element plus the list of checkboxes for reading checked values.
  function checkboxGroup(legend, cssClass, entries) {
    var group = el("span", "filter-group")
    group.appendChild(document.createTextNode(legend + " "))
    var boxes = []
    entries.forEach(function (entry) {
      var label = el("label")
      var box = document.createElement("input")
      box.type = "checkbox"
      box.className = cssClass
      box.value = entry[0]
      box.checked = true
      boxes.push(box)
      label.appendChild(box)
      label.appendChild(document.createTextNode(" " + entry[1]))
      group.appendChild(label)
    })
    return { group: group, boxes: boxes }
  }

  function checkedValues(boxes) {
    var set = {}
    boxes.forEach(function (box) { if (box.checked) set[box.value] = true })
    return set
  }

  function nameOf(row) {
    return row.cells.length ? row.cells[0].textContent.toLowerCase() : ""
  }

  // Categories present across `rows`, in CATEGORY_ORDER, with any extras
  // (e.g. "Uncategorized") appended alphabetically. Mirrors the upstream
  // renderer's _ordered_categories().
  function orderedCategories(rows) {
    var present = {}
    rows.forEach(function (row) {
      (row.dataset.categories || "").split("|").forEach(function (c) {
        if (c) present[c] = true
      })
    })
    var out = CATEGORY_ORDER.filter(function (c) { return present[c] })
    Object.keys(present).sort().forEach(function (c) {
      if (CATEGORY_ORDER.indexOf(c) === -1) out.push(c)
    })
    return out
  }

  // A multi-select of the categories present in `rows`. Kept as a native
  // <select multiple>; Chosen.js enhances it into a searchable pill dropdown
  // when available, but the filter reads this element either way.
  function categorySelect(rows, id) {
    var select = el("select", "chosen-select cat-filter")
    select.id = id
    select.multiple = true
    select.setAttribute("data-placeholder", "Filter by category…")
    orderedCategories(rows).forEach(function (c) {
      var opt = document.createElement("option")
      opt.value = c
      opt.textContent = c
      select.appendChild(opt)
    })
    return select
  }

  // Wrap the category <select> in a filter-group so it lines up with the other
  // filter controls. No text label — Chosen shows a "Filter by category…"
  // placeholder instead.
  function catGroup(select) {
    var group = el("span", "filter-group")
    group.appendChild(select)
    return group
  }

  function selectedCategories(select) {
    return Array.prototype.slice.call(select.selectedOptions).map(function (o) { return o.value })
  }

  // OR semantics: no selection shows everything; a row with no categories is
  // always shown; otherwise the row matches if any of its categories is picked.
  function categoryMatch(row, selected) {
    if (selected.length === 0) return true
    var cats = (row.dataset.categories || "").split("|").filter(Boolean)
    if (cats.length === 0) return true
    return cats.some(function (c) { return selected.indexOf(c) !== -1 })
  }

  // Modules: name + stability + security + category
  var mSearch = searchInput("Search modules…")
  var stab = checkboxGroup("Stability:", "stab-cb", STABILITY)
  var sec = checkboxGroup("Security:", "sec-cb", SECURITY)
  var mRows = bodyRows(modulesTable)
  var mCat = categorySelect(mRows, "cat-modules")
  var mEmpty = el("p", "ecosystem-empty", "No modules match your filters.")

  function applyModulesFilter() {
    var q = mSearch.value.trim().toLowerCase()
    var stabChecked = checkedValues(stab.boxes)
    var secChecked = checkedValues(sec.boxes)
    var catSelected = selectedCategories(mCat)
    var visible = 0
    mRows.forEach(function (row) {
      var show =
        (q === "" || nameOf(row).indexOf(q) !== -1) &&
        stabChecked[row.dataset.stability] === true &&
        secChecked[row.dataset.security] === true &&
        categoryMatch(row, catSelected)
      row.hidden = !show
      if (show) visible++
    })
    mEmpty.hidden = visible !== 0
  }

  // Recipes: name + category (no stability/security data exists for recipes)
  var rSearch = searchInput("Search recipes…")
  var rRows = bodyRows(recipesTable)
  var rCat = categorySelect(rRows, "cat-recipes")
  var rEmpty = el("p", "ecosystem-empty", "No recipes match your filters.")

  function applyRecipesFilter() {
    var q = rSearch.value.trim().toLowerCase()
    var catSelected = selectedCategories(rCat)
    var visible = 0
    rRows.forEach(function (row) {
      var show = (q === "" || nameOf(row).indexOf(q) !== -1) &&
        categoryMatch(row, catSelected)
      row.hidden = !show
      if (show) visible++
    })
    rEmpty.hidden = visible !== 0
  }

  mSearch.addEventListener("input", applyModulesFilter)
  stab.boxes.concat(sec.boxes).forEach(function (box) {
    box.addEventListener("change", applyModulesFilter)
  })
  rSearch.addEventListener("input", applyRecipesFilter)

  // Assemble the tabbed layout around the existing tables
  var wrapper = el("div", "ecosystem")
  modulesTable.parentNode.insertBefore(wrapper, modulesTable)

  var tabs = el("div", "tabs")
  var btnModules = el("button", "tab-btn active", "Modules (" + mRows.length + ")")
  var btnRecipes = el("button", "tab-btn", "Recipes (" + rRows.length + ")")
  btnModules.type = "button"
  btnRecipes.type = "button"
  tabs.appendChild(btnModules)
  tabs.appendChild(btnRecipes)

  var panelModules = el("div", "tab-panel active")
  var mControls = el("div", "controls")
  mControls.appendChild(mSearch)
  mControls.appendChild(stab.group)
  mControls.appendChild(sec.group)
  mControls.appendChild(catGroup(mCat))
  panelModules.appendChild(mControls)
  panelModules.appendChild(modulesTable)
  panelModules.appendChild(mEmpty)

  var panelRecipes = el("div", "tab-panel")
  var rControls = el("div", "controls")
  rControls.appendChild(rSearch)
  rControls.appendChild(catGroup(rCat))
  panelRecipes.appendChild(rControls)
  panelRecipes.appendChild(recipesTable)
  panelRecipes.appendChild(rEmpty)

  wrapper.appendChild(tabs)
  wrapper.appendChild(panelModules)
  wrapper.appendChild(panelRecipes)

  function activate(showModules) {
    btnModules.classList.toggle("active", showModules)
    btnRecipes.classList.toggle("active", !showModules)
    panelModules.classList.toggle("active", showModules)
    panelRecipes.classList.toggle("active", !showModules)
  }
  btnModules.addEventListener("click", function () { activate(true) })
  btnRecipes.addEventListener("click", function () { activate(false) })

  // Clicking a category pill in a row narrows that tab's filter to just that
  // category. Delegated to the tbody so it keeps working after Tablesort
  // re-orders rows.
  function filterToCategory(select, category, apply) {
    Array.prototype.slice.call(select.options).forEach(function (o) {
      o.selected = (o.value === category)
    })
    if (window.jQuery) window.jQuery(select).trigger("chosen:updated")
    apply()
  }
  modulesTable.tBodies[0].addEventListener("click", function (ev) {
    var pill = ev.target.closest(".cat-pill")
    if (pill) filterToCategory(mCat, pill.dataset.cat, applyModulesFilter)
  })
  recipesTable.tBodies[0].addEventListener("click", function (ev) {
    var pill = ev.target.closest(".cat-pill")
    if (pill) filterToCategory(rCat, pill.dataset.cat, applyRecipesFilter)
  })

  // Progressive enhancement: turn the plain multi-selects into searchable pill
  // dropdowns with Chosen.js when jQuery + Chosen are loaded (via zensical.toml).
  // If they're absent the native <select multiple> still filters — everything
  // above reads the underlying element, not Chosen.
  if (window.jQuery && window.jQuery.fn && window.jQuery.fn.chosen) {
    var $ = window.jQuery
    $(mCat).chosen({ width: "240px", search_contains: true }).on("change", applyModulesFilter)
    $(rCat).chosen({ width: "240px", search_contains: true }).on("change", applyRecipesFilter)
  } else {
    mCat.addEventListener("change", applyModulesFilter)
    rCat.addEventListener("change", applyRecipesFilter)
  }

  // The renderer already emits rows most-installed / most-downloaded first, so
  // that natural order is the default view. Tablesort handles re-sorting when
  // the reader clicks a column header.
  applyModulesFilter()
  applyRecipesFilter()
})
