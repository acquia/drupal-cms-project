# AI Dashboard

[AI Dashboard](https://drupal.org/project/ai_dashboard) provides a centralized, user-friendly interface for configuring and managing all AI-powered functionality in your Drupal site. This module replaces the existing AI configuration overview with a comprehensive dashboard that streamlines AI provider setup, feature installation, and system configuration.

## Ecosystem Documentation

The [docs/ecosystem.md](Ecosystem documentation) is automatically generated from a live Drupal.org and Packagist data using [ai-module-usage](https://github.com/ultimike/ai-module-usage), in collaboration with [ultimike](https://github.com/ultimike).

### Files

| File | Role |
| --- | --- |
| `drupal_ai_dependents.py` | **Collector.** Finds every Drupal module and recipe with a hard Composer dependency on [`drupal/ai`](https://www.drupal.org/project/ai) and writes the raw data as JSON. Vendored from [ultimike/ai-module-usage](https://github.com/ultimike/ai-module-usage). |
| `ecosystem.json` | **Generated data.** The collector's output: a `modules` list and a `recipes` list. Input to the renderer. |
| `ecosystem.py` | **Renderer.** Reads `ecosystem.json` and writes `ecosystem.md` as two HTML tables. |
| `ecosystem.md` | **Generated page.** Do not edit by hand — it is overwritten by `ecosystem.py`. |
| `javascripts/tablesort.js` | Turns the two tables into a tabbed Modules/Recipes view with a name search, stability/security filters, and sortable columns. |
| `stylesheets/styles.css` | Styling for the tabs, filter controls and stability badges. |

### Building

```bash
pip install zensical
python3 docs/drupal_ai_dependents.py --full-ecosystem --json docs/ecosystem.json
python3 docs/ecosystem.py
zensical build --clean
```

### Updating

`drupal_ai_dependents.py` is a verbatim copy of the upstream file. To update it, re-copy the latest version:

```bash
curl -fsSL https://raw.githubusercontent.com/ultimike/ai-module-usage/main/drupal_ai_dependents.py -o docs/drupal_ai_dependents.py
```

Keep the JSON schema (`modules` / `recipes` fields) in sync with `ecosystem.py`
if upstream changes it.
