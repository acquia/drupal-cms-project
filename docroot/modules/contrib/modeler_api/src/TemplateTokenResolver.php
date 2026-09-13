<?php

namespace Drupal\modeler_api;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Drupal\modeler_api\Plugin\TemplateTokenPluginManager;

/**
 * Resolves template tokens for the current page.
 *
 * This service allows any module to submit generic strings that may contain
 * template token references for the current page request. Each string is
 * associated with a specific object identified by a model owner ID, model ID,
 * and component ID.
 *
 * The service parses the submitted strings to extract raw token references
 * (e.g. '[template:eca-template:select:form:field:type:text]'), matches them
 * against the model owner's template token definitions, and categorizes the
 * results by purpose (select, config, etc.).
 *
 * The resolved data is structured for passing to drupalSettings where the
 * Preact frontend component can interpret select-purpose tokens to perform
 * DOM element selection.
 *
 * Usage example:
 * @code
 * $resolver = \Drupal::service('modeler_api.template_token_resolver');
 * $resolver->addToken(
 *   '[template:eca-template:select:form:field:type:text]',
 *   'eca',
 *   'my_model',
 *   'Event_1abc'
 * );
 * // A string may contain multiple tokens:
 * $resolver->addToken(
 *   'Apply to [template:eca-template:select:form:field:required:yes] and [template:eca-template:select:form:field:disabled:no]',
 *   'eca',
 *   'my_model',
 *   'Event_2def'
 * );
 * // Attach hidden config data to an object:
 * $resolver->addConfig('plugin_id', 'my_action', 'eca', 'my_model', 'Event_1abc');
 * $resolver->addConfig('weight', '10', 'eca', 'my_model', 'Event_1abc');
 * // Attach a label to an object:
 * $resolver->addLabel('Send email', 'eca', 'my_model', 'Event_1abc');
 *
 * // Later, typically in a page attachment hook:
 * $attachments = $resolver->getAttachments();
 * @endcode
 *
 * @see \Drupal\modeler_api\TemplateToken
 * @see \Drupal\modeler_api\Plugin\TemplateTokenPluginManager
 */
class TemplateTokenResolver {

  /**
   * The raw token wrapper pattern for extraction.
   *
   * This must match the format used by TemplateTokenListBuilder::getList().
   * The pattern captures the prefix and the token path from within the
   * innermost bracket pair. Both '[' and ']' are excluded from the token
   * path so that tokens nested inside outer brackets (e.g. CSS attribute
   * selectors like [name="[template:...]"]) are correctly identified.
   */
  protected const string RAW_TOKEN_PATTERN = '/\[([a-zA-Z][a-zA-Z0-9_-]*):([^\[\]]+)\]/';

  /**
   * Collected entries for the current page.
   *
   * Keyed by a composite key of modelOwnerId:modelId:componentId, each value
   * is an array of resolved token data grouped by purpose.
   *
   * @var array<string, array{model_owner_id: string, model_id: string, component_id: string, select: array, config: array}>
   */
  protected array $entries = [];

  /**
   * Hidden config key/value pairs per object.
   *
   * Keyed by the same composite key as $entries. Each value is an associative
   * array of key/value pairs added via addConfig(). These are only included
   * in the resolved output if the object also has at least one token entry.
   *
   * @var array<string, array<string, string>>
   */
  protected array $hiddenConfig = [];

  /**
   * Labels per object.
   *
   * Keyed by the same composite key as $entries. Each value is a single
   * label string added via addLabel(). These are only included in the
   * resolved output if the object also has at least one token entry.
   *
   * @var array<string, string>
   */
  protected array $labels = [];

  /**
   * Previously applied templates.
   *
   * A flat list of applied template records. Each record contains the
   * model owner ID, component ID, target value, hidden config pairs, and
   * config values from a previous save. The frontend uses these to show
   * an "already applied" indicator when the same template matches the
   * same element.
   *
   * @var array<int, array{model_owner_id: string, component_id: string, target: string, hidden_config: array<string, string>, config: array<string, string>}>
   */
  protected array $appliedTemplates = [];

