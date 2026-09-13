---
title: Components
---

# Components

The Local components are built with Single Directory Components (SDCs) provided by the **Consistent** theme. These are registered with Canvas and available on any Canvas page.

## Components provided by the Consistent theme

### Hero banner

Full-width image with an overlaid text box. Contains a heading, sub-heading, and either a search field or a call-to-action link.

On mobile, the layout reflows.

**Props:** `heading_text`, `subtitle`, `media`, `show_search`, `cta_label`, `cta_url`

### Search box

A standalone search form that submits to `/search`.

**Props:** `search_placeholder`

### Top task

A navigational link. Add multiple top task components inside a section to form a Most Popular section.

**Props:** `top_task_label`, `top_task_url`

### Card

A content card with an optional image, heading, and body text. The heading links to the card's target URL.

**Props:** `card_heading`, `card_url`, `card_text`, `card_media`

### Section

A layout wrapper applying consistent padding with an optional muted background. Groups related components in optional grid columns with visual separation.

**Props:** `section_muted`

### CTA banner

Full-width promotional strip with a heading, body text, call-to-action link, and optional image.

**Props:** `heading_text`, `text`, `cta_label`, `cta_url`, `media`

### Heading

A standalone heading element with configurable level and text.

**Props:** `heading_text`, `heading_level`

### Text

Rich text content within a Canvas page.

**Props:** `text`

### Link and summary

A labelled link with supporting text summary

**Props:** `link_label`, `link_url`, `link_summary_text`

## Using components in Canvas

Open the component library from the left-hand panel. Click the + symbol to add a new component. Drag a component onto the page, then configure its props in the right-hand panel. See [Editing with Canvas](editing-with-canvas.md) for a full walkthrough.

## Component management

For advanced users, to manage the components available to canvas, visit /admin/appearance/component

Components can be enabled or disabled.

When modifying Single Directory Components props and slots, the components should be disabled before development and re-enabled
(otherwise, it's possible for them to get into a bad state).

See the [Canvas SDC documentation](https://project.pages.drupalcode.org/canvas/sdc-components/).
