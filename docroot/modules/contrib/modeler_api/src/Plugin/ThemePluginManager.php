<?php

namespace Drupal\modeler_api\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\modeler_api\Form\Settings;
use Drupal\modeler_api\Theme;

/**
 * Plugin manager for modeler API themes.
 *
 * Themes are defined in YAML files named MODULE.modeler_api.themes.yml. Each
 * top-level key is a theme ID containing a label and a list of Drupal asset
 * libraries. A theme may be limited to certain model owners and modelers; an
 * empty or missing list means the theme applies to all of them.
 *
 * Any module can provide themes: the model owner, the modeler, or a third
 * module that only wants to contribute styling.
 *
 * Example YAML:
 * @code
 * my_dark_theme:
 *   label: 'Dark'
 *   description: 'A dark canvas with light strokes.'
 *   libraries:
 *     - my_module/dark_theme
 *   owners:
 *     - eca
 *   modelers:
 *     - workflow_modeler
 *   weight: -10
 *
 * my_print_theme:
 *   label: 'Print friendly'
 *   libraries:
 *     - my_module/print_theme
 * @endcode
 *
 * A combination that is left on the automatic setting gets its theme from
 * resolveTheme(), which picks the most specific theme that explicitly names
 * this owner or this modeler.
 *
 * @see \Drupal\modeler_api\Theme
 * @see \Drupal\modeler_api\Form\Settings
 */
class ThemePluginManager extends DefaultPluginManager {

  /**
   * All theme instances.
   *
   * @var \Drupal\modeler_api\Theme[]
   */
  protected array $allInstances;

  /**
   * Constructs a ThemePluginManager object.
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
    $yaml_discovery = new YamlDiscovery('modeler_api.themes', $this->moduleHandler->getModuleDirectories());
    $yaml_discovery->addTranslatableProperty('label');
    $yaml_discovery->addTranslatableProperty('description');
    $this->discovery = new ContainerDerivativeDiscoveryDecorator($yaml_discovery);
    $this->alterInfo('modeler_api_theme_info');
    $this->setCacheBackend($cache_backend, 'modeler_api_theme_plugins', ['modeler_api_theme_plugins']);
  }

  /**
   * Gets all theme instances.
   *
   * @param bool $reload
   *   If TRUE, force reloading all instances.
   *
   * @return \Drupal\modeler_api\Theme[]
   *   The list of all theme instances, keyed by theme ID.
   */
  public function getAllThemes(bool $reload = FALSE): array {
    if (!isset($this->allInstances) || $reload) {
      $this->allInstances = [];
      foreach ($this->getDefinitions() as $id => $definition) {
        $theme = $this->createTheme($id, $definition);
        if ($theme !== NULL) {
          $this->allInstances[$id] = $theme;
        }
      }
    }
    return $this->allInstances;
  }

  /**
   * Gets a single theme by its ID.
   *
   * @param string $id
   *   The theme ID.
   *
   * @return \Drupal\modeler_api\Theme|null
   *   The theme, or NULL if not found.
   */
  public function getTheme(string $id): ?Theme {
    $themes = $this->getAllThemes();
    return $themes[$id] ?? NULL;
  }

  /**
   * Gets all themes available for an owner-modeler combination.
   *
   * @param string $ownerId
   *   The model owner plugin ID.
   * @param string $modelerId
   *   The modeler plugin ID.
   *
   * @return \Drupal\modeler_api\Theme[]
   *   The list of themes that apply to the given combination, keyed by theme
   *   ID.
   */
  public function getThemesFor(string $ownerId, string $modelerId): array {
    $themes = [];
    foreach ($this->getAllThemes() as $id => $theme) {
      if ($theme->appliesTo($ownerId, $modelerId)) {
        $themes[$id] = $theme;
      }
    }
    return $themes;
  }