  /**
   * Dropdown items to add to DOM elements identified by template token paths.
   *
   * Each item maps a resolved token path to a dropdown entry containing a
   * label and a link to either edit an existing or create a new model. The
   * frontend uses the token path's CSS selector chain to locate the target
   * DOM elements and inject the dropdown item.
   *
   * @var array<int, array{selectors: string[], target: string, link: string}>
   */
  protected array $dropdownItems = [];

  /**
   * Constructs a TemplateTokenResolver.
   *
   * @param \Drupal\modeler_api\Plugin\TemplateTokenPluginManager $templateTokenPluginManager
   *   The template token plugin manager.
   * @param \Drupal\modeler_api\Plugin\ModelOwnerPluginManager $modelOwnerPluginManager
   *   The model owner plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\modeler_api\Api $modelerApiService
   *   The API.
   */
  public function __construct(
    protected TemplateTokenPluginManager $templateTokenPluginManager,
    protected ModelOwnerPluginManager $modelOwnerPluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Api $modelerApiService,
  ) {}

  /**
   * Adds tokens from a generic string for a specific object.
   *
   * The value is a generic string that may contain one or more raw template
   * token references in the format '[prefix:token-path]' (e.g.
   * '[template:eca-template:select:form:field:type:text]'). The method parses
   * the string, extracts all token references, and matches each against the
   * template token definitions for the given model owner.
   *
   * If a token path contains additional segments beyond the deepest matching
   * node in the template token tree, those trailing segments are captured as
   * a 'token_label' on the resolved token. For example, the token
   * '[template:eca-template:select:form:field:type:text:First name]' resolves
   * to the 'text' node with a token_label of 'First name'.
   *
   * Matched tokens are categorized by their purpose:
   * - 'select': The token defines a CSS selector chain for DOM element
   *   selection. The selector chain is collected for the frontend.
   * - 'config': The token provides a configuration value.
   *
   * All matched tokens are associated with the object identified by the
   * three IDs (owner, model, component).
   *
   * @param string $value
   *   A generic string that may contain raw token references such as
   *   '[template:eca-template:select:form:field:type:text]'. The string
   *   may contain zero, one, or multiple token references intermixed with
   *   arbitrary text.
   * @param string $modelOwnerId
   *   The model owner plugin ID (e.g. 'eca').
   * @param string $modelId
   *   The model (config entity) ID (e.g. 'my_eca_model').
   * @param string $componentId
   *   The component ID within the model (e.g. 'Event_1abc').
   *
   * @return $this
   */
  public function addToken(string $value, string $modelOwnerId, string $modelId, string $componentId): static {
    if (!preg_match_all(self::RAW_TOKEN_PATTERN, $value, $matches, PREG_SET_ORDER)) {
      return $this;
    }

    $objectKey = $modelOwnerId . ':' . $modelId . ':' . $componentId;
    if (!isset($this->entries[$objectKey])) {
      $this->entries[$objectKey] = [
        'model_owner_id' => $modelOwnerId,
        'model_id' => $modelId,
        'component_id' => $componentId,
        'select' => [],
        'config' => [],
      ];
    }

    // Get all template token definitions for this model owner.
    $templateTokens = $this->templateTokenPluginManager->getTemplateTokensByModelOwner($modelOwnerId);
    if (empty($templateTokens)) {
      return $this;
    }

    foreach ($matches as $match) {
      // $match[1] is the prefix (e.g. 'template' or 'eca-template').
      // $match[2] is the remainder after the prefix
      // ~ (e.g. 'eca-template:select:form:field:type:text' when the prefix
      // ~ is 'template', or 'select:form:field:type:text' when the prefix
      // ~ is 'eca-template').
      // The token path for lookup must always start with the top-level
      // indicator. When the prefix itself is the indicator, prepend it.
      $tokenPath = $this->buildTokenPath($match[1], $match[2], $templateTokens);

      $resolved = $this->resolveTokenPath($tokenPath, $templateTokens);
      if ($resolved === NULL) {
        continue;
      }

      $purpose = $resolved['purpose'] ?? 'config';
      // Avoid duplicates for the same object and path.
      $isDuplicate = FALSE;
      foreach ($this->entries[$objectKey][$purpose] ?? [] as $existing) {
        if ($existing['path'] === $resolved['path']) {
          $isDuplicate = TRUE;
          break;
        }
      }
      if (!$isDuplicate) {
        $this->entries[$objectKey][$purpose][] = $resolved;
      }
    }

    return $this;
  }

