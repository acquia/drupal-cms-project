# Modeler API

The Modeler API is a framework for building visual modelers in Drupal. It
fully decouples *what is being modeled* (Model Owners) from *how it is
modeled* (Modelers), so that any visual editor can work with any module's
configuration entities -- without either side knowing the other exists.

## The problem it solves

Many Drupal modules manage complex configuration entities that benefit from
visual editing: automation workflows, AI agent pipelines, migration mappings,
and more. Building a visual editor for each of these independently is a huge
duplicated effort. And once built, each editor is locked to its own module.

The Modeler API eliminates this duplication. A module that owns complex
configuration (a **Model Owner**) implements one plugin interface. A module
that provides a visual editor (a **Modeler**) implements another. The Modeler
API sits between them as the sole mediator, translating between the two
through a generic component model. Neither side has any knowledge of the
other.

If you have 4 model owners and 3 modelers, you get 12 fully working
combinations -- with zero glue code.

## What the API provides

Integrating a new model owner or a new modeler is straightforward because the
Modeler API handles all the infrastructure that would otherwise need to be
built from scratch:

- **Dynamic routing** -- Up to 18 admin routes per model owner (collection,
  add, edit, delete, enable, disable, clone, import, export, settings, etc.)
  plus 3 additional routes per modeler/owner combination, all generated
  automatically from the plugin definitions.
- **Granular permissions** -- Up to 11 permissions per model owner (administer,
  view collection, edit, delete, view, edit metadata, switch context, test,
  replay, create templates, edit templates) plus 2 per modeler/owner
  combination. All generated dynamically and enforced on every route.
- **Admin UI** -- Entity listing page with operations, local tasks, local
  actions, and menu links, all generated from plugin metadata. No manual menu
  or route configuration needed.
- **Save cycle orchestration** -- The central `Api` service manages the
  complete model save flow: parse raw data from the modeler, extract metadata,
  reset the model owner's components, add each component back, finalize, and
  persist. Neither plugin needs to know how the other stores or represents
  data.
- **Three storage strategies** -- Raw modeler data (XML, JSON, etc.) can be
  stored as third-party settings on the config entity, in a separate config
  entity, or not at all. Configurable per owner/modeler combination, or
  enforced by the model owner.
- **Import and export** -- Export any model as a `.tar.gz` archive with all
  config dependencies resolved, or as a complete Drupal recipe with
  `composer.json`, `recipe.yml`, config files, and installation instructions.
  Import from archive, raw modeler file, or single config YAML.
- **Template system** -- Model owners can mark models as templates. Template
  tokens (defined in YAML by any module) provide placeholder values that are
  resolved at runtime. A Preact-based frontend lets users select DOM elements
  to apply templates to, with the results routed back through model owners.
- **Context and dependency metadata** -- Any module can contribute
  YAML-based plugins that curate which components appear in the modeler UI
  (contexts), restrict valid component orderings (dependencies), or define
  template token trees -- all without writing PHP.
- **Testing and replay** -- Model owners can opt in to in-modeler testing
  (start/poll async test jobs) and execution replay (load trace data per
  component), with the API providing the endpoints and UI integration.
- **Drush commands** -- Bulk update all models, enable/disable all models for
  an owner, or export a model as a recipe from the command line.

## Architecture

![Architecture](docs/assets/architecture-glance.svg)

The Modeler API exposes two separate plugin interfaces:

- **`ModelOwnerInterface`** -- for modules that own configuration entities.
  The model owner defines what components exist (events, actions, conditions,
  gateways, etc.), how they map to Drupal plugins, how the config entity is
  structured, and how models are saved.
- **`ModelerInterface`** -- for modules that provide a visual editor. The
  modeler knows how to render a canvas, parse its native format (XML, JSON,
  etc.) into generic `Component` value objects, and serialize changes back.

All data flows through the `Api` service, which translates between the two
sides using 7 generic component types: start, subprocess, swimlane, element,
link, gateway, and annotation. The config entity and the raw modeler data
never touch each other directly.

## Plugin system

The module provides 5 plugin types:

| Plugin type        | Discovery                                       | Purpose                                                     |
|--------------------|-------------------------------------------------|-------------------------------------------------------------|
| **Model Owner**    | PHP attribute `#[ModelOwner]`                   | Owns config entities, defines components, manages storage   |
| **Modeler**        | PHP attribute `#[Modeler]`                      | Provides visual editor UI, parses and serializes model data |
| **Context**        | YAML (`MODULE.modeler_api.contexts.yml`)        | Curates which components appear per use case                |
| **Dependency**     | YAML (`MODULE.modeler_api.dependencies.yml`)    | Constrains valid component orderings                        |
| **Template Token** | YAML (`MODULE.modeler_api.template_tokens.yml`) | Defines token trees for model templates                     |

All five plugin types support alter hooks for programmatic modifications by
other modules.

## Known model owners

- [ECA](https://www.drupal.org/project/eca) (via `eca_ui`) -- Automation
  workflows with events, actions, conditions, and gateways. Supports status,
  templates, testing, and execution replay.
- [AI Agents](https://www.drupal.org/project/ai_agents) -- AI agent
  configuration with sub-agents and function call tools. Uses enforced
  storage and the `ComponentWrapperPlugin` for non-plugin components.

## Known modelers

- [BPMN.iO](https://www.drupal.org/project/bpmn_io) -- BPMN 2.0 editor
  using the bpmn.js library. XML format, off-canvas configuration forms, SVG
  export, minimap, search, copy/paste, and auto-layout.
- [Workflow Modeler](https://www.drupal.org/project/modeler) -- Lightweight
  editor using React Flow. JSON format, JSON-based configuration forms,
  context switching.

Any model owner works with any modeler. Users can switch between modelers at
any time without losing configuration data.

## Requirements

- Drupal `^11.3`
- PHP `>=8.3`
- Optional: `drupal/token` for enhanced template token support

## Installation

Install as you would any Drupal module:

```
composer require drupal/modeler_api
drush en modeler_api
```

The module does nothing on its own -- you need at least one model owner and
one modeler. For ECA workflows with the BPMN editor:

```
composer require drupal/eca drupal/bpmn_io
drush en eca_ui bpmn_io
```

## Configuration

After installation, visit **Administration > Configuration > Workflow >
Modeler API** (`/admin/config/workflow/modeler_api`) to configure which
modeler and storage method to use for each model owner.

## Documentation

Full developer documentation is available at the
[Modeler API documentation site](https://project.pages.drupalcode.org/modeler_api/),
covering architecture, all plugin types, the API reference, and step-by-step
implementation guides.

## Community

[Slack: #modeler-api](https://drupal.slack.com/archives/C08K6KX2EHH)
