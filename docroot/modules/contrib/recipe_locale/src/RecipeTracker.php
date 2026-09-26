<?php

declare(strict_types=1);

namespace Drupal\recipe_locale;

use Composer\InstalledVersions;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ExtensionInstallStorage;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps the list of applied recipes and the config they shipped.
 *
 * Both live in config, so they can be exported and deployed like the rest of
 * the site's configuration. Neither needs the Interface Translation module:
 * recipes applied before Locale is installed are on the list when Locale
 * arrives.
 */
final class RecipeTracker {

  /**
   * The config object that holds the list of recipes.
   */
  public const string CONFIG_NAME = 'recipe_locale.recipes';

  /**
   * The prefix of the config objects that hold what each recipe shipped.
   */
  public const string SHIPPED_PREFIX = 'recipe_locale.shipped.';

  /**
   * The project type used for recipes in Locale's project list.
   */
  public const string PROJECT_TYPE = 'recipe';

  /**
   * The install and optional config directories of all extensions.
   *
   * @var \Drupal\Core\Config\ExtensionInstallStorage[]
   */
  private array $extensionStorages = [];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TypedConfigManagerInterface $typedConfigManager,
    #[Autowire(service: 'config.storage')]
    private readonly StorageInterface $configStorage,
    #[Autowire(param: 'install_profile')]
    private readonly ?string $installProfile,
    #[Autowire(param: 'app.root')]
    private readonly string $appRoot,
  ) {}

  /**
   * Records a recipe, and every recipe it applied, in config.
   *
   * Recipes from drupal.org are added to the list of translation projects.
   * For those and for core's own recipes, the translatable values of the
   * config they shipped are stored as well.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe that was applied.
   *
   * @return string[]
   *   The project names that were added or changed.
   */
  public function record(Recipe $recipe): array {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);
    $recipes = $config->get('recipes') ?? [];
    $changed = [];
    foreach ($this->collect($recipe) as $item) {
      $info = $this->describe($item);
      $key = $info['name'] ?? $this->coreRecipeKey($item);
      if ($info !== NULL) {
        $name = $info['name'];
        unset($info['name']);
        if (($recipes[$name] ?? NULL) !== $info) {
          $recipes[$name] = $info;
          $changed[] = $name;
        }
      }
      if ($key !== NULL) {
        $this->storeShippedConfig($key, $item);
      }
    }
    if ($changed) {
      $config->set('recipes', $recipes)->save();
    }
    return $changed;
  }

  /**
   * Removes a recipe from the list, with the config it shipped.
   *
   * @param string $name
   *   The project name.
   */
  public function forget(string $name): void {
    $this->configFactory->getEditable(self::SHIPPED_PREFIX . $name)->delete();
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);
    $recipes = $config->get('recipes') ?? [];
    unset($recipes[$name]);
    $config->set('recipes', $recipes)->save();
  }

  /**
   * Returns the recorded recipes, keyed by project name.
   *
   * @return array<string, array{package: string, version: string, label: string}>
   *   The recorded recipes.
   */
  public function getAll(): array {
    return $this->configFactory->get(self::CONFIG_NAME)->get('recipes') ?? [];
  }

  /**
   * Returns the recorded recipes in the form Locale's project list uses.
   *
   * Recipes without a known version are left out: there is no URL to download
   * from without a version.
   *
   * @return array<string, array<string, mixed>>
   *   Project data keyed by project name.
   *
   * @see hook_locale_translation_projects_alter()
   */
  public function getProjects(): array {
    $projects = [];
    foreach ($this->getAll() as $name => $recipe) {
      // The version is the one that was applied. Updating the package with
      // Composer changes nothing on the site until the recipe is applied
      // again, and that records the new version.
      // The translation server serves dev branches without the "-dev" suffix
      // Composer uses: drupal_cms_starter-2.x.de.po exists, while
      // drupal_cms_starter-2.x-dev.de.po does not.
      $version = preg_replace('/-dev$/', '', $recipe['version']);
      if ($version === '') {
        continue;
      }
      $projects[$name] = [
        'name' => $name,
        'project_type' => self::PROJECT_TYPE,
        'project_status' => TRUE,
        'info' => [
          'name' => $recipe['label'],
          'project' => $name,
          'version' => $version,
        ],
      ];
    }
    return $projects;
  }

  /**
   * Returns the names of all config objects that recorded recipes shipped.
   *
   * @param string[]|null $recipes
   *   Recipe keys to limit the list to, or NULL for all recorded recipes.
   *
   * @return string[]
   *   Config names.
   */
  public function getShippedConfigNames(?array $recipes = NULL): array {
    $names = [];
    foreach ($this->loadShipped() as $key => $shipped) {
      if ($recipes !== NULL && !in_array($key, $recipes, TRUE)) {
        continue;
      }
      foreach ($shipped->get('config') ?? [] as $item) {
        $names[] = $item['name'];
      }
    }
    return array_values(array_unique($names));
  }

  /**
   * Returns the shipped copy of a config object, as Locale expects it.
   *
   * Only the translatable values were stored. Locale builds typed data from
   * the copy, and some config schemas, Canvas for example, need to see the
   * other keys too. So the copy starts from the site's own data and gets
   * the shipped values put back into the translatable slots. A slot the recipe
   * shipped empty is emptied as well: Locale skips empty strings, and whatever
   * the site put there later is not a shipped original.
   *
   * @param string $name
   *   The config name.
   *
   * @return array<string, mixed>|null
   *   The config data as shipped, or NULL when no recorded recipe shipped this
   *   config, or the site no longer has it.
   */
  public function readShippedConfig(string $name): ?array {
    $stored = $this->findShipped($name);
    if ($stored === NULL) {
      return NULL;
    }
    $data = $this->configStorage->read($name);
    if (!is_array($data) || !$this->typedConfigManager->hasConfigSchema($name)) {
      return NULL;
    }
    $typed = $this->typedConfigManager->createFromNameAndData($name, $data);
    foreach ($this->translatablePaths($typed) as $path) {
      $shipped = NestedArray::getValue($stored, $path, $exists);
      NestedArray::setValue($data, $path, $exists && is_string($shipped) ? $shipped : '');
    }
    // Only English config was stored, and Locale reads the langcode to decide
    // the source language.
    $data['langcode'] = 'en';
    return $data;
  }

  /**
   * Finds the version of a recipe package.
   *
   * Composer knows the version while the package is in composer.lock. After a
   * recipe is unpacked, Composer forgets it. Then the version key in the
   * recipe's composer.json is the only record left. Drupal CMS writes that key
   * for its own recipes at release time, and the drupal/site_template_helper
   * plugin writes it for any recipe it installs.
   *
   * @param string $package
   *   The Composer package name.
   * @param array<string, mixed> $composer
   *   The decoded composer.json of the recipe.
   *
   * @return string
   *   The version, or an empty string when it is not known.
   */
  public function findVersion(string $package, array $composer = []): string {
    if (InstalledVersions::isInstalled($package)) {
      return InstalledVersions::getPrettyVersion($package) ?? '';
    }
    if (!empty($composer['version']) && is_string($composer['version'])) {
      return $composer['version'];
    }
    return '';
  }

  /**
   * Stores the translatable values of the config a recipe ships itself.
   *
   * Config that a module also ships is left out: Locale finds the original of
   * that in the module. Config a recipe creates with config actions has no
   * shipped file, so it cannot be covered. Config shipped in a language other
   * than English is left out too, because Locale only translates from English.
   *
   * @param string $key
   *   The recipe key: the project name, or "core-" and the directory name for
   *   core's own recipes.
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe.
   */
  private function storeShippedConfig(string $key, Recipe $recipe): void {
    $directory = $recipe->path . '/config';
    $config = $this->configFactory->getEditable(self::SHIPPED_PREFIX . $key);
    if (!is_dir($directory)) {
      if (!$config->isNew()) {
        $config->delete();
      }
      return;
    }
    $storage = new FileStorage($directory);
    $items = [];
    foreach ($storage->listAll() as $name) {
      if ($this->isShippedByExtension($name)) {
        continue;
      }
      $data = $storage->read($name);
      if (!is_array($data) || !$this->typedConfigManager->hasConfigSchema($name)) {
        continue;
      }
      // Locale translates from English only. Config shipped in another
      // language is not its business, and not ours.
      if (isset($data['langcode']) && $data['langcode'] !== 'en') {
        continue;
      }
      $translatable = $this->extractTranslatable($this->typedConfigManager->createFromNameAndData($name, $data));
      if ($translatable === []) {
        continue;
      }
      // Core creates a config object only when the site does not have it
      // yet. So when another recipe already keeps a copy of this one, that
      // copy is the text in the site's config, and this file was skipped.
      if ($this->keptByAnotherRecipe($name, $key)) {
        continue;
      }
      $items[] = [
        'name' => $name,
        'data' => $translatable,
      ];
    }
    if ($items === []) {
      if (!$config->isNew()) {
        $config->delete();
      }
      return;
    }
    $stored = ['recipe' => $recipe->name, 'config' => $items];
    if ($config->isNew() || $config->get() !== $stored + $config->get()) {
      $config->setData($stored)->save();
    }
  }

  /**
   * Tells whether a recipe other than the given one keeps a config object.
   */
  private function keptByAnotherRecipe(string $name, string $key): bool {
    foreach ($this->loadShipped() as $recipe => $shipped) {
      if ($recipe === $key) {
        continue;
      }
      foreach ($shipped->get('config') ?? [] as $item) {
        if ($item['name'] === $name) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Removes the stored copy of a config object, whichever recipe keeps it.
   *
   * Called when the site deletes or renames the object. What the recipe
   * shipped is gone then, and a recipe applied later may create the object
   * again from its own file, which then keeps the new copy.
   */
  public function removeCopy(string $name): void {
    foreach ($this->loadShipped() as $key => $shipped) {
      foreach ($shipped->get('config') ?? [] as $item) {
        if ($item['name'] === $name) {
          $this->removeFromRecipe($key, $name);
          return;
        }
      }
    }
  }

  /**
   * Removes the copy of one config object from a recipe's stored copies.
   */
  private function removeFromRecipe(string $key, string $name): void {
    $config = $this->configFactory->getEditable(self::SHIPPED_PREFIX . $key);
    $items = array_values(array_filter($config->get('config') ?? [], fn(array $item) => $item['name'] !== $name));
    if ($items === []) {
      $config->delete();
    }
    else {
      $config->set('config', $items)->save();
    }
  }

  /**
   * Finds what a recorded recipe stored for a config object.
   *
   * @return array<string, mixed>|null
   *   The stored translatable values, or NULL.
   */
  private function findShipped(string $name): ?array {
    foreach ($this->loadShipped() as $shipped) {
      foreach ($shipped->get('config') ?? [] as $item) {
        if ($item['name'] === $name) {
          return $item['data'] ?? [];
        }
      }
    }
    return NULL;
  }

  /**
   * Lists the paths of every translatable value in typed config.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   Typed config.
   * @param string[] $path
   *   The keys leading to this element.
   *
   * @return array<int, string[]>
   *   Key paths, as NestedArray expects them.
   */
  private function translatablePaths(TypedDataInterface $element, array $path = []): array {
    if ($element instanceof TraversableTypedDataInterface) {
      $paths = [];
      foreach ($element as $key => $property) {
        $paths = array_merge($paths, $this->translatablePaths($property, [...$path, (string) $key]));
      }
      return $paths;
    }
    return !empty($element->getDataDefinition()['translatable']) ? [$path] : [];
  }

  /**
   * Keeps the translatable values of typed config, as plain data.
   *
   * Same walk as Locale does over shipped config, but without Locale.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   Typed config.
   *
   * @return array<string, mixed>|string
   *   The translatable values, nested like the config.
   *
   * @see \Drupal\locale\LocaleConfigManager::getTranslatableData()
   */
  private function extractTranslatable(TypedDataInterface $element): array|string {
    if ($element instanceof TraversableTypedDataInterface) {
      $translatable = [];
      foreach ($element as $key => $property) {
        $value = $this->extractTranslatable($property);
        if ($value !== [] && $value !== '') {
          $translatable[$key] = $value;
        }
      }
      return $translatable;
    }
    $value = $element->getValue();
    $definition = $element->getDataDefinition();
    if (!empty($definition['translatable']) && is_string($value) && $value !== '') {
      return $value;
    }
    return [];
  }

  /**
   * Tells whether a module, theme or the profile ships a config object.
   */
  private function isShippedByExtension(string $name): bool {
    if ($this->extensionStorages === []) {
      foreach ([ExtensionInstallStorage::CONFIG_INSTALL_DIRECTORY, ExtensionInstallStorage::CONFIG_OPTIONAL_DIRECTORY] as $directory) {
        $this->extensionStorages[] = new ExtensionInstallStorage($this->configStorage, $directory, StorageInterface::DEFAULT_COLLECTION, TRUE, $this->installProfile);
      }
    }
    foreach ($this->extensionStorages as $storage) {
      if ($storage->exists($name)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Loads the stored copies of what every recorded recipe shipped.
   *
   * @return array<string, \Drupal\Core\Config\ImmutableConfig>
   *   Config objects keyed by recipe key.
   */
  private function loadShipped(): array {
    $objects = [];
    foreach ($this->configFactory->listAll(self::SHIPPED_PREFIX) as $config_name) {
      $objects[substr($config_name, strlen(self::SHIPPED_PREFIX))] = $this->configFactory->get($config_name);
    }
    return $objects;
  }

  /**
   * Returns the storage key for one of core's own recipes.
   *
   * Core's translations cover these recipes' strings, but Locale still needs
   * the shipped copy of their config to apply them.
   *
   * @return string|null
   *   "core-" and the recipe directory name, or NULL for other recipes.
   */
  private function coreRecipeKey(Recipe $recipe): ?string {
    $core = realpath($this->appRoot . '/core/recipes');
    $path = realpath($recipe->path);
    if ($core && $path && str_starts_with($path, $core . DIRECTORY_SEPARATOR)) {
      return 'core-' . basename($path);
    }
    return NULL;
  }

  /**
   * Lists a recipe and all the recipes it applies, without repeats.
   *
   * Recipes are listed depth first, in the order they were applied.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe to start from.
   * @param \Drupal\Core\Recipe\Recipe[] $seen
   *   Recipes found so far, keyed by path.
   *
   * @return \Drupal\Core\Recipe\Recipe[]
   *   The recipes, keyed by path.
   */
  private function collect(Recipe $recipe, array &$seen = []): array {
    if (!isset($seen[$recipe->path])) {
      foreach ($recipe->recipes->recipes as $dependency) {
        $this->collect($dependency, $seen);
      }
      $seen[$recipe->path] = $recipe;
    }
    return $seen;
  }

  /**
   * Describes a recipe as a translatable project.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe.
   *
   * @return array{name: string, package: string, version: string, label: string}|null
   *   The project name, package name, version and recipe name. NULL when the
   *   recipe has no composer.json, or is not a drupal.org project. Core's own
   *   recipes have no composer.json.
   */
  private function describe(Recipe $recipe): ?array {
    $file = $recipe->path . '/composer.json';
    if (!is_file($file)) {
      return NULL;
    }
    $composer = Json::decode((string) file_get_contents($file));
    $package = is_array($composer) ? ($composer['name'] ?? '') : '';
    if (!is_string($package) || !preg_match('#^drupal/[a-z0-9_]+$#', $package)) {
      return NULL;
    }
    return [
      'name' => substr($package, 7),
      'package' => $package,
      'version' => $this->findVersion($package, $composer),
      'label' => $recipe->name,
    ];
  }

}
