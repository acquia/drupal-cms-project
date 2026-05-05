# Config Schema

The Modeler API defines both YAML config schema (for Drupal's configuration
system) and JSON schemas (for API responses from the list builders).

## YAML config schema

Located in `config/schema/modeler_api.schema.yml`.

### `modeler_api.settings`

Global settings for Model Owner / Modeler combinations:

```yaml
modeler_api.settings:
  type: config_object
  label: 'Modeler API settings'
  mapping:
    owner_modeler:
      type: sequence
      label: 'Owner IDs'
      sequence:
        type: sequence
        label: 'Modeler IDs'
        sequence:
          type: mapping
          mapping:
            theme:
              type: string
              label: 'Theme'
            storage:
              type: string
              label: 'Storage Mode'
              constraints:
                Choice:
                  callback: '\Drupal\modeler_api\Form\Settings::validStorageOptions'
```

The `owner_modeler` structure is a nested map: `owner_id -> modeler_id ->
{theme, storage}`. This allows configuring different themes and storage methods
for each owner/modeler combination.

**Storage options** (defined in `Form\Settings`):

| Constant | Value | Description |
|----------|-------|-------------|
| `STORAGE_OPTION_THIRD_PARTY` | `third_party` | Store raw data in config entity third-party settings |
| `STORAGE_OPTION_SEPARATE` | `separate` | Store raw data in a separate `DataModel` config entity |
| `STORAGE_OPTION_NONE` | `none` | Don't store raw data |

### `modeler_api.data_model.*`

Schema for the `DataModel` config entity used by separate storage:

```yaml
modeler_api.data_model.*:
  type: config_entity
  label: Model
  mapping:
    id:
      type: string
      label: ID
    data:
      type: string
      label: Data
```

### `model_settings.third_party.modeler_api`

Schema for the third-party settings that the Modeler API stores on each model
config entity:

```yaml
model_settings.third_party.modeler_api:
  type: mapping
  mapping:
    modeler_id:
      type: string
      label: ID
    storage:
      type: string
      label: 'Override system-wide storage setting'
    data:
      type: string
      label: 'Raw data, or an md5 hash of the raw data if stored externally'
    changelog:
      type: text
      label: Changelog
    label:
      type: label
      label: Label
    documentation:
      type: text
      label: Documentation
    tags:
      type: sequence
      label: Tags
      sequence:
        type: string
        label: Tag
    version:
      type: string
      label: Version
    annotations:
      type: sequence
      label: Annotations
      sequence:
        type: mapping
        label: Annotation
        mapping:
          text:
            type: label
            label: Text
          assigned_to:
            type: sequence
            label: 'Assigned to'
            sequence:
              type: string
              label: 'Target ID'
    colors:
      type: sequence
      label: Colors
      sequence:
        type: mapping
        label: Color
        mapping:
          fill:
            type: string
            label: 'Fill color'
          stroke:
            type: string
            label: 'Stroke color'
    swimlanes:
      type: sequence
      label: Swimlanes
      sequence:
        type: mapping
        label: Swimlane
        mapping:
          name:
            type: string
            label: Name
          components:
            type: sequence
            label: Components
```

## JSON schemas

The module provides three JSON schemas for the output of its list builder
services. These are located in `config/schema/`.

### Context list (`context_list.schema.json`)

Defines the structure returned by `ContextListBuilder::build()`:

```json
{
  "type": "array",
  "items": {
    "type": "object",
    "required": ["id", "topic", "model_owner", "components"],
    "properties": {
      "id": {"type": "string"},
      "topic": {"type": "string"},
      "model_owner": {"type": "string"},
      "components": {
        "type": "object",
        "propertyNames": {
          "enum": ["start", "subprocess", "swimlane", "element",
                   "link", "gateway", "annotation"]
        },
        "patternProperties": {
          "^(start|subprocess|...)$": {
            "type": "object",
            "required": ["plugins"],
            "properties": {
              "plugins": {
                "type": "array",
                "items": {"type": "string"},
                "uniqueItems": true
              }
            }
          }
        }
      }
    }
  }
}
```

### Dependency list (`dependency_list.schema.json`)

Defines the structure returned by `DependencyListBuilder::build()`:

```json
{
  "type": "object",
  "propertyNames": {
    "enum": ["start", "subprocess", "swimlane", "element",
             "link", "gateway", "annotation"]
  },
  "patternProperties": {
    "^(start|subprocess|...)$": {
      "type": "object",
      "additionalProperties": {
        "type": "array",
        "items": {
          "type": "object",
          "required": ["type", "id"],
          "properties": {
            "type": {"type": "string", "enum": ["start", "..."]},
            "id": {"type": "string"}
          }
        },
        "minItems": 1
      }
    }
  }
}
```

### Template token list (`template_token_list.schema.json`)

Defines the recursive tree structure returned by
`TemplateTokenListBuilder::build()`:

```json
{
  "type": "object",
  "additionalProperties": {
    "$ref": "#/definitions/token_entry"
  },
  "definitions": {
    "token_entry": {
      "type": "object",
      "required": ["name", "token", "raw token"],
      "properties": {
        "name": {"type": "string"},
        "token": {"type": "string"},
        "raw token": {"type": "string"},
        "value": {"type": "string"},
        "children": {
          "type": "object",
          "additionalProperties": {
            "$ref": "#/definitions/token_entry"
          }
        }
      }
    }
  }
}
```

### Template token source (`template_token_source.schema.json`)

Validates `MODULE.modeler_api.template_tokens.yml` source files. It enforces
a three-level type hierarchy where first-level children under each indicator
must declare a `purpose` (`select` or `config`):

```json
{
  "definitions": {
    "indicator_token": {
      "properties": {
        "name": {"type": "string"},
        "token": {"type": "string"},
        "children": {
          "additionalProperties": {"$ref": "#/definitions/purpose_token"}
        }
      }
    },
    "purpose_token": {
      "required": ["purpose"],
      "properties": {
        "purpose": {"type": "string", "enum": ["select", "config"]},
        "selector": {"type": "string"},
        "target": {"type": "string"},
        "children": {
          "additionalProperties": {"$ref": "#/definitions/token_entry"}
        }
      }
    },
    "token_entry": {
      "properties": {
        "name": {"type": "string"},
        "token": {"type": "string"},
        "value": {"type": "string"},
        "selector": {"type": "string"},
        "target": {"type": "string"},
        "children": {
          "additionalProperties": {"$ref": "#/definitions/token_entry"}
        }
      }
    }
  }
}
```

Key properties at the purpose and token entry levels:

| Property | Type | Description |
|----------|------|-------------|
| `purpose` | `string` | Required on first-level children. Either `select` (for DOM element selection) or `config` (for configuration values). |
| `selector` | `string` | CSS selector string for select-purpose tokens. Used to build a selector chain. |
| `target` | `string` | CSS selector for identifying target elements within the matched context. |

## Storage method details

The `data` field in third-party settings has a dual purpose:

- When storage is `third_party`: contains the full raw data string.
- When storage is `separate`: contains `hash:<md5>` -- a 37-character string
  starting with `hash:` followed by the MD5 hash of the data stored in the
  corresponding `DataModel` entity.

The `ModelOwnerBase::getModelData()` method transparently handles both cases,
validating the hash when loading from separate storage and cleaning up orphaned
`DataModel` entities on hash mismatch.
