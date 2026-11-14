#!/usr/bin/env python3

"""
Render the AI Ecosystem page (ecosystem.md) from ecosystem.json.

ecosystem.json is produced by drupal_ai_dependents.py, which finds every Drupal
module and recipe with a hard dependency on drupal/ai. This script only renders
that data — collect it first:

    python3 docs/drupal_ai_dependents.py --full-ecosystem --json docs/ecosystem.json
    python3 docs/ecosystem.py

The page is two HTML tables (Modules and Recipes). The tab switcher, name
search, category and stability/security filters are added client-side by
javascripts/tablesort.js; the styling lives in stylesheets/styles.css.
"""

from __future__ import annotations

import html
import json
from pathlib import Path

# Input / output, resolved next to this script.
JSON_INPUT_FILE = Path(__file__).resolve().parent / "ecosystem.json"
OUTPUT_FILE = Path(__file__).resolve().parent / "ecosystem.md"

AI_PROJECT_URL = "https://www.drupal.org/project/ai"

# Stability badge: value -> (display label, CSS class). Matches the order used
# for the filter checkboxes in tablesort.js.
STABILITY_META = {
    "stable": ("Stable", "stab-stable"),
    "rc": ("RC", "stab-rc"),
    "beta": ("Beta", "stab-beta"),
    "alpha": ("Alpha", "stab-alpha"),
    "dev": ("Dev", "stab-dev"),
}

# Security-coverage icons, all self-contained (no external asset).
# Filled shield — covered + stable release.
SECURITY_COVERED_STABLE = (
    '<svg class="shield" xmlns="http://www.w3.org/2000/svg" width="16" height="16" '
    'viewBox="0 0 24 24" fill="currentColor" aria-label="Security covered (stable)">'
    '<title>Security covered (stable release)</title>'
    '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
)
# Outline shield — covered + pre-release.
SECURITY_COVERED_PRERELEASE = (
    '<svg class="shield" xmlns="http://www.w3.org/2000/svg" width="16" height="16" '
    'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" '
    'stroke-linejoin="round" aria-label="Security covered (pre-release)">'
    '<title>Security covered (pre-release)</title>'
    '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
)
SECURITY_NOT_COVERED = '<span title="No security advisory coverage">🚫</span>'


def esc(value) -> str:
    """HTML-escape a value for safe insertion into markup (quotes included)."""
    return html.escape(str(value), quote=True)


def label_cell(item: dict) -> str:
    """The linked project name, with the machine name as a tooltip and an
    optional muted description below it."""
    label = esc(item["label"])
    link = (
        f'<a href="{esc(item["url"])}" title="{esc(item["machine_name"])}">{label}</a>'
    )
    description = item.get("description")
    desc_html = (
        f'<div class="proj-desc">{esc(description)}</div>' if description else ""
    )
    return f'<td data-sort="{label}">{link}{desc_html}</td>'


def version_cell(item: dict) -> str:
    """The version, followed by a coloured stability badge."""
    version = item.get("version") or ""
    stability = item.get("stability", "stable")
    badge_label, badge_class = STABILITY_META.get(stability, STABILITY_META["stable"])
    version_text = esc(version) if version else "—"
    badge = f'<span class="badge {badge_class}">{badge_label}</span>'
    return f'<td data-sort="{esc(version)}" class="col-version">{version_text} {badge}</td>'


def date_cell(item: dict) -> str:
    """The ISO release date (sorts chronologically as text)."""
    date = item.get("release_date") or "—"
    sort_value = "" if date == "—" else esc(date)
    return f'<td data-sort="{sort_value}" class="col-date">{esc(date)}</td>'


def number_cell(value, css_class: str) -> str:
    """A right-aligned, comma-formatted integer cell (— when absent)."""
    if value is None:
        return f'<td data-sort="" class="{css_class}">—</td>'
    return f'<td data-sort="{value}" class="{css_class}">{value:,}</td>'


def security_cell(item: dict) -> str:
    """A shield (stable/pre-release) or 🚫, plus a numeric sort key."""
    if item.get("security_covered"):
        stability = item.get("stability", "stable")
        icon = SECURITY_COVERED_STABLE if stability == "stable" else SECURITY_COVERED_PRERELEASE
        return f'<td data-sort="1" class="col-security">{icon}</td>'
    return f'<td data-sort="0" class="col-security">{SECURITY_NOT_COVERED}</td>'


def categories_cell(item: dict) -> str:
    """The package's categories as clickable filter pills (— when none).

    Each pill carries its category in data-cat so tablesort.js can turn a
    click into a single-category filter. Reads categories via .get() so a
    pre-category ecosystem.json still renders a plain dash.
    """
    cats = item.get("categories") or []
    if not cats:
        return '<td data-sort="" class="col-cat">—</td>'
    pills = "".join(
        f'<span class="cat-pill" data-cat="{esc(c)}">{esc(c)}</span>' for c in cats
    )
    return f'<td data-sort="{esc(", ".join(cats))}" class="col-cat">{pills}</td>'


def cat_attr(item: dict) -> str:
    """The pipe-joined data-categories attribute the JS filter reads (or '')."""
    cats = item.get("categories") or []
    return f' data-categories="{esc("|".join(cats))}"' if cats else ""