  /**
   * Resolves the theme to use when no theme is pinned for a combination.
   *
   * This implements the automatic theme selection, which is what makes a theme
   * take effect the moment its module is installed, without an administrator
   * having to know that it exists. Nothing is written to configuration: the
   * answer is worked out again on every request, so it also disappears when
   * the providing module is uninstalled.
   *
   * A candidate must state that it was built for this combination:
   *
   * 1. Only a theme that declares a non-empty list of owners or a non-empty
   *    list of modelers can be chosen. A theme that declares neither applies
   *    everywhere by design, and picking it automatically would let any module
   *    change the styling of every canvas on the site merely by being
   *    installed. Such a theme remains available as an explicit choice in the
   *    settings form.
   * 2. The theme must apply to the combination at all, which is the same check
   *    the settings form uses.
   *
   * Among the candidates, the most specific one wins:
   *
   * 1. A theme that names both an owner and a modeler beats a theme that names
   *    only one of the two.
   * 2. Within the same specificity, the lowest weight wins.
   * 3. At equal weight, the alphabetically first theme ID wins. Discovery
   *    order is deliberately not used as a tie-break, because it follows
   *    module weight and would silently change when unrelated modules are
   *    installed.
   *
   * @param string $ownerId
   *   The model owner plugin ID.
   * @param string $modelerId
   *   The modeler plugin ID.
   *
   * @return \Drupal\modeler_api\Theme|null
   *   The theme to apply, or NULL if no theme qualifies. NULL means the
   *   modeler keeps its own look and feel, exactly like the explicit default.
   */
  public function resolveTheme(string $ownerId, string $modelerId): ?Theme {
    $best = NULL;
    $bestSpecificity = 0;
    $bestWeight = 0;
    foreach ($this->getThemesFor($ownerId, $modelerId) as $id => $theme) {
      $hasOwners = $theme->getOwners() !== [];
      $hasModelers = $theme->getModelers() !== [];
      if (!$hasOwners && !$hasModelers) {
        // The theme does not name this owner or this modeler in particular, so
        // it is never selected automatically.
        continue;
      }
      // Naming both dimensions is more specific than naming only one of them,
      // and the lower value wins in the comparison below.
      $specificity = ($hasOwners && $hasModelers) ? 0 : 1;
      $weight = $theme->getWeight();
      if ($best !== NULL) {
        // Each criterion only gets a say when the previous one is a draw.
        // strcmp() rather than a plain comparison, so that a theme ID which
        // happens to look like a number is still ordered as a string.
        $comparison = ($specificity <=> $bestSpecificity)
          ?: ($weight <=> $bestWeight)
          ?: strcmp((string) $id, $best->getId());
        if ($comparison >= 0) {
          continue;
        }
      }
      $best = $theme;
      $bestSpecificity = $specificity;
      $bestWeight = $weight;
    }
    return $best;
  }

  /**
   * Creates a Theme object from a plugin definition.
   *
   * @param string $id
   *   The theme ID.
   * @param array $definition
   *   The plugin definition from YAML.
   *
   * @return \Drupal\modeler_api\Theme|null
   *   The theme object, or NULL if the definition is invalid. A definition is
   *   invalid if it has no label or does not provide at least one asset
   *   library, because such a theme could neither be selected nor have any
   *   effect. A definition is also invalid if its ID is one of the reserved
   *   settings values, because that theme could never be told apart from the
   *   option it shadows.
   */
  protected function createTheme(string $id, array $definition): ?Theme {
    if ($id === Settings::THEME_OPTION_AUTO || $id === Settings::THEME_OPTION_DEFAULT) {
      return NULL;
    }
    $libraries = $this->stringList($definition['libraries'] ?? []);
    if (empty($definition['label']) || $libraries === []) {
      return NULL;
    }
    $weight = $definition['weight'] ?? 0;

    return new Theme(
      id: $id,
      label: $definition['label'],
      description: $definition['description'] ?? '',
      provider: $definition['provider'] ?? '',
      libraries: $libraries,
      owners: $this->stringList($definition['owners'] ?? []),
      modelers: $this->stringList($definition['modelers'] ?? []),
      weight: is_numeric($weight) ? (int) $weight : 0,
    );
  }

  /**
   * Normalizes a YAML value into a list of non-empty strings.
   *
   * A single string is accepted as a shorthand for a list with one item, and
   * anything that is not a string is dropped.
   *
   * @param mixed $value
   *   The raw value from the YAML definition.
   *
   * @return string[]
   *   The normalized list of strings.
   */
  protected function stringList(mixed $value): array {
    if (is_string($value)) {
      return $value === '' ? [] : [$value];
    }
    if (!is_array($value)) {
      return [];
    }
    $list = [];
    foreach ($value as $item) {
      if (is_string($item) && $item !== '') {
        $list[] = $item;
      }
    }
    return $list;
  }

}
