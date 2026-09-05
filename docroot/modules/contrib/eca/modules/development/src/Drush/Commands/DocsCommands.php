<?php

namespace Drupal\eca_development\Drush\Commands;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RendererInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Plugin\ECA\Condition\ConditionInterface;
use Drupal\eca\Plugin\ECA\Event\EventInterface;
use Drupal\eca\Service\Actions;
use Drupal\eca\Service\Conditions;
use Drupal\eca\Service\Events;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\ExportRecipe;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader as TwigLoader;

/**
 * ECA documentation Drush command file.
 */
final class DocsCommands extends DrushCommands {

  use AutowireTrait;

  public const string NAMESPACE = 'drupal-eca-recipe';

  /**
   * Format version of the generated machine-readable plugin catalog.
   *
   * Consumers use this to detect breaking changes. The shape is specified in
   * the documentation repository under mcp/CONTRACT.md and must not change
   * without bumping this number and updating that file.
   *
   * Version 2 added "fields[].source", "key_sources" and config keys that have
   * no form element at all, so that "fields" describes the plugin's true
   * configuration contract rather than only its configuration form.
   */
  private const int CATALOG_VERSION = 2;

  /**
   * Field source: the key has a form element in buildConfigurationForm().
   */
  private const string SOURCE_FORM = 'form';

  /**
   * Field source: the key is declared by the plugin's defaultConfiguration().
   */
  private const string SOURCE_DEFAULT_CONFIGURATION = 'default_configuration';

  /**
   * Field source: the key is declared by the typed configuration schema.
   */
  private const string SOURCE_CONFIG_SCHEMA = 'config_schema';

  /**
   * Maximum number of select options published for a single form field.
   */
  private const int MAX_OPTIONS = 25;

  /**
   * Relative path of the generated machine-readable API directory.
   */
  private const string API_PATH = '../mkdocs/docs/api';

  /**
   * Form field keys whose option list is derived from the generating site.
   *
   * Some #options lists are built at runtime from the generating site's own
   * entity types, bundles, fields, roles, languages, text formats, views,
   * permissions or module configuration. Publishing those would bake a single
   * site's content model into public documentation and actively mislead every
   * reader whose site differs. Such fields are published with an empty option
   * list and the "options_site_dependent" marker instead.
   *
   * The decision is made by ::isSiteDependentOptions() in three steps:
   *
   * 1. This curated deny-list wins. It maps a form field key to the list of
   *    plugin ID patterns it applies to; "*" matches every plugin and a
   *    trailing "*" is a prefix match (see ::pluginIdMatches()). Scoping by
   *    plugin ID matters because the same key can be portable elsewhere: the
   *    "type" key is an entity-type-and-bundle selector on content entity
   *    events but a small hard-coded comparison-type enum on scalar
   *    conditions.
   * 2. ::PORTABLE_OPTION_FIELDS then protects lists that are long but
   *    genuinely site-independent, so they get truncated rather than dropped.
   * 3. Otherwise a list with more than self::MAX_OPTIONS entries is assumed to
   *    be site-derived. A hand-written enum in a plugin is never that long,
   *    while every site-derived list grows with the site. This keeps the
   *    output safe for plugins from modules that this list does not know.
   */
  private const array SITE_DEPENDENT_OPTION_FIELDS = [
    // Entity type and bundle selectors.
    'type' => [
      'content_entity:*',
      'eca_entity_type_bundle',
      'eca_new_entity',
      'eca_get_bundle_list',
    ],
    'entity_type' => ['*'],
    'bundle' => ['*'],
    // Views and their displays.
    'view_id' => ['*'],
    'display_id' => ['*'],
    // User roles.
    'rid' => ['*'],
    'role' => ['*'],
    'roles' => ['*'],
    // Installed languages.
    'langcode' => ['*'],
    'target_langcode' => ['*'],
    // Text formats.
    'format' => ['eca_render_text:*'],
    // Permissions and config actions, both driven by installed modules.
    'permission' => ['*'],
    'action_id' => ['*'],
    // Cache bins registered by installed modules.
    'backend' => ['eca_raw_cache_*'],
    // Models and hosts configured on the site.
    'model' => ['*'],
    'gitlab' => ['*'],
    // Workflow states and transitions. Note that the singular "state" key is
    // deliberately absent: on eca_form_field_add_state it is a portable list of
    // Form API states such as "enabled" or "visible".
    'state_id' => ['*'],
    'transition_id' => ['*'],
  ];

  /**
   * Field keys with a long but site-independent option list.
   *
   * These override the "more than self::MAX_OPTIONS entries" heuristic in
   * ::isSiteDependentOptions() so that the list is truncated and still
   * published rather than being suppressed as site-dependent.
   */
  private const array PORTABLE_OPTION_FIELDS = [
    // The PHP timezone database, identical on every host.
    'timezone' => ['*'],
  ];

