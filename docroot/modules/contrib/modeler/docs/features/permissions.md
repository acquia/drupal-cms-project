# Permissions

The Modeler uses a **granular permission system** to control what each user
can do within the editor. Permissions are provided by the Drupal backend and
enforced in the modeler UI.

## Permission reference

| Permission | Description | When denied |
|------------|-------------|-------------|
| **Edit metadata** | Edit the model's label, description, version, tags, and other properties. | Metadata modal fields are read-only. |
| **Switch context** | Change the active workflow context to filter available components. | The context selector is hidden. |
| **Edit template** | Edit a model that is already marked as a template. | The modeler opens in **full read-only mode** -- no editing of any kind is possible. |
| **Create template** | Mark a model as a template (via the metadata modal). | The "Template" checkbox is hidden in the metadata modal. |
| **Test** | Trigger live test executions from the modeler. | Review flow does not start a live listener (only past replay data, if any, is shown). |
| **Replay** | View past execution replay data. | The **Review flow** button is not shown for event nodes. |

## Default behavior

By default, **all permissions are granted**. A permission is only restricted
when the backend explicitly denies it. This means existing installations
continue to work without any permission configuration -- users have full
access unless an administrator configures restrictions.

## Read-only mode

When the **Edit template** permission is denied and the model is already a
template, the modeler opens in a fully read-only state:

- The canvas is locked -- nodes cannot be moved, added, or deleted.
- Configuration forms are disabled.
- The Save button is hidden.
- The toolbar shows only viewing-related actions (search, zoom, dark mode,
  export, close).

This is useful for allowing users to view and export template models without
being able to modify them.

## Configuring permissions

Permissions are configured in the Drupal backend by the site administrator.
The exact configuration method depends on the model owner module (e.g., ECA)
and the site's role and permission setup. Consult your model owner module's
documentation for details on how to grant or deny modeler permissions per
role.
