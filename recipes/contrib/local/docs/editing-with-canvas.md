---
title: Editing with Canvas
---

# Editing with Canvas

Canvas is Drupal's new visual page builder. In Local, the Home, About, and What's On pages are Canvas pages. Canvas pages are distinct from standard Drupal nodes — they use a component-based layout rather than fields.

## Opening a page for editing

Go to **Content → Pages**. Click a page title, then **Edit** to open the Canvas editor. You can also use the contextual edit link from the front-end when logged in.

## The Canvas interface

**Top toolbar** — Shows the page name, publish state, preview button, and save button.

**Left panel** — Component library. Search or browse components and drag them onto the page.

**Right panel** — Component settings. When a component is selected, its props appear here.

## Adding and arranging components

1. Find the component in the left-hand library.
2. Drag it onto the page. A drop indicator shows where it will land.
3. Configure its props in the right-hand panel.

Drag components to reorder them. Select a component and use the toolbar to delete it.

## Home page structure

The demo home page uses:

- **Hero banner** — site name, tagline, and main search form
- **Section** — wrapping the Top Tasks
- **Top task** most popular service links
- **Section** — lays out components in a grid
- **Card**  — service section landing pages
- **CTA banner** — promotional strip with link and image
- **Card** — featured content cards

## Header and footer

The header and footer are Drupal block regions, not Canvas components.

- Header navigation: **Structure → Menus → Services** / **Structure → Menus → Main navigation**
- Footer links: **Structure → Menus → Footer**
- Site logo: **Appearance → Settings → Consistent**

## Saving and publishing

Canvas uses Drupal's content moderation workflow. Save as **Draft** or **Published** from the top-right of the editor.

## Further reading

[Canvas documentation](https://project.pages.drupalcode.org/canvas/)
