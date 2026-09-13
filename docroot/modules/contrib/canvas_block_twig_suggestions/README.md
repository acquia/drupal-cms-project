# Canvas Block Twig Suggestions

Provides clean, region-aware block template suggestions for blocks placed
through Drupal Canvas.

## The Problem

Drupal Canvas is a visual-first page builder that uses its own rendering
pipeline instead of Drupal's standard block placement system. When Canvas
places a block on a page, it assigns the block's `#id` to a component UUID
(e.g., `a4ada991-2613-4901-99c4-f23c2efc02b0`) rather than a stable machine
name.

Drupal core's `BlockThemeHooks::themeSuggestionsBlock()` then generates a
template suggestion from that UUID:

    block__a4ada991_2613_4901_99c4_f23c2efc02b0

This suggestion is useless. It changes every time a block is placed, it can
never match a template file, and because it is the highest-priority suggestion,
it overshadows the clean plugin-id suggestions that core already generates
below it.

On top of that, Canvas does not pass region context to individual block render
arrays. Its `BlockComponent::renderComponent()` returns `#theme => 'block'`
with `#id => $componentUuid` but no `#region`. This means a theme has no way
to tell whether a block was placed in the header or the footer, making it
impossible to give the same block type different markup in different regions.

## Why This Approach

Canvas bypasses Drupal's block placement system entirely. Its
`CanvasPageVariant` display variant builds the page by loading `PageRegion`
config entities and rendering each region's component tree through PHP Fibers.
The component tree rendering (`ComponentTreeItemList::renderify()`) iterates
component instances and calls each `ComponentSource::renderComponent()`
without any region parameter.

However, the region information is not lost forever. After the display variant
builds the page, core's `HtmlRenderer::buildPageRenderArray()` takes the
returned render array (which is keyed by region name) and adds
`#theme_wrappers` and `#region` to each region element. When Twig renders
`{{ page.header }}`, the Renderer processes the header region element and
its `#pre_render` callbacks fire before any children (blocks) render.

This module exploits that render order:

1. `hook_preprocess_page()` injects a `#pre_render` callback into every
   region render array.
2. When a region starts rendering, the callback captures the region name
   from the `#region` property that core already set.
3. When `hook_theme_suggestions_block_alter()` fires for each child block,
   it reads the captured region name and uses it to build a region-specific
   template suggestion.

This is the only reliable way to recover region context for Canvas-placed
blocks, because Canvas's rendering happens inside its own display variant
before the page render array reaches the theme layer.

## What It Does

For every block placed through Canvas, this module:

- **Strips the UUID-based suggestion** that core generated from the Canvas
  component UUID.
- **Adds a region-aware suggestion** in the format
  `block__[region]__[plugin_id]`, which becomes the highest-priority
  suggestion.

Core's own plugin-id suggestions remain intact below the new one.

## Template Suggestion Order

For a `system_menu_block:main` block placed in the footer region via Canvas:

| Priority | Suggestion | Template File |
|----------|-----------|---------------|
| Highest | `block__footer__system_menu_block__main` | `block--footer--system-menu-block--main.html.twig` |
| | `block__system_menu_block__main` | `block--system-menu-block--main.html.twig` |
| | `block__system_menu_block` | `block--system-menu-block.html.twig` |
| | `block__system` | `block--system.html.twig` |
| Base | `block` | `block.html.twig` |

## Same Block, Different Regions

If you place `system_branding_block` in both the header and footer regions,
each instance gets its own region-specific suggestion:

- Header: `block--header--system-branding-block.html.twig`
- Footer: `block--footer--system-branding-block.html.twig`

Create either or both template files in your theme and Drupal will use them.
If no region-specific template exists, the generic
`block--system-branding-block.html.twig` is used as a fallback.

## Requirements

- Drupal 11
- Canvas module (`canvas:canvas`)

## Installation

1. Place the module in `web/modules/custom/canvas_block_twig_suggestions/`.
2. Enable the module:

       drush en canvas_block_twig_suggestions

3. Clear cache:

       drush cr

## Verifying It Works

Enable Twig debug in `sites/default/services.yml`:

```yaml
parameters:
  twig.config:
    debug: true
```

Clear cache, then view a Canvas page and inspect the HTML. You will see
template suggestion comments like:

```html
<!-- THEME DEBUG -->
<!-- THEME HOOK: 'block' -->
<!-- FILE NAME SUGGESTIONS:
   * block--footer--system-menu-block--main.html.twig
   * block--system-menu-block--main.html.twig
   * block--system-menu-block.html.twig
   * block--system.html.twig
   x block.html.twig
-->
```

## File Structure

```
canvas_block_twig_suggestions/
├── canvas_block_twig_suggestions.info.yml   # Module metadata
├── canvas_block_twig_suggestions.module     # hook_preprocess_page, hook_theme_suggestions_block_alter
├── src/
│   └── CanvasRegionTracker.php              # #pre_render callback, captures active region
├── tests/                                   # Unit tests
│   ├── bootstrap.php                        # Test bootstrap file
│   └── src/
│       └── Unit/                            # Unit test files
├── CLAUDE.md                                # Development context for Claude Code
├── README.md                                # This file
└── sessions/
    └── session-log.md                       # Development session history
```

## Testing

The module includes PHPUnit unit tests covering all core functionality.
Tests extend `PHPUnit\Framework\TestCase` directly and call the real hook
functions rather than duplicating logic inline.

| Test Class | Coverage |
|-----------|---------|
| `CanvasRegionTrackerTest` | Region tracking: set, get, overwrite, reset, trusted callbacks |
| `CanvasBlockTwigSuggestionsTest` | Hook function: Canvas blocks, regular blocks, missing/null IDs and plugin IDs, multiple UUID suggestions, hyphenated names |
| `HookIntegrationTest` | End-to-end flow: region pre-render then block alter, two blocks in different regions |
| `CanvasBlockTwigSuggestionsFunctionalTest` | Logic units via data providers: Canvas detection, plugin ID cleaning, region cleaning, UUID removal |
| `ModuleInfoTest` | Module metadata: parses `.info.yml` and validates type, core version, dependencies |

Run the tests:

```bash
ddev exec "vendor/bin/phpunit --bootstrap web/modules/custom/canvas_block_twig_suggestions/tests/bootstrap.php web/modules/custom/canvas_block_twig_suggestions/tests/src/Unit/"
```