def modules_table(modules: list[dict]) -> str:
    """The Modules table: Label · Version · Released · Security · Usage."""
    rows = []
    for item in modules:
        security = "covered" if item.get("security_covered") else "not-covered"
        cells = (
            label_cell(item)
            + version_cell(item)
            + date_cell(item)
            + security_cell(item)
            + number_cell(item.get("usage"), "col-usage")
            + categories_cell(item)
        )
        rows.append(
            f'    <tr data-stability="{esc(item.get("stability", "stable"))}" '
            f'data-security="{security}"{cat_attr(item)}>{cells}</tr>'
        )
    return (
        '<table id="ecosystem-modules">\n'
        "  <thead>\n"
        "    <tr>\n"
        '      <th data-type="text">Module</th>\n'
        '      <th data-type="text">Version</th>\n'
        '      <th data-type="text">Released</th>\n'
        '      <th class="col-security">Security</th>\n'
        '      <th data-type="number" class="col-usage">Installs</th>\n'
        '      <th data-type="text" class="col-cat">Categories</th>\n'
        "    </tr>\n"
        "  </thead>\n"
        "  <tbody>\n"
        + "\n".join(rows)
        + "\n  </tbody>\n"
        "</table>"
    )


def recipes_table(recipes: list[dict]) -> str:
    """The Recipes table: Label · Version · Released · Downloads · Stars.

    Recipes have no Drupal.org usage tracking or security-advisory status, so
    those columns are omitted; Packagist downloads/stars stand in as the
    popularity signal.
    """
    rows = []
    for item in recipes:
        cells = (
            label_cell(item)
            + version_cell(item)
            + date_cell(item)
            + number_cell(item.get("downloads"), "col-downloads")
            + number_cell(item.get("stars"), "col-stars")
            + categories_cell(item)
        )
        rows.append(f"    <tr{cat_attr(item)}>{cells}</tr>")
    return (
        '<table id="ecosystem-recipes">\n'
        "  <thead>\n"
        "    <tr>\n"
        '      <th data-type="text">Recipe</th>\n'
        '      <th data-type="text">Version</th>\n'
        '      <th data-type="text">Released</th>\n'
        '      <th data-type="number" class="col-downloads">Downloads</th>\n'
        '      <th data-type="number" class="col-stars">Stars</th>\n'
        '      <th data-type="text" class="col-cat">Categories</th>\n'
        "    </tr>\n"
        "  </thead>\n"
        "  <tbody>\n"
        + "\n".join(rows)
        + "\n  </tbody>\n"
        "</table>"
    )


def intro_paragraph(
    modules: list[dict], recipes: list[dict], full_ecosystem: bool
) -> str:
    """The one-paragraph scope statement that sits under the page title.

    What earns a row its place depends on which crawl produced
    ecosystem.json, so the wording has to follow the payload's
    `full_ecosystem` flag. Without it, every row passed the drupal/ai
    composer `require` check. With it, modules also qualify by being filed
    under drupal.org's "Artificial Intelligence (AI)" project category,
    dependency or not — so claiming a hard dependency for all of them would
    overstate the scope.
    """
    module_count = f"<strong>{len(modules)}</strong>"
    recipe_count = f"<strong>{len(recipes)}</strong>"
    ai_link = f"[Drupal AI module]({AI_PROJECT_URL})"

    if full_ecosystem:
        return (
            f"These are the {module_count} modules and {recipe_count} recipes "
            f"that make up the {ai_link} ecosystem."
        )

    return (
        f"These are the {module_count} modules and {recipe_count} recipes "
        f"that declare a hard dependency on the {ai_link}."
    )


def build_markdown(payload: dict) -> str:
    """Render the full ecosystem.md page from a results payload."""
    # Default display order: most-installed modules and most-downloaded recipes
    # first (entries with no count sort to the bottom), ties broken by label.
    # This is the order the reader sees before touching Tablesort.
    modules = sorted(
        payload.get("modules", []),
        key=lambda m: (-(m.get("usage") or -1), m["label"].lower()),
    )
    recipes = sorted(
        payload.get("recipes", []),
        key=lambda r: (-(r.get("downloads") or -1), r["label"].lower()),
    )
    generated = payload.get("generated", "")
    versions = "/".join(str(v) for v in sorted(payload.get("drupal_versions", [])))

    return "\n".join(
        [
            "# Ecosystem",
            "",
            intro_paragraph(modules, recipes, payload.get("full_ecosystem", False)),
            "",
            f"*Generated {generated} · Drupal {versions} compatible · all stability levels.*",
            "",
            "<!-- This is built with ecosystem.py from ecosystem.json. Don't edit this file directly. -->",
            "",
            modules_table(modules),
            "",
            recipes_table(recipes),
            "",
        ]
    )


def main() -> None:
    payload = json.loads(JSON_INPUT_FILE.read_text(encoding="utf-8"))
    OUTPUT_FILE.write_text(build_markdown(payload), encoding="utf-8")
    print(
        f"Wrote {OUTPUT_FILE} "
        f"({len(payload.get('modules', []))} modules, {len(payload.get('recipes', []))} recipes)."
    )


if __name__ == "__main__":
    main()
