---
title: Overview
---

# Overview

Local is a Drupal CMS site template for local councils and similar public sector organisations. It provides a working demonstration of a council website — services navigation, homepage layout, demo content, and editorial workflows — ready to explore in minutes.

## Team

Local was created by [Annertech](https://www.annertech.com/) with contributions from:

- [Tony Barker](https://www.drupal.org/u/tonypaulbarker)
- [Luke Brennan](https://www.drupal.org/u/lukejosephbrennan)
- [Fatima Mahmood](https://www.drupal.org/u/fatimamahmood)
- [Stella Power](https://www.drupal.org/u/stella)
- [Mike King](https://www.drupal.org/u/emkay)

## What is 'Local'?

Local is a **site template** for local organisations serving their communities to get started with Drupal CMS, canvas and other features.

The template is built on [Drupal CMS](https://new.drupal.org/drupal-cms), uses [Canvas](https://www.drupal.org/project/canvas) for page building, and is distributed as a [Drupal Recipe](https://www.drupal.org/docs/drupal-apis/recipe-api).

## What is included

- **Consistent theme** — blue and amber front-end theme built with Tailwind CSS and SDCs, to WCAG 2.2 AA
- **Services architecture** — Service landing pages and Service pages with automatic section listing and clean URLs
- **Canvas pages** — Home, About, and What's On pages demonstrating the available components
- **Demo content** — service sections with example pages, media, menus, and footer links
- **Editorial workflows** — draft and published states with content moderation and scheduling
- **Search** — Search API, pre-configured to index published content
- **Privacy and consent** — Klaro cookie consent with pre-configured apps for common third-party services
- **Email** — Easy Email with transactional templates for all user lifecycle events

## What isn't Local? 

Local is **not** a fully featured LocalGov Drupal or similar distribution for councils.

## Known limitations

- **Multilingual is not supported by Canvas.** This is a Canvas limitation, actively being worked on upstream — see the [Canvas multilingual meta issue](https://www.drupal.org/project/canvas/issues/3551464). Local is suitable for English-only sites until this is resolved.
- **Demo content is illustrative.** All demo content must be replaced before launch.
- **Some menu links are hard coded in templates.** These are being updated.
- **Media cannot be cropped in canvas** This is a current limitation of the Canvas + media integration. Please upload -pre-cropped and treated images.

## Technology stack

| Layer | Technology |
|---|---|
| CMS | Drupal CMS (Drupal 11) |
| Page builder | Canvas |
| Front-end theme | Consistent (Tailwind CSS + SDC) |
| Distribution format | Drupal Recipe |
| Automation workflows | ECA (Event-Condition-Action) |
| Search | Search API |
| Email | Easy Email |
| Cookie consent | Klaro |
