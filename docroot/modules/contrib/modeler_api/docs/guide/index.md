# Implementation Guide

This section provides step-by-step guides for implementing each plugin type
provided by the Modeler API.

## Guides

- [Creating a Model Owner](creating-model-owner.md) -- implement a plugin that
  integrates your config entities with visual modelers
- [Creating a Modeler](creating-modeler.md) -- implement a plugin that provides
  a visual editing experience
- [YAML Plugin Definitions](yaml-plugins.md) -- define contexts, dependencies,
  and template tokens without writing PHP

## Prerequisites

Before implementing any Modeler API plugin, ensure:

1. Your module depends on `modeler_api`:

    ```yaml
    # my_module.info.yml
    dependencies:
      - modeler_api:modeler_api
    ```

2. You are familiar with [Drupal's plugin system](https://www.drupal.org/docs/drupal-apis/plugin-api).

3. You understand the [Architecture](../architecture/index.md) of the Modeler
   API, particularly the relationship between Model Owners and Modelers.

## Which plugin type do you need?

| I want to...                             | Plugin type           | Guide                                             |
|------------------------------------------|-----------------------|---------------------------------------------------|
| Make my config entities visual           | Model Owner           | [Creating a Model Owner](creating-model-owner.md) |
| Build a new visual editor                | Modeler               | [Creating a Modeler](creating-modeler.md)         |
| Curate component lists for a model owner | Context (YAML)        | [YAML Plugins](yaml-plugins.md#contexts)          |
| Restrict component ordering              | Dependency (YAML)     | [YAML Plugins](yaml-plugins.md#dependencies)      |
| Add template tokens                      | Template Token (YAML) | [YAML Plugins](yaml-plugins.md#template-tokens)   |

## Existing implementations for reference

| Module               | Plugin type              | Plugin ID          | Key patterns                                         |
|----------------------|--------------------------|--------------------|------------------------------------------------------|
| `bpmn_io`            | Modeler                  | `bpmn_io`          | XML parsing, BPMN canvas, off-canvas config forms    |
| `modeler`            | Modeler                  | `workflow_modeler` | JSON format, React Flow UI, JSON config forms        |
| `ai_agents`          | Model Owner              | `ai_agents_agent`  | ComponentWrapperPlugin, enforced storage, sub-models |
| `eca` (via `eca_ui`) | Model Owner              | `eca`              | Full-featured: status, templates, testing, replay    |
| `eca_ng`             | Context/Dependency/Token | N/A                | YAML-only definitions for all three types            |
