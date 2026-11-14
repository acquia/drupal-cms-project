<?php

namespace Drupal\modeler_api;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\modeler_api\Plugin\TemplateTokenPluginManager;

/**
 * Builds merged template token lists with an accompanying JSON schema.
 *
 * This service provides a merged token tree for a given model owner in the
 * same format as the sample.json used for template tokens. Multiple template
 * token definitions from different modules are merged into a single tree. The
 * output is an object keyed by token ID, where each entry contains 'name',
 * 'token', 'raw token', optionally 'value', optionally recursive 'children',
 * and any additional plugin-provided data.
 *
 * A JSON schema definition is shipped as a file at
 * config/schema/template_token_list.schema.json and can be retrieved through
 * this service so that consumers can understand and validate the data
 * structure.
 */
class TemplateTokenListBuilder {

  /**
   * The JSON schema file path relative to the module directory.
   */
  protected const string SCHEMA_FILE = 'config/schema/template_token_list.schema.json';

  /**
   * Constructs a TemplateTokenListBuilder.
   *
   * @param \Drupal\modeler_api\Plugin\TemplateTokenPluginManager $templateTokenPluginManager
   *   The template token plugin manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected TemplateTokenPluginManager $templateTokenPluginManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Gets the merged template token tree for a given model owner.
   *
   * All template token definitions for the model owner are merged into a
   * single tree. The output format matches the sample.json structure: an
   * object keyed by a prefixed token ID (e.g. 'template-author'), where each
   * entry contains:
   * - name: The human-readable token name.
   * - token: The token identifier.
   * - raw token: The token wrapped in template syntax, e.g.
   *   '[template:author]'.
   * - value: (optional) A sample or default value.
   * - purpose: (on first level under each indicator) The token branch
   *   purpose, e.g. 'select' or 'config'.
   * - selector: (optional, for select-purpose tokens) A CSS selector string
   *   that incrementally narrows the DOM selection from the parent level.
   * - children: (optional) Recursive child tokens in the same format.
   * - Any additional data provided by the plugin.
   *
   * @param string $modelOwnerId
   *   The model owner plugin ID.
   * @param string $prefix
   *   The prefix for the top-level keys (default: 'template').
   * @param string $rawTokenWrapper
   *   The wrapper format for raw tokens. Must contain two '%s' placeholders:
   *   the first for the prefix, the second for the token value.
   *   Default: '[%s:%s]'.
   *
   * @return array
   *   The merged token tree.
   */
  public function getList(string $modelOwnerId, string $prefix = 'template', string $rawTokenWrapper = '[%s:%s]'): array {
    $merged = [];
    foreach ($this->templateTokenPluginManager->getTemplateTokensByModelOwner($modelOwnerId) as $templateToken) {
      $this->mergeTokenTree($merged, $templateToken->getTokens(), $prefix, $rawTokenWrapper);
    }
    return $merged;
  }

  /**
   * Recursively merges a token tree into the target array.
   *
   * @param array $target
   *   The target array to merge into (passed by reference).
   * @param array $tokens
   *   The source token tree to merge from.
   * @param string $prefix
   *   The prefix for generating keys and raw tokens.
   * @param string $rawTokenWrapper
   *   The wrapper format for raw tokens.
   */
  protected function mergeTokenTree(array &$target, array $tokens, string $prefix, string $rawTokenWrapper): void {
    foreach ($tokens as $key => $tokenData) {
      $tokenId = $prefix . '-' . $key;
      $tokenValue = $tokenData['token'] ?? $key;

      // Build the entry with standard properties first.
      $entry = [];
      // Copy all data from the plugin definition preserving additional data.
      foreach ($tokenData as $dataKey => $dataValue) {
        if ($dataKey === 'children') {
          continue;
        }
        $entry[$dataKey] = $dataValue;
      }
      // Ensure standard properties are set.
      if (!isset($entry['name'])) {
        $entry['name'] = (string) ($tokenData['name'] ?? $key);
      }
      else {
        $entry['name'] = (string) $entry['name'];
      }
      $entry['token'] = $tokenValue;
      $entry['raw token'] = sprintf($rawTokenWrapper, $prefix, $tokenValue);

      // Handle children recursively.
      if (!empty($tokenData['children'])) {
        $children = $target[$tokenId]['children'] ?? [];
        $this->mergeTokenTree($children, $tokenData['children'], $prefix, $rawTokenWrapper);
        $entry['children'] = $children;
      }

      // Merge with existing entry if present (later plugins can extend).
      if (isset($target[$tokenId])) {
        $target[$tokenId] = array_merge($target[$tokenId], $entry);
      }
      else {
        $target[$tokenId] = $entry;
      }
    }
  }

  /**
   * Gets the absolute file path to the JSON schema.
   *
   * @return string
   *   The absolute path to the template_token_list.schema.json file.
   */
  public function getJsonSchemaPath(): string {
    return $this->moduleHandler->getModule('modeler_api')->getPath() . '/' . self::SCHEMA_FILE;
  }

  /**
   * Gets the JSON schema that describes the structure of the token list.
   *
   * The schema follows the JSON Schema draft-07 specification and describes
   * the structure returned by getList(). The schema is loaded from
   * config/schema/template_token_list.schema.json.
   *
   * @return array
   *   The JSON schema as an associative array.
   */
  public function getJsonSchema(): array {
    return json_decode(file_get_contents($this->getJsonSchemaPath()), TRUE);
  }

}
