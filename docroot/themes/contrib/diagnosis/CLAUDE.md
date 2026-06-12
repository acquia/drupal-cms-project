# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Diagnosis is a modern Drupal 11 theme that integrates Tailwind CSS 4 with Single Directory Components (SDCs). It's designed for Drupal's Canvas system and uses a component-based architecture with strict coding conventions.

**Key Technologies:**

- Drupal 11.3+ theme
- Tailwind CSS 4 (via @tailwindcss/cli)
- Single Directory Components (SDCs)
- CVA (Class Variant Authority) for conditional styling
- PHP 8.3+ with typed hooks

## Development Commands

```bash
# Development mode (watch CSS changes)
npm run dev

# Build CSS for production
npm run build

# Format code (Prettier)
npm run format

# Check formatting
npm run format:check
```

**Important:** Always run `npm run format` and `npm run build` after making changes to ensure code is properly formatted and build artifacts are up to date.

## Architecture

### Component Structure

Components live in `components/` directory. Each component is a Single Directory Component (SDC) with:

- `*.component.yml` - Schema definition with props, metadata
- `*.twig` - Template file
- `*.js` - Optional JavaScript
- `*.css` or `*.tailwind.css` - Optional styles

Example component structure:

```
components/button/
  ├── button.component.yml
  ├── button.twig
  └── (optional) button.js
```

### Theme Hooks System

The theme uses Drupal 11's PHP attributes-based hook system:

- `src/Hook/ThemeHooks.php` - Main hook implementations using `#[Hook]` attributes
- `src/RenderCallbacks.php` - Trusted callbacks for component pre-rendering
- `diagnosis.theme` - Nearly empty, uses class-based hooks instead

### Template System

Templates are in `templates/` organized by type:

- `templates/layout/` - Page structure (html.html.twig, page.html.twig)
- `templates/navigation/` - Menus, breadcrumbs, pagers
- `templates/block/` - Block overrides
- `templates/views/` - Views templates

The theme loads menus directly in `ThemeHooks::preprocessPage()` and passes them to templates, avoiding block configuration.

### Styling Architecture

**Main CSS:** `src/main.css`

- Imports Tailwind CSS 4
- Component-specific CSS imports
- Custom theme tokens using `@theme` directive
- Design system tokens (colors, typography, spacing)
- Form styling in `@layer components`
- Utility classes in `@layer utilities`

**Build Output:** `build/main.min.css` (served as prebuilt, non-aggregated)

**CSS Variables:** Theme uses CSS custom properties for colors, spacing, and typography that map to Tailwind utilities.

### Canvas Integration

The theme detects Canvas-rendered pages in `ThemeHooks::preprocessPage()`:

- Sets `$variables['rendered_by_canvas']` based on route or content template detection
- Canvas module is a required development dependency
- Theme conflicts with `experience_builder` module (see composer.json)

## Critical Coding Rules

### 1. CVA (Class Variant Authority) is Mandatory

**Never use inline conditionals in HTML attributes.** Always use CVA for conditional classes.

❌ Bad:

```twig
<div class="base{% if condition %} extra{% endif %}"></div>
```

✅ Good:

```twig
{% set variants =
  html_cva(
    base: 'base',
    variants: {
      condition: {
        yes: 'extra',
        no: ''
      }
    }
  )
%}
<div
  class="{{
  variants.apply({
    condition: condition ? 'yes' : 'no'
  })
  }}"
></div>
```

**CVA variant keys use `yes`/`no` strings, not booleans:**

```twig
variants: { clickable: { yes: 'cursor-pointer', no: '' } # Correct clickable: { true: '...', false: '...' } # Wrong }
```

**Use arrays for long class strings (>80 chars):**

```twig
variant: { primary: [ 'border-[var(--hgc-btn-border)]', 'bg-[var(--hgc-btn-bg)]', 'text-[var(--hgc-btn-label)]', 'hover:bg-[var(--hgc-btn-bg-hover)]',
] }
```

**Normalize array/string inputs for CVA:**

```twig
{# Always do this when accepting classes from parent components #}
{% set additional_classes = btn_classes|default('') %}
{% if additional_classes is iterable %}
  {% set additional_classes = additional_classes|join(' ') %}
{% endif %}
```

### 2. Component Includes Must Isolate Context

Always use `with_context: false` or `with only` to prevent context pollution:

```twig
{# Function syntax #}
{{
  include(
    'diagnosis:icon',
    {
      icon: icon
    },
    with_context: false
  )
}}

{# Tag syntax #}
{% include '@diagnosis/components/icon/icon.twig' with {
  icon: icon
} only %}
```

Only pass props that are explicitly configurable in the component's schema. Don't pass every possible prop with defaults.

### 3. No Split HTML Tags Across Conditionals

❌ Bad:

```twig
{% if url %}
  <a href="{{ url }}">
{% endif %}
  <p>Content</p>
{% if url %}
  </a>
{% endif %}
```

✅ Good:

```twig
{% if url %}
  <a href="{{ url }}">
    <p>
      Content
    </p>
  </a>
{% else %}
  <p>
    Content
  </p>
{% endif %}
```

### 4. No Dynamic Tag Names

❌ Bad:

```twig
<h{{ level }}>Title</h{{ level }}>
```

✅ Good:

```twig
{% if level == 1 %}
  <h1>Title</h1>
{% elseif level == 2 %}
  <h2>Title</h2>
{% endif %}
```

### 5. Attributes Must Have Space

Always use `<div {{ attributes }}>` not `<div{{ attributes }}>`

### 6. No Inline Control Structures in Attributes

❌ Bad:

```twig
<div class="base"{% if x %} data-attr="value"{% endif %}>
```

✅ Good:

```twig
{% set data_attr = x ? 'data-attr="value"' : '' %}
<div class="base" {{ data_attr }}></div>
```

## Additional Context

- **CVA Module:** The theme requires the `drupal/cva` module for the `html_cva()` Twig function
- **Icons:** Icon system defined in `diagnosis.icons.yml` with Font Awesome integration
- **Libraries:** Defined in `diagnosis.libraries.yml` - global CSS and messages library
- **Theme Settings:** Color scheme (light/dark) configurable in theme settings via `ThemeHooks::themeSettingsFormAlter()`
- **Font Preloading:** Theme path exposed in `ThemeHooks::preprocessHtml()` for font preloading
- **Menus:** Main, banner, footer, and social menus are programmatically loaded in page preprocess and passed to templates
- **Starter Kit:** This theme can be used as a starter kit (see `diagnosis.starterkit.yml`)

## File Naming Conventions

- Component files: `component-name.component.yml`, `component-name.twig`
- PHP classes: PascalCase in `src/` with proper namespacing (`Drupal\diagnosis\...`)
- Templates: kebab-case with `.html.twig` extension (except components use `.twig`)
- CSS files: `main.css` for build source, component-specific files use `.tailwind.css` or `.css`

## See Also

- `AGENTS.md` - Detailed agent rules (more comprehensive version of critical rules)
- `README.md` - Empty, no useful content currently
- `package.json` - NPM scripts and dependencies
- `composer.json` - Drupal dependencies and metadata
