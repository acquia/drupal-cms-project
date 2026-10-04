# Drush Commands

The Modeler API provides Drush commands for common model management operations.

**Class:** `Drupal\modeler_api\Drush\Commands\ModelerApiCommands`

## Available commands

### `modeler_api:update`

Updates all existing models by checking if any raw model data or owner
component definitions need to be refreshed.

```bash
drush modeler_api:update
```

This iterates over all Model Owner plugins, loads each model's raw data,
parses it through the Modeler, and calls
`ModelerInterface::updateComponents()`. If updates are detected, the model is
re-saved.

### `modeler_api:disable`

Disables a model by ID.

```bash
drush modeler_api:disable <model_id>
```

**Arguments:**

| Argument | Description |
|----------|-------------|
| `model_id` | The config entity ID of the model to disable |

### `modeler_api:enable`

Enables a model by ID.

```bash
drush modeler_api:enable <model_id>
```

**Arguments:**

| Argument | Description |
|----------|-------------|
| `model_id` | The config entity ID of the model to enable |

### `modeler_api:model:export`

Exports a model as a `.tar.gz` archive.

```bash
drush modeler_api:model:export <model_id> [<destination>]
```

**Arguments:**

| Argument | Description |
|----------|-------------|
| `model_id` | The config entity ID of the model to export |
| `destination` | Optional file path for the archive (defaults to the current directory) |

## ExportRecipe service

Beyond Drush commands, the `modeler_api.export.recipe` service
(`Drupal\modeler_api\ExportRecipe`) can export models as **Drupal recipes**.

A recipe export includes:

| File | Description |
|------|-------------|
| `recipe.yml` | Recipe manifest with name, description, and config import/actions |
| `composer.json` | Composer metadata for the recipe |
| `README.md` | Human-readable description |
| `config/*.yml` | Configuration files for the model and its dependencies |

The recipe export is available via the UI form at
`/{basePath}/{id}/export_recipe` or can be triggered programmatically:

```php
/** @var \Drupal\modeler_api\ExportRecipe $exporter */
$exporter = \Drupal::service('modeler_api.export.recipe');
$exporter->export($owner, $model, '/path/to/output');
```
