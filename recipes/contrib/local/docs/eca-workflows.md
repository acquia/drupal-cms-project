---
title: ECA workflows
---

# ECA workflows

Local uses [ECA (Event-Condition-Action)](https://www.drupal.org/project/eca) to automate site behaviours without custom code. Workflows are configured visually using the BPMN.io modeller.

Advanced users can manage the workflows at **Configuration → Workflow → ECA**.

## Custom workflows for the Local Site Template

**Service page insert** — When a Service page is created, adds an entry to the parent Service landing page's section listing automatically.

**Service page update** — When a Service page is updated or its parent changes, moves or refreshes its listing entry on the correct landing page.

## Included workflows from Drupal CMS

**Auth redirects** — Sends logged-in users away from `/user/login` and `/user/register` to the front page.

**Content duplicate** — Adds a Duplicate action to nodes, letting editors clone a page as a starting point.

**Content template disable preview** — Disables the Drupal preview button on content types where Canvas is used, avoiding confusion between the two editors.

**Define breakpoints** — Fires on install to set the responsive breakpoint configuration for the theme.

**Grant media type permissions** — Fires on install to grant the editorial role appropriate permissions for each media type.

**Privacy setting link** — Generates a dynamic link to the Klaro cookie settings panel and injects it into the footer menu.

**Remote video consent** — Wraps remote video embeds (YouTube, Vimeo) in Klaro consent management so they do not load until the user has consented.

**Search exclude** — Marks utility pages as excluded from Search API indexing.

**Unpublished 404** — Ensures unpublished content returns a 404 to anonymous users rather than a 403.

## Editing workflows

Workflows should never be edited directly on a Production site. 

Instead, use Drupal's configuration management and a testing and deployment process.

Open a workflow at **Configuration → Workflow → ECA**, then click **Edit in modeller**. The BPMN.io modeller shows the workflow as a flow diagram. Changes take effect immediately on save.

## Further reading

- [ECA module documentation](https://www.drupal.org/docs/contributed-modules/eca-event-condition-action)
