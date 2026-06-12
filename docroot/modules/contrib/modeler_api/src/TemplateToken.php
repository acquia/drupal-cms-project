<?php

namespace Drupal\modeler_api;

/**
 * Contains a template token definition for modeler template tokens.
 *
 * A template token definition belongs to a model owner and provides a
 * hierarchical tree of token entries. Each token entry has at minimum a 'name'
 * and 'token' key, optionally a 'value', and may contain recursive 'children'.
 * Plugins can also attach additional data beyond these standard properties,
 * which is preserved in each token's data array.
 *
 * The first level of children underneath each top-level token indicator must
 * declare a 'purpose' key. Currently supported purposes are:
 * - 'select': The branch defines CSS selectors for selecting DOM elements.
 *   Each level may include a 'selector' key containing a CSS selector string
 *   that further narrows the selection within its parent's matched elements.
 * - 'config': The branch defines configuration values.
 *
 * Template tokens are defined in YAML files named
 * MODULE.modeler_api.template_tokens.yml and are discovered by the
 * TemplateTokenPluginManager.
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
 *               selector: '.form-item'
 *               value: 'Form fields'
 * @endcode
 *
 * @see \Drupal\modeler_api\Plugin\TemplateTokenPluginManager
 */
readonly class TemplateToken {

  /**
   * Instantiates a new template token definition.
   *
   * @param string $id
   *   The template token definition ID.
   * @param string $modelOwner
   *   The model owner plugin ID this definition belongs to.
   * @param array $tokens
   *   A hierarchical token tree. Each entry is an associative array with at
   *   minimum 'name' and 'token' keys. Optional keys include 'value',
   *   'purpose' (required on the first level under each indicator),
   *   'selector' (for select-purpose tokens), and 'children' (which is
   *   itself a recursive token tree). Any additional keys provided by the
   *   plugin are preserved as-is.
   * @param string $provider
   *   The module that provides this template token definition.
   */
  public function __construct(
    protected string $id,
    protected string $modelOwner,
    protected array $tokens,
    protected string $provider,
  ) {}

  /**
   * Gets the template token definition ID.
   *
   * @return string
   *   The definition ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets the model owner plugin ID.
   *
   * @return string
   *   The model owner plugin ID.
   */
  public function getModelOwner(): string {
    return $this->modelOwner;
  }

  /**
   * Gets the full token tree.
   *
   * Returns the hierarchical token tree as provided by the plugin, including
   * all additional data. Each entry contains at minimum 'name' and 'token',
   * and may contain 'value', 'children', and any plugin-specific extra data.
   *
   * @return array
   *   The token tree.
   */
  public function getTokens(): array {
    return $this->tokens;
  }

  /**
   * Gets the provider module name.
   *
   * @return string
   *   The module that provides this template token definition.
   */
  public function getProvider(): string {
    return $this->provider;
  }

  /**
   * Checks if a token exists at a given path.
   *
   * The path uses colon-separated segments to navigate the tree hierarchy.
   * For example, 'config:timeout' looks for a 'timeout' child under the
   * token whose key is 'config'.
   *
   * @param string $path
   *   A colon-separated token path (e.g. 'config:timeout').
   *
   * @return bool
   *   TRUE if the token exists at the given path, FALSE otherwise.
   */
  public function hasToken(string $path): bool {
    return $this->findToken($path) !== NULL;
  }

  /**
   * Finds a token entry at a given path.
   *
   * Navigates the token tree using colon-separated path segments. The first
   * segment matches a top-level token key, subsequent segments navigate into
   * children.
   *
   * @param string $path
   *   A colon-separated token path (e.g. 'config:timeout').
   *
   * @return array|null
   *   The token data array if found, or NULL if the path does not exist.
   */
  public function findToken(string $path): ?array {
    $segments = explode(':', $path);
    $current = $this->tokens;

    foreach ($segments as $segment) {
      if (!isset($current[$segment])) {
        return NULL;
      }
      $entry = $current[$segment];
      // For subsequent segments, navigate into children.
      if ($segment !== end($segments)) {
        $current = $entry['children'] ?? [];
      }
      else {
        return $entry;
      }
    }
    return NULL;
  }

  /**
   * Resolves the purpose for a given token path.
   *
   * The purpose is inherited from the first level underneath each top-level
   * token indicator. For example, given a path 'eca-template:select:form',
   * the purpose is determined by the 'select' node (first child of the
   * 'eca-template' indicator).
   *
   * @param string $path
   *   A colon-separated token path.
   *
   * @return string|null
   *   The purpose string (e.g. 'select', 'config'), or NULL if the path is
   *   invalid or no purpose is defined.
   */
  public function resolvePurpose(string $path): ?string {
    $segments = explode(':', $path);
    if (count($segments) < 2) {
      return NULL;
    }
    // Navigate to the indicator (first segment).
    $indicator = $this->tokens[$segments[0]] ?? NULL;
    if ($indicator === NULL) {
      return NULL;
    }
    // The purpose is on the first child level (second segment).
    $purposeNode = $indicator['children'][$segments[1]] ?? NULL;
    return $purposeNode['purpose'] ?? NULL;
  }

  /**
   * Collects the CSS selector chain for a given token path.
   *
   * For select-purpose tokens, each level may define a 'selector' key. This
   * method walks the path and collects all selectors in order from the
   * purpose node down to the target node.
   *
   * @param string $path
   *   A colon-separated token path.
   *
   * @return string[]
   *   An ordered array of CSS selector strings from the purpose node down
   *   to the target, or an empty array if the path is invalid or has no
   *   selectors.
   */
  public function collectSelectors(string $path): array {
    $segments = explode(':', $path);
    if (count($segments) < 2) {
      return [];
    }

    $selectors = [];
    $current = $this->tokens;

    foreach ($segments as $i => $segment) {
      if (!isset($current[$segment])) {
        return [];
      }
      $entry = $current[$segment];
      // Collect selectors starting from the purpose node (index 1) onward.
      if ($i >= 1 && !empty($entry['selector'])) {
        $selectors[] = $entry['selector'];
      }
      $current = $entry['children'] ?? [];
    }
    return $selectors;
  }

}
