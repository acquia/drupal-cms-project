# CVA (Class Variance Authority) Module

This module makes Twig's built-in `html_cva` function available in Drupal templates. The `html_cva` function is part of Twig 3.12+ and comes from the `twig/html-extra` package.

This is particularly useful for building reusable components with Tailwind CSS.

**Documentation:** [Twig html_cva function](https://twig.symfony.com/doc/3.x/functions/html_cva.html)

## Installation

1. Install the required package:
   ```bash
   composer require twig/html-extra
   ```

2. Place this module in `web/modules/custom/cva/`

3. Enable the module via Drush: `drush en cva`
   Or enable via the Drupal admin UI at `/admin/modules`

## Usage

### Basic Example

```twig
{%
  set alert = html_cva(
    base: 'alert',
    variants: {
      color: {
        blue: 'bg-blue',
        red: 'bg-red',
        green: 'bg-green',
      },
      size: {
        sm: 'text-sm',
        md: 'text-md',
        lg: 'text-lg',
      }
    }
  )
%}

<div class="{{ alert.apply({color, size}, class) }}">
  Alert content
</div>
```

### With Default Variants

```twig
{%
  set alert = html_cva(
    base: 'alert',
    variants: {
      color: {
        blue: 'bg-blue',
        red: 'bg-red',
        green: 'bg-green',
      },
      size: {
        sm: 'text-sm',
        md: 'text-md',
        lg: 'text-lg',
      },
      rounded: {
        sm: 'rounded-sm',
        md: 'rounded-md',
        lg: 'rounded-lg',
      }
    },
    default_variant: {
      rounded: 'md',
    }
  )
%}

<div class="{{ alert.apply({color: 'red', size: 'lg'}) }}">
  Alert content
</div>
{# Result: class="alert bg-red text-lg rounded-md" #}
```

### Compound Variants

You can define compound variants that apply when multiple conditions are met:

```twig
{%
  set alert = html_cva(
    base: 'alert',
    variants: {
      color: {
        blue: 'bg-blue',
        red: 'bg-red',
        green: 'bg-green',
      },
      size: {
        sm: 'text-sm',
        md: 'text-md',
        lg: 'text-lg',
      }
    },
    compound_variants: [{
      color: ['red'],
      size: ['md', 'lg'],
      class: 'font-bold',
    }]
  )
%}

<div class="{{ alert.apply({color: 'red', size: 'lg'}) }}">
  Alert content
</div>
{# Result: class="alert bg-red text-lg font-bold" #}
```

### With Tailwind Merge

To avoid class conflicts with Tailwind CSS, use the `tailwind_merge` filter:

```bash
composer require tales-from-a-dev/twig-tailwind-extra
```

```twig
<div class="{{ alert.apply({color, size}, class)|tailwind_merge }}">
  Alert content
</div>
```

## Requirements

- Drupal 11.x
- Twig 3.12+
- twig/html-extra package

For complete documentation, see the [official Twig html_cva documentation](https://twig.symfony.com/doc/3.x/functions/html_cva.html).

## Installation

1. Place this module in `web/modules/custom/cva/`
2. Enable the module via Drush: `drush en cva`
3. Or enable via the Drupal admin UI at `/admin/modules`

## Requirements

- Drupal 11.x
- Twig (included with Drupal core)

