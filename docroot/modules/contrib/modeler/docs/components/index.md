# Components

Components are the building blocks of a workflow model. They represent events,
actions, conditions, and other elements that define what the workflow does and
how it flows.

## Component types

| Type | Description | Visual shape |
|------|-------------|--------------|
| **Event** | Triggers that start a workflow (e.g., "Content is created", "Cron runs"). | Rounded rectangle |
| **Action** | Tasks that perform work (e.g., "Send email", "Set field value"). | Rectangle |
| **Condition** | Logic that evaluates to true/false, attached to edges. | Displayed as edge labels |
| **Gateway** | Decision points for complex branching. | Diamond |
| **Subprocess** | References to other workflow models. | Double-bordered rectangle |
| **Placeholder** | Temporary node awaiting action/gateway assignment (condition-first authoring). | Dashed amber rectangle |

## How components are provided

The available components depend on the **model owner** module. For example:

- **ECA** provides events from Drupal core and contributed modules (entity
  events, cron, form events, etc.), actions from Drupal's action system, and
  conditions for evaluating entity state, user roles, and more.
- Other model owners may provide their own sets of components.

## Component properties

Every component has:

- **Plugin ID**: A unique identifier (e.g., `eca_entity:entity_create`).
- **Label**: A human-readable name shown on the canvas.
- **Component type**: An integer constant identifying the kind of component
  (1 = start/event, 4 = element/action, 5 = link/condition, 6 = gateway,
  2 = subprocess). The backend also provides a `typeMap` that maps these
  integers to string names (`start`, `element`, `link`, `gateway`,
  `subprocess`).
- **Configuration**: Plugin-specific settings edited via the Property Panel.
- **Provider**: The Drupal module that provides the component.
- **Documentation URL**: An optional link to external documentation.

The display labels for component types (e.g., "Event" vs. "Trigger", "Action"
vs. "Task") are provided by the model owner module and can differ between
integrations.

## Sections

- [Nodes](nodes.md) -- events, actions, gateways, subprocesses, and placeholder nodes
- [Edges & Conditions](edges.md) -- connections and conditional logic
- [Configuration Forms](configuration.md) -- configuring component settings
