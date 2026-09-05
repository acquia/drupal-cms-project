---
title: Services
---

# Services

The services architecture provides a two-level structure — service sections and individual service pages — with automatic section listing, URL generation, and an optional top tasks tier on each section.

## Content types

### Service landing page

A top-level service area such as Housing, Waste, or Libraries. Lists all service pages within it.

| Field | Type | Notes                                                                        |
|---|---|------------------------------------------------------------------------------|
| Title | Plain text | Required. Used in the URL.                                                   |
| Description | Plain text (long) | Introductory text shown at the top of the page.                              |
| In this section | Paragraph (entity reference revisions) | Managed automatically by ECA. Can also have overidden summary and link text. |
| Number of top tasks | Integer | Controls how many items appear in the Most Popular tier.                     |

URL pattern: `/[title]` — for example, `/housing`

### Service page

An individual service within a section — for example, Pay your tax or Report a missed collection.

| Field | Type | Notes |
|---|---|---|
| Title | Plain text | Required. |
| Description | Plain text (long) | Required. Used in section listings and search results. |
| Parent section | Entity reference | The Service landing page this page belongs to. Setting this triggers the ECA workflow. |
| Content | Formatted text (long) | Full body content of the page. |

URL pattern: `/[parent-title]/[title]` — for example, `/housing/report-a-repair`

## How section listings work

The automation if service sections is a a demonstration of ECA workflows. 

The "In this section" list on a Service landing page is maintained automatically by the **Service page insert** and **Service page update** ECA workflows.

When a Service page is created, the insert workflow adds a new entry to the parent landing page. Each entry stores a link to the page, a summary synced from the Description field, and an optional summary override (leave blank to use the Description).

When a Service page is updated, the update workflow moves or refreshes the entry accordingly.

!!! note
    If editing "In this section" field directly on a Service landing page. Manual edits will be overwritten the next time a child Service page is saved.

## Most Popular and Topics

The landing page template splits its listing into two tiers based on **Number of top tasks**:

- **Most Popular** — the first _n_ items are displayed as prominent Top task links in a grid
- **Topics** — remaining items are displayed as link-summary components in a three-column grid

Set Number of top tasks to `0` to disable the Most Popular tier entirely.

## The Services menu

Service pages are configured to appear in the **Services menu** by default. Set the menu position from the Menu settings tab when editing a Service page. This menu drives the Services flyout in the site header.

Manage it at **Structure → Menus → Services**.

## Demo content

The template ships with service sections and child service pages:

Ensure to delete and replace all the demo content.

## Editorial workflow

Service pages and landing pages use the **Basic** content moderation workflow: Draft → Published → Unpublished.

Unpublished content returns a 404 to anonymous users, not a 403 — handled by the **Unpublished 404** ECA workflow.
