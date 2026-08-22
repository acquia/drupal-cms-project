---
title: Customisation
---

# Customisation

Local is a starting point. This page covers some common customisation tasks.

## Replace demo content

Work through these in order:

1. **Menus** — Update the menus at (**Structure → Menus**) e.g. the Services menu (**Structure → Menus → Services**).
2. **Service sections** — Delete the demo service landing and service pages before creating your own.
3. **Canvas pages** — Delete or edit Home, About, and What's On at **Content → Pages**. Replace any text, images, and links.
4. **Footer and logo** — Set the logo at **Appearance → Settings → Consistent** or **Appearance → Settings → My Custom Theme Name**.

## Theming

To avoid issues with component discovery, changing themes when using Canvas is currently best done when the site is clear of content and canvas components provided by the current theme have been disabled.
The recommendation is not to subtheme Byte, Mercury or Consistent. Drupal CMS provides 'Blank' as a starting point for a minimal theme.
For experienced Drupal themers to customise 'Consistent', copy the theme to the custom themes folder and replace machine and theme naming with your own.

The Consistent theme's design tokens are CSS custom properties in `src/theme.css`. For example:

```css
--color-primary          /* Main brand colour */
--color-primary-hover    /* Hover state */
--color-link             /* Body link colour */
--font-sans              /* Body typeface */
```

Rebuild after changes:

```bash
cd web/themes/custom/MY_THEME
npm install && npm run build
```

## Adding Canvas components

Create a new component in your theme's `components/` directory:

1. Add `my-component/my-component.component.yml` — defines name, props, and schema
2. Add `my-component/my-component.twig` — defines the markup
3. Optionally add `my-component.css` and `my-component.js`
4. Clear caches — the component appears in **Appearance → Components** and the Canvas library

- See [Canvas SDC components documentation](https://project.pages.drupalcode.org/canvas/sdc-components/)

## Adding content types

Add content types at **Structure → Content types** or via a supplementary recipe. Include new types in the Search API index at **Configuration → Search and metadata → Search API → Content index → Fields** if they should appear in search results.

## Cookie consent

Klaro is pre-configured with consent apps for common third-party services. Enable apps at **Configuration → System → Klaro**. Remove any apps for services you do not use.

## Email

Edit transactional email templates at **Configuration → Easy Email**. Configure the mail transport at **Configuration → System → Mailer**.
