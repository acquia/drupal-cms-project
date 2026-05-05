# Plugin Managers

The Modeler API defines five plugin managers. Two use PHP attribute-based
discovery for code-heavy plugins; three use YAML-based discovery for
declarative configuration.

## Attribute-based plugin managers

These manage plugins that are PHP classes annotated with PHP 8 attributes. They
extend Drupal's `DefaultPluginManager` and follow the standard plugin discovery
pattern.

| Plugin Manager | Description | Details |
|---------------|-------------|---------|
| [Model Owner](model-owner/index.md) | Owns config entities that can be modeled | Defines components, storage, CRUD |
| [Modeler](modeler/index.md) | Provides visual editing UI | Renders canvas, parses/serializes data |

Both managers provide an `getAllInstances()` method that returns all available
plugin instances, which is useful for building UI elements like selection lists.

## YAML-based plugin managers

These manage plugins defined in YAML files placed in each module's root
directory. They do not require any PHP code, making them ideal for declarative
metadata that can be contributed by any module.

| Plugin Manager | YAML file pattern | Description | Details |
|---------------|-------------------|-------------|---------|
| [Context](context/index.md) | `MODULE.modeler_api.contexts.yml` | Available components per use case | Defines plugin lists per component type |
| [Plugin Dependency](plugin-dependency/index.md) | `MODULE.modeler_api.dependencies.yml` | Predecessor constraints | Restricts component ordering |
| [Template Token](template-token/index.md) | `MODULE.modeler_api.template_tokens.yml` | Token trees for templates | Recursive key-value tokens |

## Alter hooks

Every plugin manager supports an alter hook that allows other modules to modify
plugin definitions at discovery time:

| Plugin type | Alter hook |
|-------------|-----------|
| Model Owner | `hook_modeler_api_model_owner_info_alter()` |
| Modeler | `hook_modeler_api_modeler_info_alter()` |
| Context | `hook_modeler_api_context_info_alter()` |
| Dependency | `hook_modeler_api_dependency_info_alter()` |
| Template Token | `hook_modeler_api_template_token_info_alter()` |

## Plugin relationship diagram

```
Context ----+
            |
Dependency -+--> Model Owner <----> Modeler
            |
Template ---+
 Token
```

The three YAML-based plugin types always reference a specific Model Owner via
their `model_owner` key. The Modeler, on the other hand, is Model Owner
agnostic -- it works with any Model Owner that it is paired with in the
settings.