  /**
   * Adds a hidden config key/value pair for a specific object.
   *
   * The pair is stored and will be included in the resolved output only if
   * the same object (identified by the three IDs) also has at least one
   * resolved template token entry. This allows modules to pass arbitrary
   * configuration data alongside template token selections without exposing
   * it as a separate drupalSettings structure.
   *
   * @param string $key
   *   The configuration key.
   * @param string $value
   *   The configuration value.
   * @param string $modelOwnerId
   *   The model owner plugin ID (e.g. 'eca').
   * @param string $modelId
   *   The model (config entity) ID (e.g. 'my_eca_model').
   * @param string $componentId
   *   The component ID within the model (e.g. 'Event_1abc').
   *
   * @return $this
   */
  public function addConfig(string $key, string $value, string $modelOwnerId, string $modelId, string $componentId): static {
    $objectKey = $modelOwnerId . ':' . $modelId . ':' . $componentId;
    $this->hiddenConfig[$objectKey][$key] = $value;
    return $this;
  }

  /**
   * Adds a label for a specific object.
   *
   * The label is stored and will be included in the resolved output only if
   * the same object (identified by the three IDs) also has at least one
   * resolved template token entry. This allows modules to attach
   * human-readable labels to objects so the frontend can display them
   * alongside the selected DOM elements.
   *
   * @param string $label
   *   The human-readable label string.
   * @param string $modelOwnerId
   *   The model owner plugin ID (e.g. 'eca').
   * @param string $modelId
   *   The model (config entity) ID (e.g. 'my_eca_model').
   * @param string $componentId
   *   The component ID within the model (e.g. 'Event_1abc').
   *
   * @return $this
   */
  public function addLabel(string $label, string $modelOwnerId, string $modelId, string $componentId): static {
    $objectKey = $modelOwnerId . ':' . $modelId . ':' . $componentId;
    $this->labels[$objectKey] = $label;
    return $this;
  }

  /**
   * Records a previously applied template.
   *
   * This registers a template that was saved/applied in a prior interaction.
   * The frontend uses these records to show an "already applied" indicator
   * when the same template matches the same element. Matching is determined
   * by comparing the model owner ID, component ID, target value, and all
   * hidden config key/value pairs.
   *
   * @param string $modelOwnerId
   *   The model owner plugin ID (e.g. 'eca').
   * @param string $componentId
   *   The component ID within the model (e.g. 'Event_1abc').
   * @param string $target
   *   The target value that identifies the DOM element (e.g. the form
   *   field's name attribute value).
   * @param array $hiddenConfig
   *   An associative array of hidden config key/value pairs that were
   *   stored with the applied template.
   * @param array $config
   *   An associative array of user-provided config values that were
   *   stored with the applied template.
   *
   * @return $this
   */
  public function addAppliedTemplate(string $modelOwnerId, string $componentId, string $target, array $hiddenConfig = [], array $config = []): static {
    $this->appliedTemplates[] = [
      'model_owner_id' => $modelOwnerId,
      'component_id' => $componentId,
      'target' => $target,
      'hidden_config' => $hiddenConfig,
      'config' => $config,
    ];
    return $this;
  }

