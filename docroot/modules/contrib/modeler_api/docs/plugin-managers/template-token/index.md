# Template Token Plugin Manager

The Template Token plugin manager discovers YAML-based plugins that provide
hierarchical token trees for use in model templates. Template tokens allow
model templates to contain placeholder values that are replaced when the
template is instantiated.

## Plugin manager details

| Property | Value |
|----------|-------|
| **Service ID** | `plugin.manager.modeler_api.template_token` |
| **Class** | `Drupal\modeler_api\Plugin\TemplateTokenPluginManager` |
| **Discovery** | YAML file (`MODULE.modeler_api.template_tokens.yml`) |
| **Value object** | `Drupal\modeler_api\TemplateToken` |
| **Alter hook** | `hook_modeler_api_template_token_info_alter()` |
| **Cache tag** | `modeler_api_template_token_plugins` |

## YAML file structure

Create a file named `my_module.modeler_api.template_tokens.yml` in your
module's root directory:

```yaml
my_tokens:
  model_owner: my_owner_plugin_id
  tokens:
    my-token-group:
      name: 'My Token Group'
      token: my-token-group
      children:
        sub-token:
          name: 'Sub Token'
          token: 'my-token-group:sub-token'
          value: 'Default value'
          children:
            leaf:
              name: 'Leaf Token'
              token: 'my-token-group:sub-token:leaf'
              value: 'Leaf value'
```

### Top-level keys

Each top-level key defines a token set. Multiple sets can be defined in a
single file and will be merged by the list builder.

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `model_owner` | `string` | Yes | Plugin ID of the Model Owner |
| `tokens` | `object` | Yes | Token tree definition |

### Token entry structure

Each token entry has the following properties:

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `name` | `string` | Yes | Human-readable name (translatable) |
| `token` | `string` | Yes | Token identifier used in expressions |
| `value` | `string` | No | Sample or default value |
| `purpose` | `string` | Conditional | Required on first-level children under each indicator. Either `select` or `config`. |
| `selector` | `string` | No | CSS selector string for select-purpose tokens. Collected into a selector chain. |
| `target` | `string` | No | CSS selector for identifying target elements within the matched context. |
| `children` | `object` | No | Nested child tokens (same structure, recursive) |

### Token purposes

First-level children under each top-level indicator (e.g. `eca-template`)
must declare a `purpose`:

| Purpose | Description |
|---------|-------------|
| `select` | Defines a CSS selector chain for DOM element selection. The Preact frontend highlights matching elements. |
| `config` | Provides configuration values passed through without DOM interaction. |

The purpose is inherited by all descendants. The `TemplateToken::resolvePurpose()`
method determines the purpose for any given token path.

Tokens use a **colon-separated path** convention. For example, a token at
`eca-template:config:global:value:VALUE` represents a leaf in the tree:

```
eca-template
  └── config
       └── global
            └── value
                 └── VALUE  →  "Configurable value"
```

## Token resolution

The `TemplateToken::findToken()` method navigates the token tree using a
colon-separated path:

```php
$templateToken = $tokenManager->getTemplateToken('eca');
$found = $templateToken->findToken('eca-template:config:global:value:VALUE');
// Returns the token entry array with 'name', 'token', 'value', etc.
```

The `hasToken()` method checks for existence without returning the full entry.

## TemplateToken value object

The `Drupal\modeler_api\TemplateToken` class is a readonly value object:

| Method | Return | Description |
|--------|--------|-------------|
| `getId()` | `string` | Token set ID |
| `getModelOwner()` | `string` | Model Owner plugin ID |
| `getTokens()` | `array` | Full token tree |
| `getProvider()` | `string` | Module that defined these tokens |
| `hasToken($path)` | `bool` | Check if a token exists at the path |
| `findToken($path)` | `?array` | Find a token entry by colon-separated path |
| `resolvePurpose($path)` | `?string` | Resolve the purpose (`select` or `config`) for a given token path |
| `collectSelectors($path)` | `string[]` | Collect CSS `selector` keys from the purpose node down to the target path |

## TemplateTokenResolver

The `modeler_api.template_token_resolver` service resolves template token
references found in strings during a page request. It is the bridge between
server-side model data and the frontend template token selector UI.

