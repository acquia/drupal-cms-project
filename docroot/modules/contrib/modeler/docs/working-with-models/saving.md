# Saving & Closing

## Saving your model

Click the **Save** button in the toolbar to persist your model. The save
process:

1. Retrieves a CSRF token from the server for security.
2. Serializes the model data (nodes, edges, metadata) to JSON.
3. Sends the data to the backend via the Modeler API.
4. The model owner (e.g., ECA) converts the model to its internal configuration
   format.

The Save button is **disabled** when there are no unsaved changes.

### Pre-save validation

Before saving, the modeler checks for **placeholder nodes** on the canvas.
If any exist, the save is blocked and an error message is displayed:

> *Cannot save: N placeholder node(s) still need an action or gateway
> assigned. Please replace all placeholder nodes before saving.*

This validation also applies when you attempt to:

- **Review** the model (starting a review session is blocked).
- **Close** the modeler via "Save and Close" (the save step is blocked).
- **Export** with unsaved changes (the "Save first" step is blocked).

Replace all placeholder nodes with real actions or gateways to proceed.

### Unsaved changes indicator

A dot appears next to the model title in the toolbar when you have unsaved
changes. This helps you keep track of whether your work has been persisted.

## Closing the modeler

Click the **Close** button (X) in the toolbar to leave the modeler and return
to the model list.

### With unsaved changes

If you have unsaved changes, a **confirmation dialog** appears with three
options:

| Button | Action |
|--------|--------|
| **Save and Close** | Saves the model and then returns to the list. |
| **Close Without Saving** | Discards all changes since the last save and returns to the list. |
| **Cancel** | Stays in the modeler and continues editing. |

### Without unsaved changes

If everything is saved, clicking Close returns you to the model list immediately.

### Stay-in-context behavior

In some situations (e.g., when the modeler is opened within a dialog or side
panel), the close button minimizes the modeler instead of navigating away.
This depends on how the model owner integrates the modeler.

## Auto-save

The modeler does not auto-save. You must explicitly click Save or use the
Save and Close dialog to persist your work.

!!! warning
    If you close the browser tab or navigate away without saving, your changes
    will be lost. The browser may show a confirmation prompt, but this depends
    on browser settings.

## Exporting

In addition to saving, you can **export** your model in multiple formats for
backup, sharing, or embedding. Click the Export button in the toolbar to
access the export dialog.

See [Export](../features/export.md) for details on available export formats.