  /**
   * Curated format hints for fields whose values cannot be enumerated.
   *
   * Maps a form field key to a list of plugin ID patterns (same matching rules
   * as ::SITE_DEPENDENT_OPTION_FIELDS) and the prose hint to publish. The
   * first matching pattern wins, so put specific patterns before "*".
   */
  private const array VALUE_FORMATS = [
    'type' => [
      // Verified against \Drupal\eca\Service\ContentEntityTypes::ALL, which is
      // the string "_all". The "Type (and bundle)" field ships with an empty
      // #description, so this hint is the only documentation a reader gets.
      'content_entity:*' => '<entity_type_id> <bundle>, space separated. Use _all as a wildcard in either position.',
      'eca_entity_type_bundle' => '<entity_type_id> <bundle>, space separated. Use _all as a wildcard in either position.',
      'eca_new_entity' => '<entity_type_id> <bundle>, space separated. Both parts are required, wildcards are not supported.',
      'eca_get_bundle_list' => 'An entity type ID available on the target site, for example node or taxonomy_term.',
    ],
    'entity_type' => [
      '*' => 'An entity type ID available on the target site, for example node, user or taxonomy_term.',
    ],
    'view_id' => [
      '*' => 'The machine name of a view on the target site.',
    ],
    'display_id' => [
      '*' => 'The machine name of a display within the selected view, for example default or page_1.',
    ],
    'rid' => [
      '*' => 'A user role machine name on the target site, for example administrator.',
    ],
    'role' => [
      '*' => 'A user role machine name on the target site. anonymous and authenticated always exist.',
    ],
    'langcode' => [
      '*' => 'A language code enabled on the target site, for example en. The special value _interface means the current interface language.',
    ],
    'target_langcode' => [
      '*' => 'A language code enabled on the target site. The special values _interface and _preferred resolve at runtime.',
    ],
    'format' => [
      'eca_render_text:*' => 'A text format machine name on the target site, for example basic_html, full_html or plain_text.',
    ],
    'permission' => [
      '*' => 'A permission machine name, for example "administer nodes". The available permissions depend on the modules installed on the target site.',
    ],
    'action_id' => [
      '*' => 'A config action plugin ID available on the target site, for example entity_method:user.role:grantPermission.',
    ],
    'backend' => [
      'eca_raw_cache_*' => 'A Drupal cache bin name available on the target site, for example default, render or data.',
    ],
    'model' => [
      '*' => 'The machine name of a model configured on the target site.',
    ],
    'state_id' => [
      '*' => 'The ID of a workflow state defined on the target site, for example draft or published.',
    ],
    'transition_id' => [
      '*' => 'The ID of a workflow transition defined on the target site, for example publish or archive.',
    ],
    'gitlab' => [
      '*' => 'The machine name of a GitLab host configured on the target site.',
    ],
    'timezone' => [
      '*' => 'Any PHP timezone identifier, for example Europe/Berlin or America/New_York.',
    ],
  ];

  /**
   * Table of contents.
   *
   * @var array
   */
  protected array $toc = [];

  /**
   * Machine-readable catalog entries, one per documented plugin.
   *
   * Accumulated while ::pluginDoc() runs and flushed once by ::writeCatalog().
   *
   * @var array
   */
  protected array $catalog = [];

  /**
   * List of all processed modules.
   *
   * @var array
   */
  protected array $modules = [];

  /**
   * List of extensions.
   *
   * @var \Drupal\Core\Extension\Extension[]
   */
  protected ?array $moduleExtensions;

  /**
   * Twig array loader.
   *
   * @var \Twig\Loader\ArrayLoader
   */
  protected TwigLoader $twigLoader;

  /**
   * Twig environment service.
   *
   * @var \Twig\Environment
   */
  protected TwigEnvironment $twigEnvironment;

  /**
   * The model owner plugin manager.
   *
   * @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface
   */
  private ModelOwnerInterface $owner;

  /**
   * DocsCommands constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Actions $actionServices,
    #[Autowire(service: 'eca.service.condition')]
    protected Conditions $conditionServices,
    #[Autowire(service: 'eca.service.event')]
    protected Events $eventsServices,
    protected FileSystemInterface $fileSystem,
    protected ModuleHandlerInterface $moduleHandler,
    protected ModuleExtensionList $moduleExtensionList,
    protected Api $modelerApi,
    #[Autowire(service: 'modeler_api.export.recipe')]
    protected ExportRecipe $exportRecipe,
    #[Autowire(service: 'plugin.manager.modeler_api.model_owner')]
    private readonly ModelOwnerPluginManager $modelOwnerPluginManager,
    protected RendererInterface $renderer,
    #[Autowire(service: 'twig')]
    protected TwigEnvironment $twig,
    #[Autowire(service: 'config.typed')]
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct();
    $this->twigLoader = new TwigLoader();
    $this->twigEnvironment = new TwigEnvironment($this->twigLoader);
    $this->moduleExtensions = $moduleExtensionList->getList();
    $owner = $this->modelOwnerPluginManager->createInstance('eca');
    if ($owner instanceof ModelOwnerInterface) {
      $this->owner = $owner;
    }
  }

  /**
   * Export documentation for all plugins.
   */
  #[Command(name: 'eca:doc:plugins', aliases: [])]
  #[Usage(name: 'eca:doc:plugins', description: 'Export documentation for all plugins.')]
  public function plugins(): void {
    @$this->fileSystem->mkdir('../mkdocs/include/modules', NULL, TRUE);
    @$this->fileSystem->mkdir('../mkdocs/include/plugins', NULL, TRUE);
    $this->toc['0-ECA']['0-placeholder'] = 'plugins/eca/index.md';

    foreach ($this->eventsServices->events() as $event) {
      $this->pluginDoc($event);
    }
    foreach ($this->conditionServices->conditions() as $condition) {
      $this->pluginDoc($condition);
    }
    foreach ($this->actionServices->actions() as $action) {
      $this->pluginDoc($action);
    }
    $this->updateToc('plugins');
    $this->writeCatalog();
    $this->writeEventDependencies();
  }