  /**
   * Adds a dropdown item to DOM elements identified by a token path.
   *
   * This method resolves a colon-separated token path against the template
   * token definitions for the given model owner to determine the CSS selector
   * chain and target attribute for identifying DOM elements. It then checks
   * whether the specified model already exists and generates a link to either
   * edit the existing model or create a new one.
   *
   * The token path uses the same colon-separated format as raw token
   * references in addToken(), e.g. 'eca-template:select:form:field:all'.
   *
   * The resolved token definition provides:
   * - CSS selectors for locating the target DOM elements.
   * - A target attribute (e.g. '[name]') for identifying individual elements.
   *
   * The link is generated via Link::createFromRoute() so that other modules
   * can hook into link generation (e.g. to add HTMX attributes). The
   * rendered link HTML is passed to the frontend, which injects it into the
   * template token popup.
   *
   * Context and context config are included as query parameters in the
   * generated link. Context config values may contain the placeholder
   * '{{ target }}' which the frontend replaces with the resolved target
   * attribute value of the DOM element (e.g. the form field's name) before
   * rendering the link.
   *
   * @param string $tokenPath
   *   The colon-separated token path describing which DOM elements to
   *   target (e.g. 'eca-template:select:form:field:all').
   * @param string $label
   *   The human-readable label for the dropdown item.
   * @param string $modelOwnerId
   *   The model owner plugin ID (e.g. 'eca').
   * @param string $modelId
   *   The model (config entity) ID. Used to determine whether the model
   *   already exists. If it exists, the link points to the edit page;
   *   otherwise, it points to the add page.
   * @param string $context
   *   A context identifier included as the 'context' query parameter
   *   in the generated link (e.g. 'eca_form').
   * @param array $contextConfig
   *   An associative array of context configuration key/value pairs
   *   included as the JSON-encoded 'contextConfig' query parameter.
   *   Values may contain the '{{ target }}' placeholder which the
   *   frontend resolves against the matched DOM element.
   *
   * @return $this
   */
  public function addDropdownItem(string $tokenPath, string $label, string $modelOwnerId, string $modelId, string $context, array $contextConfig = []): static {
    // Resolve the token path against the model owner's template tokens,
    // reusing the same resolution logic as addToken().
    $templateTokens = $this->templateTokenPluginManager->getTemplateTokensByModelOwner($modelOwnerId);
    if (empty($templateTokens)) {
      return $this;
    }

    $resolved = $this->resolveTokenPath($tokenPath, $templateTokens);
    if ($resolved === NULL || $resolved['purpose'] !== 'select') {
      return $this;
    }

    // Determine whether the model already exists.
    try {
      $owner = $this->modelOwnerPluginManager->createInstance($modelOwnerId);
    }
    catch (\Exception) {
      return $this;
    }

    $entityTypeId = $owner->configEntityTypeId();
    $entity = $this->entityTypeManager->getStorage($entityTypeId)->load($modelId);
    $isNew = $entity === NULL;

    // Build query parameters.
    $query = [];
    if ($context !== '') {
      $query['context'] = $context;
    }
    if (!empty($contextConfig)) {
      $query['contextConfig'] = json_encode($contextConfig);
    }

    $options = [];
    if (!empty($query)) {
      $options['query'] = $query;
    }

    // Generate the link via Link::createFromRoute() so other modules can
    // hook into link generation (e.g. to add HTMX attributes).
    $prefix = $isNew ? '+ ' : '✎ ';
    if ($isNew) {
      $name = 'entity.' . $entityTypeId . '.add';
    }
    else {
      $name = 'entity.' . $entityTypeId . '.edit_form';
    }
    if ($this->modelerApiService->getRouteByName($name)) {
      $link = Link::createFromRoute($prefix . $label, $name, [
        $entityTypeId => $modelId,
        'ownerId' => $modelOwnerId,
      ], $options)->toString();

      $this->dropdownItems[] = [
        'selectors' => $resolved['selectors'],
        'target' => $resolved['target'] ?? '',
        'link' => (string) $link,
      ];
    }

    return $this;
  }