| Method | Return | Description |
|--------|--------|-------------|
| `enabled()` | `bool` | Whether the resolver is active |
| `addToken($value, $modelOwnerId, $modelId, $componentId)` | `$this` | Parse a string for token references and resolve them |
| `addConfig($key, $value, $modelOwnerId, $modelId, $componentId)` | `$this` | Attach hidden config key/value to an object |
| `addLabel($label, $modelOwnerId, $modelId, $componentId)` | `$this` | Attach a human-readable label to an object |
| `addAppliedTemplate($modelOwnerId, $componentId, $target, $hiddenConfig, $config)` | `$this` | Record a previously applied template |
| `hasTokens()` | `bool` | Whether any tokens were resolved |
| `getEntries()` | `array` | Raw resolved entries keyed by composite key |
| `resolve()` | `array` | Final output with hidden config and labels merged |
| `reset()` | `$this` | Clear all state |
| `getAttachments(&$attachments)` | `void` | Attach library and resolved data to a render array |

### Token label support

Token paths can include trailing segments beyond the deepest matching node.
These extra segments are captured as a `token_label`. For example:

```
[template:eca-template:select:form:field:type:text:First name]
```

Resolves to the `text` node with `token_label` set to `First name`.

## TemplateTokenPluginManager API

| Method | Return | Description |
|--------|--------|-------------|
| `getAllTemplateTokens($reload)` | `TemplateToken[]` | All discovered token sets |
| `getTemplateToken($id)` | `?TemplateToken` | A single token set by ID |
| `getTemplateTokensByModelOwner($modelOwnerId)` | `TemplateToken[]` | All tokens for a Model Owner |

## TemplateTokenListBuilder

The `modeler_api.template_token_list_builder` service merges all token trees
for a given Model Owner into a single unified tree. The output conforms to
`template_token_list.schema.json`.

```php
/** @var \Drupal\modeler_api\TemplateTokenListBuilder $listBuilder */
$listBuilder = \Drupal::service('modeler_api.template_token_list_builder');
$mergedTree = $listBuilder->build('eca');
```

### JSON schema

The merged output follows `config/schema/template_token_list.schema.json`:

```json
{
  "eca-template": {
    "name": "ECA Templates",
    "token": "eca-template",
    "raw token": "[template:eca-template]",
    "children": {
      "config": {
        "name": "Configuration",
        "token": "eca-template:config",
        "raw token": "[template:eca-template:config]",
        "children": {
          "global": {
            "name": "Global",
            "token": "eca-template:config:global",
            "raw token": "[template:eca-template:config:global]",
            "children": {
              "value": {
                "name": "Value",
                "token": "eca-template:config:global:value",
                "raw token": "[template:eca-template:config:global:value]",
                "children": {
                  "VALUE": {
                    "name": "Value",
                    "token": "eca-template:config:global:value:VALUE",
                    "raw token": "[template:eca-template:config:global:value:VALUE]",
                    "value": "Configurable value"
                  }
                }
              }
            }
          }
        }
      }
    }
  }
}
```

When multiple modules define tokens for the same Model Owner, trees are deep
merged. For example, if `eca_ng` defines both `eca` and `eca_form` token sets,
the builder merges them into a single tree under their shared root paths.

## Complete example

From the `eca_ng` module (`eca_ng.modeler_api.template_tokens.yml`):

```yaml
eca:
  model_owner: eca
  tokens:
    eca-template:
      name: 'ECA Templates'
      token: eca-template
      children:
        config:
          name: 'Configuration'
          token: 'eca-template:config'
          children:
            global:
              name: 'Global'
              token: 'eca-template:config:global'
              children:
                value:
                  name: 'Value'
                  token: 'eca-template:config:global:value'
                  children:
                    VALUE:
                      name: 'Value'
                      token: 'eca-template:config:global:value:VALUE'
                      value: 'Configurable value'

eca_form:
  model_owner: eca
  tokens:
    eca-template:
      name: 'ECA Templates'
      token: eca-template
      children:
        config:
          name: 'Configuration'
          token: 'eca-template:config'
          children:
            form:
              name: 'Form'
              token: 'eca-template:config:form'
              children:
                field:
                  name: 'Field'
                  token: 'eca-template:config:form:field'
                  children:
                    LABEL:
                      name: 'Label'
                      token: 'eca-template:config:form:field:LABEL'
                      value: 'Select 1 field'
        select:
          name: 'Select where template applies'
          token: 'eca-template:select'
          children:
            form:
              name: 'Forms'
              token: 'eca-template:select:form'
              children:
                field:
                  name: 'Field'
                  token: 'eca-template:select:form:field'
                  children:
                    all:
                      name: 'All form fields'
                      token: 'eca-template:select:form:field:all'
                      value: 'All form fields'
```

This defines two token sets that the list builder merges into a single tree for
the `eca` Model Owner. The first set provides global configuration tokens; the
second adds form-specific configuration and selection tokens.