  /**
   * Export documentation for all models.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  #[Command(name: 'eca:doc:models', aliases: [])]
  #[Usage(name: 'eca:doc:models', description: 'Export documentation for all models.')]
  public function models(): void {
    /** @var \Drupal\eca\Entity\Eca $eca */
    foreach ($this->entityTypeManager
      ->getStorage('eca')
      ->loadMultiple() as $eca) {
      $this->modelDoc($eca);
      $owner = $this->modelerApi->findOwner($eca);
      $this->exportRecipe->doExport($owner, $eca, $this->exportRecipe->defaultName($eca), self::NAMESPACE, '../recipes/' . $eca->id());
    }
    $this->updateToc('library');
  }

  /**
   * Update the TOC file identified by $key.
   *
   * The freshly generated TOC is merged append-only with any TOC that already
   * exists on disk: existing entries that are missing from the new TOC are
   * preserved, while freshly generated values always win on key conflicts.
   * This guarantees that previously documented plugins or models are never
   * dropped from the navigation, even when they can no longer be discovered
   * during the current run.
   *
   * @param string $key
   *   The key for the TOC to update.
   */
  private function updateToc(string $key): void {
    $filename = '../mkdocs/toc/' . $key . '.yml';

    // Merge with a potentially existing TOC on disk, append-only. The existing
    // navigation list is converted back into the internal weighted assoc-map
    // shape and merged into the freshly generated TOC so that the existing
    // transform below produces the exact same output format as before.
    if (is_file($filename)) {
      $existingContent = file_get_contents($filename);
      if ($existingContent !== '') {
        $existingNav = Yaml::decode($existingContent);
        if (is_array($existingNav)) {
          $existingToc = $this->navToToc($existingNav, TRUE);
          $this->mergeTocAppendOnly($this->toc, $existingToc);
        }
      }
    }

    $this->sortNestedArrayAssoc($this->toc);
    $content = Yaml::encode($this->toc);
    $content = '- ' . $key . '/index.md' . PHP_EOL . str_replace(
      ['0-ECA:', '  0-placeholder: ', '  1-', '  2-', '  3-'],
      ['ECA:', '  ', '  ', '  ', '  '],
      $content);
    $content = preg_replace_callback('/\n\s*/', static function (array $matches) {
      return $matches[0] . '- ';
    }, $content);
    file_put_contents($filename, substr($content, 0, -2));
  }

  /**
   * Converts a navigation list back into the internal weighted assoc-map.
   *
   * This is the inverse of the transform applied in ::updateToc(): it takes a
   * decoded mkdocs navigation list and rebuilds the weighted, associative TOC
   * structure held in $this->toc, so that an existing on-disk TOC can be
   * merged with a freshly generated one.
   *
   * @param array $nav
   *   The decoded navigation list.
   * @param bool $top
   *   Whether $nav is the top level of the navigation list. At the top level
   *   the synthetic leading "{key}/index.md" link is skipped and the "ECA"
   *   section is mapped back to its weighted "0-ECA" key.
   *
   * @return array
   *   The navigation list expressed as a weighted assoc-map.
   */
  private function navToToc(array $nav, bool $top): array {
    $out = [];
    foreach ($nav as $item) {
      if (!is_array($item)) {
        // Scalar string entries are index links. At the top level this is the
        // synthetic leading "{key}/index.md" link prepended in ::updateToc(),
        // which must not be added back. Otherwise it is a provider index link.
        if (!$top) {
          $out['0-placeholder'] = $item;
        }
        continue;
      }
      $navKey = array_key_first($item);
      $val = $item[$navKey];
      $weightedKey = match ($navKey) {
        'Events' => '1-Events',
        'Conditions' => '2-Conditions',
        'Actions' => '3-Actions',
        'ECA' => $top ? '0-ECA' : $navKey,
        default => $navKey,
      };
      $out[$weightedKey] = is_array($val) ? $this->navToToc($val, FALSE) : $val;
    }
    return $out;
  }

  /**
   * Deep-merges a source TOC into a target TOC, append-only.
   *
   * Only entries that are missing from $target are added from $source. When a
   * key exists in both and both values are arrays, the merge recurses. When a
   * key exists in both but the values are not both arrays (leaf links or a
   * type mismatch), the existing $target value is kept, so freshly generated
   * values always win on conflict.
   *
   * @param array $target
   *   The freshly generated TOC to merge into. Modified by reference.
   * @param array $source
   *   The existing TOC to merge from.
   */
  private function mergeTocAppendOnly(array &$target, array $source): void {
    foreach ($source as $key => $value) {
      if (!array_key_exists($key, $target)) {
        $target[$key] = $value;
      }
      elseif (is_array($target[$key]) && is_array($value)) {
        $this->mergeTocAppendOnly($target[$key], $value);
      }
    }
  }

  /**
   * Sort array by key recursively.
   *
   * @param mixed $a
   *   The array to sort by key.
   */
  private function sortNestedArrayAssoc(mixed &$a): void {
    if (!is_array($a)) {
      return;
    }
    ksort($a);
    foreach ($a as $k => $v) {
      $this->sortNestedArrayAssoc($a[$k]);
    }
  }

  /**
   * Prepare documentation for given plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The ECA plugin for which documentation should be created.
   */
  private function pluginDoc(PluginInspectionInterface $plugin): void {
    if (!empty($plugin->getPluginDefinition()['nodocs']) || !empty($plugin->getPluginDefinition()['no_docs'])) {
      return;
    }
    $values = $this->getPluginValues($plugin);
    if ($values === NULL) {
      return;
    }
    $id = str_replace(':', '_', $plugin->getPluginId());
    $values['id_fs'] = $id;
    $this->modules[$values['provider']] = $values;

    $provider = $values['provider'];
    $values['extension_info'] = [
      'standalone' => TRUE,
      'module' => $provider,
    ];
    if (isset($this->moduleExtensions[$provider])) {
      // @phpstan-ignore-next-line
      if ($this->moduleExtensions[$provider]->origin === 'core') {
        $values['extension_info']['standalone'] = FALSE;
        $values['extension_info']['module'] = 'core';
      }
      else {
        // @phpstan-ignore-next-line
        $subpath = $this->moduleExtensions[$provider]->subpath;
        // @phpstan-ignore-next-line
        foreach ($this->moduleExtensions[$provider]->requires as $require) {
          // @phpstan-ignore-next-line
          if (isset($this->moduleExtensions[$require->getName()]) && str_contains($subpath, $this->moduleExtensions[$require->getName()]->subpath . '/')) {
            $values['extension_info']['standalone'] = FALSE;
            $values['extension_info']['module'] = $require->getName();
            break;
          }
        }
      }
    }

    $path = $values['path'];
    $filename = $path . '/' . $id . '.md';
    @$this->fileSystem->mkdir('../mkdocs/docs/' . $path, NULL, TRUE);
    file_put_contents('../mkdocs/docs/' . $filename, $this->render(__DIR__ . '/../../../templates/docs/plugin.md.twig', $values));

    $this->catalog[] = $this->catalogEntry($plugin, $values, $filename);

    $path = '../mkdocs/include/plugins/' . $values['provider'] . '/' . $values['type'] . '/';
    @$this->fileSystem->mkdir($path, NULL, TRUE);
    if (!file_exists($path . $id . '.md')) {
      file_put_contents($path . $id . '.md', '');
    }
    $path .= $id . '/';
    @$this->fileSystem->mkdir($path, NULL, TRUE);
    foreach ($values['fields'] as $field) {
      if (!file_exists($path . $field['name'] . '.md')) {
        file_put_contents($path . $field['name'] . '.md', '');
      }
    }

    if (!isset($values['toc'][$values['provider_name']])) {
      // Initialize TOC for a new provider.
      $values['toc'][$values['provider_name']]['0-placeholder'] = $values['provider_path'] . '/index.md';

      file_put_contents('../mkdocs/docs/' . $values['provider_path'] . '/index.md', $this->render(__DIR__ . '/../../../templates/docs/provider.md.twig', $values));
      if (!file_exists('../mkdocs/include/modules/' . $provider . '.md')) {
        file_put_contents('../mkdocs/include/modules/' . $provider . '.md', '');
      }
    }
    $values['toc'][$values['provider_name']][$values['weight'] . '-' . ucfirst($values['type']) . 's'][(string) $values['label']] = $filename;
  }

  /**
   * Extracts all required values from the given plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The ECA plugin for which values should be extracted.
   *
   * @return array|null
   *   The extracted values.
   */
  private function getPluginValues(PluginInspectionInterface $plugin): ?array {
    $values = $plugin->getPluginDefinition();
    if ($values['provider'] === 'core') {
      $values['provider_name'] = 'Drupal core';
    }
    else {
      $values['provider_name'] = $this->moduleExtensionList->getName($values['provider']);
    }
    if (str_starts_with($values['provider'], 'eca_')) {
      $basePath = str_replace('eca_', 'eca/', $values['provider']);
      $values['toc'] = &$this->toc['0-ECA'];
    }
    else {
      $basePath = $values['provider'];
      $values['toc'] = &$this->toc;
    }
    if (!isset($values['version_introduced'])) {
      $values['version_introduced'] = 'unknown';
    }
    $form_state = new FormState();
    if ($plugin instanceof EventInterface) {
      $weight = 1;
      $type = 'event';
      $form = $plugin->buildConfigurationForm([], $form_state);
      $values['tokens'] = $plugin->getTokens();
    }
    elseif ($plugin instanceof ConditionInterface) {
      $weight = 2;
      $type = 'condition';
      $form = $plugin->buildConfigurationForm([], $form_state);
    }
    elseif ($plugin instanceof ActionInterface) {
      $weight = 3;
      $type = 'action';
      $form = $this->actionServices->getConfigurationForm($plugin, $form_state);
      if ($form === NULL) {
        return NULL;
      }
    }
    else {
      $weight = 4;
      $type = 'error';
      $form = [];
    }
    $values['path'] = sprintf('plugins/%s/%ss',
      $basePath,
      $type
    );
    $values['provider_path'] = sprintf('plugins/%s',
      $basePath,
    );
    $fields = [];
    $extraDescriptions = [];
    $incomplete = FALSE;
    foreach ($form as $key => $def) {
      if (empty($def) || !is_array($def)) {
        continue;
      }
      switch ($def['#type'] ?? 'markup') {
        case 'hidden':
        case 'actions':
          continue 2;

        case 'container':
          // Fields nested inside a container are never collected, so the field
          // list of this plugin is not exhaustive. Flag that honestly instead
          // of letting a consumer assume completeness.
          $incomplete = TRUE;
          // Fall through, containers are handled like any other markup.
        case 'item':
        case 'markup':
          if (isset($def['#markup']) && !str_starts_with($key, 'eca_token_')) {
            $extraDescriptions[] = $this->toMarkupString($def['#markup']);
          }
          continue 2;

        default:
          $fields[] = $this->fieldValues($plugin, $key, $def);
      }
    }
    $values['weight'] = $weight;
    $values['type'] = $type;
    $values['plugin_id'] = $plugin->getPluginId();
    $values['fields'] = $fields;
    $values['extraDescriptions'] = $extraDescriptions;

    // The configuration form is not the configuration contract: a plugin may
    // store keys that have no form element at all, either because they are
    // computed in submitConfigurationForm() or because the form element key
    // differs from the stored key. Union in every key the plugin declares
    // itself, so that consumers can trust "fields" to be a superset of the
    // legal configuration keys.
    $formKeys = [];
    foreach ($fields as $field) {
      $formKeys[$field['name']] = TRUE;
    }
    $declared = $this->declaredConfigurationKeys($plugin, $type);
    $extraFields = [];
    foreach ($declared['keys'] as $key => $source) {
      if (isset($formKeys[$key])) {
        continue;
      }
      $extraFields[] = $this->nonFormFieldValues($plugin, $key, $source, $declared['defaults']);
    }
    $values['extra_fields'] = $extraFields;
    $values['key_sources'] = $declared['sources'];
    // The key set is only trustworthy when the plugin declares it. Containers
    // hide nested form elements, and a plugin without a reachable
    // defaultConfiguration() may store keys nothing here can discover.
    $values['fields_may_be_incomplete'] = $incomplete || !in_array(self::SOURCE_DEFAULT_CONFIGURATION, $declared['sources'], TRUE);
    return $values;
  }

  /**
   * Collects every configuration key a plugin declares for itself.
   *
   * Two sources are consulted, both of which are authoritative and neither of
   * which is complete on its own:
   *
   * - ::defaultConfiguration() is the Drupal plugin API's own declaration of a
   *   configurable plugin's configuration keys. It is reachable for almost
   *   every ECA plugin and is what catches computed keys such as
   *   eca_render:block's "block_machine_name".
   * - The typed configuration schema, looked up under the name that
   *   eca.schema.yml maps the "configuration" mapping to. Only about half of
   *   all plugins ship one, so it can only ever add keys, never confirm
   *   completeness. Wildcard schema definitions are accepted because modules
   *   such as eca_tamper deliberately declare one schema for all derivatives,
   *   but a resolved definition without a "mapping" is rejected.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin to inspect.
   * @param string $type
   *   The plugin type: event, condition or action.
   *
   * @return array
   *   An array with three keys: "keys" maps a configuration key to the source
   *   it was discovered in, "defaults" holds the raw defaultConfiguration()
   *   values, and "sources" lists the sources that were actually reachable.
   */
  private function declaredConfigurationKeys(PluginInspectionInterface $plugin, string $type): array {
    $keys = [];
    $defaults = [];
    $sources = [self::SOURCE_FORM];

    // Both the interface and a bare method are accepted. A few plugins, for
    // example eca_switch_back, declare defaultConfiguration() without formally
    // implementing ConfigurableInterface, and their declaration of "I have no
    // configuration keys" is just as authoritative.
    if ($plugin instanceof ConfigurableInterface || method_exists($plugin, 'defaultConfiguration')) {
      try {
        // @phpstan-ignore-next-line
        $defaults = $plugin->defaultConfiguration();
        $sources[] = self::SOURCE_DEFAULT_CONFIGURATION;
        foreach (array_keys($defaults) as $key) {
          $keys[$key] = self::SOURCE_DEFAULT_CONFIGURATION;
        }
      }
      catch (\Throwable $e) {
        // A plugin whose defaults cannot be built is flagged as incomplete by
        // the caller, because SOURCE_DEFAULT_CONFIGURATION stays absent.
        $this->logger()->debug('Cannot read defaultConfiguration() of {plugin}: {message}', [
          'plugin' => $plugin->getPluginId(),
          'message' => $e->getMessage(),
        ]);
        $defaults = [];
      }
    }

    foreach ($this->schemaConfigurationKeys($plugin, $type) as $key) {
      $sources[] = self::SOURCE_CONFIG_SCHEMA;
      $keys[$key] ??= self::SOURCE_CONFIG_SCHEMA;
    }

    return [
      'keys' => $keys,
      'defaults' => $defaults,
      'sources' => array_values(array_unique($sources)),
    ];
  }

  /**
   * Reads the configuration keys from the plugin's typed configuration schema.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin to inspect.
   * @param string $type
   *   The plugin type: event, condition or action.
   *
   * @return array
   *   The declared configuration keys, empty when no schema is available.
   */
  private function schemaConfigurationKeys(PluginInspectionInterface $plugin, string $type): array {
    // These names mirror the "configuration" mapping types declared for the
    // eca.eca.* config entity in ECA's own eca.schema.yml. Actions follow the
    // core action.configuration.* convention.
    $name = match ($type) {
      'event' => 'eca.event.plugin.' . $plugin->getPluginId(),
      'condition' => 'eca.condition.plugin.' . $plugin->getPluginId(),
      'action' => 'action.configuration.' . $plugin->getPluginId(),
      default => NULL,
    };
    if ($name === NULL) {
      return [];
    }
    try {
      $definition = $this->typedConfigManager->getDefinition($name, FALSE);
    }
    catch (\Throwable) {
      return [];
    }
    if (!isset($definition['mapping']) || !is_array($definition['mapping'])) {
      // Either no schema at all, or a fallback that carries no mapping.
      return [];
    }
    return array_keys($definition['mapping']);
  }

  /**
   * Builds a field entry for a configuration key that has no form element.
   *
   * Nothing is invented here: there is no title, description or form type to
   * report, so those stay empty or NULL. Only the default value, which the
   * plugin does declare, is carried over.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin the key belongs to.
   * @param string $key
   *   The configuration key.
   * @param string $source
   *   The source the key was discovered in.
   * @param array $defaults
   *   The plugin's defaultConfiguration() values.
   *
   * @return array
   *   The field entry.
   */
  private function nonFormFieldValues(PluginInspectionInterface $plugin, string $key, string $source, array $defaults): array {
    return [
      'name' => $key,
      'label' => '',
      'description' => '',
      'form_type' => NULL,
      'required' => FALSE,
      'default' => $this->normalizeDefaultValue($defaults[$key] ?? NULL),
      'multiple' => FALSE,
      'options' => [],
      'options_site_dependent' => FALSE,
      'options_truncated' => FALSE,
      'value_format' => $this->valueFormat($plugin->getPluginId(), $key),
      'source' => $source,
    ];
  }

  /**
   * Extracts all documented values of a single configuration form field.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin the form field belongs to.
   * @param string $key
   *   The form field key, which is also the plugin configuration key that a
   *   model's "configuration" map has to use.
   * @param array $def
   *   The raw form element definition.
   *
   * @return array
   *   The extracted field values.
   */
  private function fieldValues(PluginInspectionInterface $plugin, string $key, array $def): array {
    $pluginId = $plugin->getPluginId();
    $options = $this->flattenOptions($def['#options'] ?? NULL);
    $siteDependent = $this->isSiteDependentOptions($pluginId, $key, count($options));
    $truncated = FALSE;
    if ($siteDependent) {
      $options = [];
    }
    elseif (count($options) > self::MAX_OPTIONS) {
      $options = array_slice($options, 0, self::MAX_OPTIONS);
      $truncated = TRUE;
    }
    $default = $this->normalizeDefaultValue($def['#default_value'] ?? NULL);
    $label = $this->toMarkupString($def['#title'] ?? $key);
    $optionsDisplay = [];
    foreach ($options as $option) {
      $optionsDisplay[] = $this->toInlineCode((string) $option['value']);
    }
    return [
      // These three keys are consumed by plugin.md.twig and by the
      // include/plugins placeholder generation in ::pluginDoc(). Existing
      // placeholder files are named after "name", so it must not be renamed.
      'name' => $key,
      'label' => $label,
      'description' => $this->toMarkupString($def['#description'] ?? ''),
      // Machine-readable metadata, published in api/plugins.json.
      'form_type' => $def['#type'] ?? 'markup',
      'required' => (bool) ($def['#required'] ?? FALSE),
      'default' => $default,
      'multiple' => (bool) ($def['#multiple'] ?? FALSE),
      'options' => $options,
      'options_site_dependent' => $siteDependent,
      'options_truncated' => $truncated,
      'value_format' => $this->valueFormat($pluginId, $key),
      'source' => self::SOURCE_FORM,
      // Presentation-only rendering of the values above, so that the Markdown
      // template does not have to deal with escaping and type juggling. These
      // keys are not part of the published JSON contract.
      'label_display' => $label === '' ? '' : $this->toInlineCode($label),
      'default_display' => $this->defaultValueDisplay($default),
      'options_display' => $optionsDisplay,
    ];
  }

  /**
   * Flattens a form element #options list into a portable list of values.
   *
   * Option groups are flattened rather than nested, because only the innermost
   * keys are valid configuration values. The group label carries no value.
   *
   * @param mixed $options
   *   The raw #options value.
   *
   * @return array
   *   A list of arrays with a "value" and a "label" key.
   */
  private function flattenOptions(mixed $options): array {
    if (!is_array($options)) {
      return [];
    }
    $flat = [];
    foreach ($options as $value => $label) {
      if (is_array($label) && !$this->isRenderArray($label)) {
        foreach ($label as $groupValue => $groupLabel) {
          $flat[] = [
            'value' => $groupValue,
            'label' => $this->toMarkupString($groupLabel),
          ];
        }
        continue;
      }
      $flat[] = [
        'value' => $value,
        'label' => $this->toMarkupString($label),
      ];
    }
    return $flat;
  }

  /**
   * Determines whether an array is a render array rather than an option group.
   *
   * @param array $value
   *   The array to inspect.
   *
   * @return bool
   *   TRUE if at least one key is a render array property.
   */
  private function isRenderArray(array $value): bool {
    foreach (array_keys($value) as $key) {
      if (is_string($key) && str_starts_with($key, '#')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Determines whether a field's option list is derived from this site.
   *
   * See ::SITE_DEPENDENT_OPTION_FIELDS for the full rule.
   *
   * @param string $pluginId
   *   The plugin ID that owns the field.
   * @param string $key
   *   The form field key.
   * @param int $count
   *   Number of flattened options the field offers.
   *
   * @return bool
   *   TRUE if the option list must not be published.
   */
  private function isSiteDependentOptions(string $pluginId, string $key, int $count): bool {
    if ($count === 0) {
      return FALSE;
    }
    if (isset(self::SITE_DEPENDENT_OPTION_FIELDS[$key]) && $this->pluginIdMatchesAny(self::SITE_DEPENDENT_OPTION_FIELDS[$key], $pluginId)) {
      return TRUE;
    }
    if (isset(self::PORTABLE_OPTION_FIELDS[$key]) && $this->pluginIdMatchesAny(self::PORTABLE_OPTION_FIELDS[$key], $pluginId)) {
      return FALSE;
    }
    return $count > self::MAX_OPTIONS;
  }

  /**
   * Looks up the curated value format hint for a field.
   *
   * @param string $pluginId
   *   The plugin ID that owns the field.
   * @param string $key
   *   The form field key.
   *
   * @return string|null
   *   The format hint, or NULL when none is curated for this field.
   */
  private function valueFormat(string $pluginId, string $key): ?string {
    foreach (self::VALUE_FORMATS[$key] ?? [] as $pattern => $format) {
      if ($this->pluginIdMatches($pattern, $pluginId)) {
        return $format;
      }
    }
    return NULL;
  }

  /**
   * Checks a plugin ID against a list of patterns.
   *
   * @param array $patterns
   *   The list of patterns.
   * @param string $pluginId
   *   The plugin ID to match.
   *
   * @return bool
   *   TRUE if at least one pattern matches.
   */
  private function pluginIdMatchesAny(array $patterns, string $pluginId): bool {
    foreach ($patterns as $pattern) {
      if ($this->pluginIdMatches($pattern, $pluginId)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Matches a plugin ID against a single pattern.
   *
   * A pattern of "*" matches every plugin ID, a trailing "*" is a prefix
   * match, anything else has to be equal.
   *
   * @param string $pattern
   *   The pattern.
   * @param string $pluginId
   *   The plugin ID to match.
   *
   * @return bool
   *   TRUE if the pattern matches.
   */
  private function pluginIdMatches(string $pattern, string $pluginId): bool {
    if ($pattern === '*') {
      return TRUE;
    }
    if (str_ends_with($pattern, '*')) {
      return str_starts_with($pluginId, substr($pattern, 0, -1));
    }
    return $pattern === $pluginId;
  }

  /**
   * Normalizes a #default_value into a JSON serializable value.
   *
   * Stringable objects such as TranslatableMarkup become strings, arrays are
   * normalized recursively and NULL stays NULL.
   *
   * @param mixed $value
   *   The raw #default_value.
   *
   * @return mixed
   *   The normalized value.
   */
  private function normalizeDefaultValue(mixed $value): mixed {
    if ($value === NULL || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
      return $value;
    }
    if (is_array($value)) {
      $normalized = [];
      foreach ($value as $key => $item) {
        $normalized[$key] = $this->normalizeDefaultValue($item);
      }
      return $normalized;
    }
    return $this->toMarkupString($value);
  }

  /**
   * Renders a normalized default value for the Markdown documentation.
   *
   * @param mixed $default
   *   The normalized default value.
   *
   * @return string
   *   The Markdown snippet, or an empty string when there is nothing worth
   *   documenting.
   */
  private function defaultValueDisplay(mixed $default): string {
    if ($default === NULL) {
      return '';
    }
    if (is_bool($default)) {
      return $default ? '`true`' : '`false`';
    }
    if (is_array($default)) {
      if ($default === []) {
        return 'empty list';
      }
      $items = [];
      foreach ($default as $item) {
        $items[] = is_array($item) ? '`…`' : $this->toInlineCode((string) $item);
      }
      return implode(', ', $items);
    }
    if ($default === '') {
      return 'empty string';
    }
    return $this->toInlineCode((string) $default);
  }

  /**
   * Wraps a value in Markdown inline code, handling contained backticks.
   *
   * @param string $value
   *   The value to wrap.
   *
   * @return string
   *   The Markdown inline code span.
   */
  private function toInlineCode(string $value): string {
    if ($value === '') {
      return '`\'\'`';
    }
    if (!str_contains($value, '`')) {
      return '`' . $value . '`';
    }
    // A code span may be fenced with more backticks than it contains, and
    // needs padding spaces when it starts or ends with a backtick.
    preg_match_all('/`+/', $value, $matches);
    $fence = str_repeat('`', max(array_map('strlen', $matches[0])) + 1);
    return $fence . ' ' . $value . ' ' . $fence;
  }

  /**
   * Builds the machine-readable catalog entry for a single plugin.
   *
   * The shape is specified in the documentation repository under
   * mcp/CONTRACT.md.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin to describe.
   * @param array $values
   *   The values extracted by ::getPluginValues().
   * @param string $docPath
   *   The path of the generated Markdown page, relative to the docs root.
   *
   * @return array
   *   The catalog entry.
   */
  private function catalogEntry(PluginInspectionInterface $plugin, array $values, string $docPath): array {
    $pluginId = $plugin->getPluginId();
    $baseId = $pluginId;
    $isDerivative = FALSE;
    if ($plugin instanceof DerivativeInspectionInterface) {
      $baseId = $plugin->getBaseId();
      $isDerivative = $plugin->getDerivativeId() !== NULL;
    }
    elseif (str_contains($pluginId, ':')) {
      [$baseId] = explode(':', $pluginId, 2);
      $isDerivative = TRUE;
    }

    // Form fields first, then the declared keys that have no form element, so
    // that the documented user-facing configuration keeps its form order.
    $fields = [];
    foreach (array_merge($values['fields'], $values['extra_fields']) as $field) {
      $fields[] = [
        // The plugin configuration key. Named "name" internally because the
        // Markdown template and the include file generation rely on that.
        'key' => $field['name'],
        'label' => $field['label'],
        'description' => $field['description'],
        'form_type' => $field['form_type'],
        'required' => $field['required'],
        'default' => $field['default'],
        'multiple' => $field['multiple'],
        'options' => $field['options'],
        'options_site_dependent' => $field['options_site_dependent'],
        'options_truncated' => $field['options_truncated'],
        'value_format' => $field['value_format'],
        'source' => $field['source'],
      ];
    }

    $entry = [
      'id' => $pluginId,
      'id_fs' => $values['id_fs'],
      'type' => $values['type'],
      'provider' => $values['provider'],
      'provider_name' => $values['provider_name'],
      'label' => $this->toMarkupString($values['label'] ?? ''),
      'description' => $this->toMarkupString($values['description'] ?? ''),
      'version_introduced' => $values['version_introduced'],
      'doc_path' => $docPath,
      'is_derivative' => $isDerivative,
      'base_id' => $baseId,
      'fields_may_be_incomplete' => $values['fields_may_be_incomplete'],
      'key_sources' => $values['key_sources'],
      'fields' => $fields,
    ];
    if ($values['type'] === 'event') {
      $entry['tokens'] = $this->normalizeTokens($values['tokens'] ?? []);
    }
    return $entry;
  }

  /**
   * Converts ECA token attributes into plain nested arrays.
   *
   * @param array $tokens
   *   A list of \Drupal\eca\Attribute\Token objects.
   *
   * @return array
   *   The tokens as nested arrays.
   */
  private function normalizeTokens(array $tokens): array {
    $normalized = [];
    foreach ($tokens as $token) {
      if (!is_object($token)) {
        continue;
      }
      $aliases = [];
      foreach ($token->aliases ?? [] as $alias) {
        $aliases[] = (string) $alias;
      }
      $normalized[] = [
        'name' => (string) ($token->name ?? ''),
        'description' => $this->toMarkupString($token->description ?? ''),
        'aliases' => $aliases,
        'properties' => $this->normalizeTokens($token->properties ?? []),
      ];
    }
    return $normalized;
  }

  /**
   * Writes the accumulated plugin catalog to api/plugins.json.
   */
  private function writeCatalog(): void {
    @$this->fileSystem->mkdir(self::API_PATH, NULL, TRUE);

    // Sort by type, then by plugin ID, so that the file diffs cleanly.
    usort($this->catalog, static fn(array $a, array $b): int => [$a['type'], $a['id']] <=> [$b['type'], $b['id']]);

    $counts = [];
    foreach ($this->catalog as $entry) {
      $counts[$entry['type']] = ($counts[$entry['type']] ?? 0) + 1;
    }
    ksort($counts);

    // @phpstan-ignore-next-line
    $ecaVersion = $this->moduleExtensions['eca']->info['version'] ?? 'unknown';
    $catalog = [
      'version' => self::CATALOG_VERSION,
      'generated' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
      'eca_version' => $ecaVersion,
      'counts' => $counts,
      'plugins' => $this->catalog,
    ];
    file_put_contents(
      self::API_PATH . '/plugins.json',
      json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
    );
  }

  /**
   * Copies the ECA event compatibility map into the API directory.
   *
   * The map lists the start events each component is valid under. It is static
   * and site independent, so it is copied verbatim.
   */
  private function writeEventDependencies(): void {
    @$this->fileSystem->mkdir(self::API_PATH, NULL, TRUE);

    $source = $this->moduleHandler->getModule('eca')->getPath() . '/eca.modeler_api.dependencies.yml';
    if (!is_file($source)) {
      $this->logger()->warning('Cannot copy the event compatibility map, {file} does not exist.', ['file' => $source]);
      return;
    }
    file_put_contents(self::API_PATH . '/event_dependencies.yml', file_get_contents($source));
  }

  /**
   * Safely converts a form property value into a plain string.
   *
   * Form properties such as #title, #description and #markup may be a string,
   * a stringable object (e.g. TranslatableMarkup) or a full render array. When
   * a render array is echoed directly by Twig, PHP raises an "Array to string
   * conversion" warning, so render arrays are rendered to a string first.
   *
   * @param mixed $value
   *   The raw form property value.
   *
   * @return string
   *   The value as a plain string. NULL becomes an empty string.
   */
  private function toMarkupString(mixed $value): string {
    if ($value === NULL) {
      return '';
    }
    if (is_array($value)) {
      // Temporarily disable Twig debug so the rendered HTML is not polluted
      // with THEME DEBUG comments on dev sites where twig.config.debug is on.
      // The core "twig" service is the same environment used during theme
      // rendering, and isDebug() is read live at render time. Restore the
      // original state afterwards, even if rendering throws.
      $debug = $this->twig->isDebug();
      if ($debug) {
        $this->twig->disableDebug();
      }
      try {
        return (string) $this->renderer->renderInIsolation($value);
      }
      finally {
        if ($debug) {
          $this->twig->enableDebug();
        }
      }
    }
    return (string) $value;
  }

  /**
   * Creates documentation for the given ECA model.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity for which documentation should be created.
   */
  private function modelDoc(Eca $eca): void {
    $modeler = $this->modelerApi->findOwner($eca);
    if ($modeler === NULL) {
      return;
    }
    $tags = $modeler->getTags($eca);
    if (empty($tags) || (count($tags) === 1 && ($tags[0] === 'untagged' || $tags[0] === ''))) {
      // Do not export models without at least one tag.
      return;
    }

    $values = [
      'rawid' => $eca->id(),
      'id' => str_replace([':', ' '], '_', mb_strtolower($eca->label())),
      'label' => $eca->label(),
      'version' => $eca->get('version'),
      'changelog' => $modeler->getChangelog($eca),
      'main_tag' => $tags[0],
      'tags' => $tags,
      'documentation' => $modeler->getDocumentation($eca),
      'events' => [],
      'conditions' => [],
      'actions' => [],
      'model_filename' => $modeler->getPluginId() . '-' . $eca->id(),
      'library_path' => 'library/' . $tags[0],
      'namespace' => self::NAMESPACE,
    ];
    foreach ($eca->getUsedEvents() as $event) {
      $label = $eca->getEventInfo($event);
      $plugin = $event->getPlugin();
      if (!empty($plugin->getPluginDefinition()['nodocs'])) {
        continue;
      }
      $info = $this->getPluginValues($plugin);
      $id = str_replace(':', '_', $plugin->getPluginId());
      $values['events'][] = '[' . $label . '](/' . $info['path'] . '/' . $id . '.md)';
    }
    $values['events'] = array_unique($values['events']);
    foreach ($eca->getConditions() as $condition) {
      if ($plugin = $this->conditionServices->createInstance($condition['plugin'])) {
        if (!empty($plugin->getPluginDefinition()['nodocs'])) {
          continue;
        }
        $label = $condition['label'] ?? $plugin->getPluginDefinition()['label'];
        $info = $this->getPluginValues($plugin);
        $id = str_replace(':', '_', $plugin->getPluginId());
        $values['conditions'][] = '[' . $label . '](/' . $info['path'] . '/' . $id . '.md)';
      }
    }
    $values['conditions'] = array_unique($values['conditions']);
    foreach ($eca->getActions() as $action) {
      if ($plugin = $this->actionServices->createInstance($action['plugin'])) {
        if (!empty($plugin->getPluginDefinition()['nodocs'])) {
          continue;
        }
        $label = $action['label'] ?? $plugin->getPluginDefinition()['label'];
        $info = $this->getPluginValues($plugin);
        $id = str_replace(':', '_', $plugin->getPluginId());
        $values['actions'][] = '[' . $label . '](/' . $info['path'] . '/' . $id . '.md)';
      }
    }
    $values['actions'] = array_unique($values['actions']);

    @$this->fileSystem->mkdir('../mkdocs/docs/' . $values['library_path'] . '/' . $values['id'], NULL, TRUE);

    $archiveFileName = '../mkdocs/docs/' . $values['library_path'] . '/' . $values['id'] . '/' . $values['model_filename'] . '.tar.gz';
    $values['dependencies'] = $this->modelerApi->exportArchive($this->owner, $eca);

    file_put_contents('../mkdocs/docs/' . $values['library_path'] . '/' . $values['id'] . '.md', $this->render(__DIR__ . '/../../../templates/docs/library.md.twig', $values));
    file_put_contents('../mkdocs/docs/' . $values['library_path'] . '/' . $values['id'] . '/' . $values['model_filename'] . '.xml', $modeler->getModeldata($eca));
    // The .xml above is a modeler graph format. Additionally export the config
    // entity itself, which is the canonical authoring target and can be
    // imported as-is.
    file_put_contents('../mkdocs/docs/' . $values['library_path'] . '/' . $values['id'] . '/eca.eca.' . $eca->id() . '.yml', Yaml::encode($eca->toArray()));

    $this->toc[$values['main_tag']][$values['label']] = $values['library_path'] . '/' . $values['id'] . '.md';
  }

  /**
   * Renders a twig template in filename with given values.
   *
   * @param string $filename
   *   The filename of a twig template.
   * @param array $values
   *   The values for rendering.
   *
   * @return string
   *   The rendered result of the twig template.
   */
  private function render(string $filename, array $values): string {
    $this->twigLoader->setTemplate($filename, file_get_contents($filename));
    try {
      return $this->twigEnvironment->render($filename, $values);
    }
    catch (LoaderError | RuntimeError | SyntaxError) {
      // @todo Log these exceptions.
    }
    return '';
  }

}