  /**
   * Checks whether any tokens have been resolved for the current page.
   *
   * @return bool
   *   TRUE if at least one token has been resolved, FALSE otherwise.
   */
  protected function hasTokens(): bool {
    foreach ($this->entries as $entry) {
      if (!empty($entry['select']) || !empty($entry['config'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Resolves all collected entries for output.
   *
   * Returns an array of objects, each identified by model owner, model, and
   * component, containing their resolved select and config tokens. If hidden
   * config pairs were added via addConfig() for an object that also has at
   * least one resolved token entry, those pairs are included in the output.
   *
   * @return array
   *   An array of resolved object entries. Each entry contains:
   *   - 'model_owner_id': The model owner plugin ID.
   *   - 'model_id': The model config entity ID.
   *   - 'component_id': The component ID.
   *   - 'select': An array of select-purpose tokens, each with:
   *     - 'path': The matched token path.
   *     - 'name': The human-readable name from the token definition.
   *     - 'selectors': An ordered array of CSS selector strings.
   *     - 'value': (optional) The token's value.
   *     - 'token_label': (optional) Trailing segments from the original
   *       token that extend beyond the matched path, joined with ':'.
   *   - 'config': An array of config-purpose tokens, each with:
   *     - 'path': The matched token path.
   *     - 'name': The human-readable name from the token definition.
   *     - 'value': (optional) The token's value.
   *     - 'token_label': (optional) Trailing segments beyond the matched
   *       path, joined with ':'.
   *   - 'hidden_config': (optional) An associative array of key/value pairs
   *     added via addConfig(). Only present when the object has at least one
   *     resolved token entry and config pairs were provided.
   *   - 'label': (optional) A label string added via addLabel(). Only present
   *     when the object has at least one resolved token entry and a label
   *     was provided.
   */
  protected function resolve(): array {
    $result = [];
    foreach ($this->entries as $objectKey => $entry) {
      if (!empty($this->hiddenConfig[$objectKey])) {
        $entry['hidden_config'] = $this->hiddenConfig[$objectKey];
      }
      if (isset($this->labels[$objectKey])) {
        $entry['label'] = $this->labels[$objectKey];
      }
      $result[] = $entry;
    }
    return $result;
  }

  /**
   * Builds the full token path from the regex-captured prefix and remainder.
   *
   * Raw tokens can appear in two formats:
   * - With wrapper prefix: [template:eca-template:select:form:field:type:text]
   *   Here 'template' is the wrapper prefix and 'eca-template:...' is the
   *   remainder which already starts with the top-level indicator.
   * - Without a wrapper prefix: [eca-template:select:form:field:type:text]
   *   Here 'eca-template' is captured as the prefix, but it is actually the
   *   top-level indicator and must be prepended to the remainder.
   *
   * This method determines which case applies by checking whether the
   * remainder already starts with a known top-level indicator. If not, the
   * prefix itself is the indicator and must be prepended.
   *
   * @param string $prefix
   *   The captured prefix from the regex (first group).
   * @param string $remainder
   *   The captured remainder from the regex (second group).
   * @param \Drupal\modeler_api\TemplateToken[] $templateTokens
   *   The template token definitions to check for indicators.
   *
   * @return string
   *   The full colon-separated token path starting with the indicator.
   */
  protected function buildTokenPath(string $prefix, string $remainder, array $templateTokens): string {
    // Check if the remainder already starts with a known indicator.
    $firstSegment = explode(':', $remainder, 2)[0];
    foreach ($templateTokens as $templateToken) {
      if ($templateToken->hasToken($firstSegment)) {
        // The remainder already contains the indicator (wrapper prefix case).
        return $remainder;
      }
    }

    // The prefix itself is the indicator; prepend it.
    return $prefix . ':' . $remainder;
  }

  /**
   * Resolves a single token path against the given template token definitions.
   *
   * If the full path does not match any token in the tree, trailing segments
   * are progressively stripped and tested. Any stripped trailing segments are
   * joined with ':' and returned as 'token_label' in the result. This allows
   * tokens like 'eca-template:select:form:field:type:text:My custom label'
   * to resolve to the 'text' node with a token_label of 'My custom label'.
   *
   * @param string $path
   *   The colon-separated token path (without the wrapper brackets or
   *   prefix, e.g. 'eca-template:select:form:field:type:text').
   * @param \Drupal\modeler_api\TemplateToken[] $templateTokens
   *   The template token definitions to search.
   *
   * @return array|null
   *   The resolved token data, or NULL if no matching definition was found.
   *   When trailing segments exist beyond the matched path, 'token_label'
   *   contains them joined with ':'.
   */
  protected function resolveTokenPath(string $path, array $templateTokens): ?array {
    if ($path === '') {
      return NULL;
    }

    $segments = explode(':', $path);
    $indicator = $segments[0];

    // Collect template tokens that have the indicator.
    $candidates = [];
    foreach ($templateTokens as $templateToken) {
      if ($templateToken->hasToken($indicator)) {
        $candidates[] = $templateToken;
      }
    }
    if (empty($candidates)) {
      return NULL;
    }

    // Try longest path first, then progressively strip trailing segments.
    // For each path length, try all candidate plugins before shortening.
    // This ensures that a deeper match in any plugin is preferred over a
    // shallow match in another.
    for ($length = count($segments); $length >= 2; $length--) {
      $trySegments = array_slice($segments, 0, $length);
      $tryPath = implode(':', $trySegments);

      foreach ($candidates as $templateToken) {
        $tokenNode = $templateToken->findToken($tryPath);
        if ($tokenNode === NULL) {
          continue;
        }

        $purpose = $templateToken->resolvePurpose($tryPath);
        $result = [
          'path' => $tryPath,
          'name' => $tokenNode['name'] ?? $trySegments[array_key_last($trySegments)],
          'purpose' => $purpose,
        ];

        if ($purpose === 'select') {
          $result['selectors'] = $templateToken->collectSelectors($tryPath);
        }

        if (isset($tokenNode['value'])) {
          $result['value'] = $tokenNode['value'];
        }

        // Include target if present on the matched node itself.
        if (isset($tokenNode['target'])) {
          $result['target'] = $tokenNode['target'];
        }

        // Capture trailing segments as a label.
        if ($length < count($segments)) {
          $result['token_label'] = implode(':', array_slice($segments, $length));

          // When there are trailing segments, the immediate child of the
          // matched node may carry metadata (like 'target') that applies
          // to this token usage. Look for it in the node's children.
          if (!isset($result['target']) && !empty($tokenNode['children'])) {
            foreach ($tokenNode['children'] as $child) {
              if (isset($child['target'])) {
                $result['target'] = $child['target'];
                break;
              }
            }
          }
        }

        return $result;
      }
    }

    return NULL;
  }

  /**
   * Gets the render array attachments for the current page.
   *
   * If tokens have been resolved or dropdown items have been added, this
   * method returns a render array with the Preact library attached and the
   * resolved data in drupalSettings.
   *
   * The drupalSettings structure groups tokens by object (model owner +
   * model + component) and by purpose (select, config), enabling the
   * frontend to associate DOM selections with specific model components.
   *
   * Dropdown items are passed separately in
   * drupalSettings.modelerApiDropdownItems so the frontend can inject them
   * into the appropriate DOM elements.
   *
   * @param array &$attachments
   *   An array that you can add attachments to.
   */
  public function getAttachments(array &$attachments): void {
    if ($this->hasTokens() || !empty($this->dropdownItems)) {
      $attachments['#attached']['library'][] = 'modeler_api/template_token_selector';
      if ($this->hasTokens()) {
        $resolved = $this->resolve();
        if ($resolved) {
          $attachments['#attached']['drupalSettings']['modelerApiTemplateTokens'] = $resolved;
          if (!empty($this->appliedTemplates)) {
            $attachments['#attached']['drupalSettings']['modelerApiAppliedTemplates'] = $this->appliedTemplates;
          }
        }
      }
      if (!empty($this->dropdownItems)) {
        $attachments['#attached']['drupalSettings']['modelerApiDropdownItems'] = $this->dropdownItems;
      }
    }
  }

}
