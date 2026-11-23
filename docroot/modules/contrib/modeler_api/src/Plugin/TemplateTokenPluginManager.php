<?php

namespace Drupal\modeler_api\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\modeler_api\TemplateToken;

/**
 * Plugin manager for modeler API template tokens.
 *
 * Template tokens are defined in YAML files named
 * MODULE.modeler_api.template_tokens.yml. Each top-level key is a template
 * token definition ID containing a model_owner and a list of tokens. Tokens
 * support recursive children, and plugins may provide additional data alongside
 * the standard token properties.
 *
 * Each first-level child underneath a top-level token indicator must declare
 * a 'purpose' key. Currently supported purposes:
 * - 'select': The branch defines CSS selectors for DOM element selection.
 *   Nodes may include a 'selector' key with a CSS selector string that
 *   narrows the selection incrementally from the parent level.
 * - 'config': The branch provides configuration values.
 *
 * Example YAML:
 * @code
 * my_tokens:
 *   model_owner: my_model_owner
 *   tokens:
 *     my-template:
 *       name: 'My Templates'
 *       token: my-template
 *       children:
 *         config:
 *           name: 'Configuration'
 *           token: 'my-template:config'
 *           purpose: config
 *           children:
 *             timeout:
 *               name: 'Timeout'
 *               token: 'my-template:config:timeout'
 *               value: '30'
 *         select:
 *           name: 'Select elements'
 *           token: 'my-template:select'
 *           purpose: select
 *           selector: 'form'
 *           children:
 *             field:
 *               name: 'Fields'
 *               token: 'my-template:select:field'
 *               selector: '.form-item input'
 *               value: 'Form fields'
 * @endcode
 *
 * @see \Drupal\modeler_api\TemplateToken
 */
class TemplateTokenPluginManager extends DefaultPluginManager {

  /**
   * All template token instances.
   *
   * @var \Drupal\modeler_api\TemplateToken[]
   */
  protected array $allInstances;

  /**
   * Constructs a TemplateTokenPluginManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    CacheBackendInterface $cache_backend,
  ) {
    // Do not call parent::__construct() as that sets up attribute-based
    // discovery. YAML-based discovery is configured here directly.
    $this->moduleHandler = $module_handler;
    $yaml_discovery = new YamlDiscovery('modeler_api.template_tokens', $this->moduleHandler->getModuleDirectories());
    $yaml_discovery->addTranslatableProperty('name');
    $this->discovery = new ContainerDerivativeDiscoveryDecorator($yaml_discovery);
    $this->alterInfo('modeler_api_template_token_info');
    $this->setCacheBackend($cache_backend, 'modeler_api_template_token_plugins', ['modeler_api_template_token_plugins']);
  }

  /**
   * Gets all template token instances.
   *
   * @param bool $reload
   *   If TRUE, force reloading all instances.
   *
   * @return \Drupal\modeler_api\TemplateToken[]
   *   The list of all template token instances, keyed by definition ID.
   */
  public function getAllTemplateTokens(bool $reload = FALSE): array {
    if (!isset($this->allInstances) || $reload) {
      $this->allInstances = [];
      foreach ($this->getDefinitions() as $id => $definition) {
        $templateToken = $this->createTemplateToken($id, $definition);
        if ($templateToken !== NULL) {
          $this->allInstances[$id] = $templateToken;
        }
      }
    }
    return $this->allInstances;
  }

  /**
   * Gets a single template token definition by its ID.
   *
   * @param string $id
   *   The template token definition ID.
   *
   * @return \Drupal\modeler_api\TemplateToken|null
   *   The template token, or NULL if not found.
   */
  public function getTemplateToken(string $id): ?TemplateToken {
    $tokens = $this->getAllTemplateTokens();
    return $tokens[$id] ?? NULL;
  }

  /**
   * Gets all template tokens for a given model owner.
   *
   * @param string $modelOwnerId
   *   The model owner plugin ID.
   *
   * @return \Drupal\modeler_api\TemplateToken[]
   *   The list of template tokens for the given model owner, keyed by ID.
   */
  public function getTemplateTokensByModelOwner(string $modelOwnerId): array {
    $tokens = [];
    foreach ($this->getAllTemplateTokens() as $id => $templateToken) {
      if ($templateToken->getModelOwner() === $modelOwnerId) {
        $tokens[$id] = $templateToken;
      }
    }
    return $tokens;
  }

  /**
   * Creates a TemplateToken object from a plugin definition.
   *
   * @param string $id
   *   The template token definition ID.
   * @param array $definition
   *   The plugin definition from YAML.
   *
   * @return \Drupal\modeler_api\TemplateToken|null
   *   The template token object, or NULL if the definition is invalid.
   */
  protected function createTemplateToken(string $id, array $definition): ?TemplateToken {
    if (empty($definition['model_owner'])) {
      return NULL;
    }

    $tokens = $this->buildTokenTree($definition['tokens'] ?? []);

    return new TemplateToken(
      id: $id,
      modelOwner: $definition['model_owner'],
      tokens: $tokens,
      provider: $definition['provider'] ?? '',
    );
  }

  /**
   * Recursively builds a token tree from a YAML definition.
   *
   * Each token entry is normalized to include at least a 'name' and 'token'
   * key, plus any additional data the plugin provides. Children are processed
   * recursively.
   *
   * @param array $tokensDefinition
   *   The tokens array from the YAML definition.
   *
   * @return array
   *   A normalized token tree where each entry is an associative array with
   *   at minimum 'name' and 'token' keys, and optionally 'value', 'children',
   *   and any additional plugin-defined data.
   */
  protected function buildTokenTree(array $tokensDefinition): array {
    $tokens = [];
    foreach ($tokensDefinition as $key => $tokenData) {
      if (!is_array($tokenData) || empty($tokenData['token'])) {
        continue;
      }
      $entry = $tokenData;
      // Recursively process children.
      if (isset($entry['children']) && is_array($entry['children'])) {
        $entry['children'] = $this->buildTokenTree($entry['children']);
      }
      $tokens[$key] = $entry;
    }
    return $tokens;
  }

}
