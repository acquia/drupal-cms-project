<?php

namespace Drupal\modeler_api;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\ManagedStorage;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\modeler_api\Form\Settings;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Service provides export to recipe functionality for models.
 */
class ExportRecipe {

  use StringTranslationTrait;

  public const string DEFAULT_NAMESPACE = 'drupal';
  public const string DEFAULT_DESTINATION = 'temporary://recipe';

  /**
   * The length beyond which a derived summary is no longer a one-line summary.
   */
  protected const int SUMMARY_MAX_LENGTH = 255;

  /**
   * The provider key of this module's third-party settings on a model.
   *
   * @see \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase::setThirdPartySetting()
   */
  protected const string THIRD_PARTY_PROVIDER = 'modeler_api';

  /**
   * Constructs the recipe export service.
   */
  public function __construct(
    protected readonly ManagedStorage $configStorage,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly ModuleExtensionList $moduleExtensionList,
    protected readonly MessengerInterface $messenger,
    protected readonly Api $api,
  ) {}

  /**
   * Exports the given model to a recipe.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner plugin.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The model owner's config entity.
   * @param string|null $name
   *   The name of the model.
   * @param string $namespace
   *   The namespace to use for composer.
   * @param string $destination
   *   The directory, where to store the recipe.
   */
  public function doExport(ModelOwnerInterface $owner, ConfigEntityInterface $entity, ?string $name = NULL, string $namespace = self::DEFAULT_NAMESPACE, string $destination = self::DEFAULT_DESTINATION): void {
    $destination = rtrim($destination, '/');
    $configDestination = $destination . '/config';
    $composerJson = $destination . '/composer.json';
    $recipeYml = $destination . '/recipe.yml';
    $readmeMd = $destination . '/README.md';
    try {
      $existingComposer = $this->readExistingJson($composerJson);
      $existingRecipe = $this->readExistingYaml($recipeYml);
    }
    catch (\Throwable $exception) {
      $this->messenger->addError($this->t('The existing recipe metadata can not be read: @message', [
        '@message' => $exception->getMessage(),
      ]));
      return;
    }

    if ($name === NULL) {
      $name = $this->defaultName($entity);
    }
    $documentation = $owner->getDocumentation($entity);
    $summary = $owner->getSummary($entity);
    // An empty summary means the model does not express one, in which case the
    // export derives a one-line description from the documentation. Whether
    // the summary is expressed or derived also decides who wins over a
    // description that a previous export left in the files.
    $summaryIsExpressed = $summary !== '';
    if (!$summaryIsExpressed) {
      $summary = $this->summaryFromDocumentation($documentation);
    }
    $recipes = array_values($owner->getRecipes($entity));

    $modelConfigName = $owner->configEntityProviderId() . '.' . $owner->configEntityTypeId() . '.' . $entity->id();
    $dependencies = [
      'config' => [
        $modelConfigName,
      ],
      'module' => [],
    ];
    // The separately stored raw model data is deliberately not added here: a
    // recipe ships the model, not the diagram of the modeler that authored it.
    // @see self::stripModelerData()
    $this->api->getNestedDependencies($dependencies, $entity->getDependencies());

    // Config objects that the model declares in addition to the ones it
    // depends on. They join the very same list, so that a declared object goes
    // through the identical code path below and no config data ever has to be
    // duplicated into the model itself.
    $declaredConfig = [];
    foreach ($owner->getExportConfig($entity) as $configName) {
      if (!in_array($configName, $dependencies['config'], TRUE)) {
        $declaredConfig[] = $configName;
        $dependencies['config'][] = $configName;
      }
    }

    // Modules the model declares in addition to the ones Drupal can compute.
    // They join the computed list rather than being handled separately, which
    // gives them the same treatment on both sides: the composer requirements
    // resolve a submodule to its project and skip core modules, while the
    // recipe's install list names every module including the core ones.
    // Appending, rather than merging and sorting, keeps the order of an
    // already published recipe untouched, so re-exporting a model that
    // declares nothing produces no diff at all.
    foreach ($owner->getModules($entity) as $module) {
      if (in_array($module, $dependencies['module'], TRUE)) {
        continue;
      }
      if (!$this->moduleExtensionList->exists($module)) {
        // The extension list covers every module present on disk, installed
        // or not, so this is a name that resolves to nothing at all. Emitting
        // it would produce a recipe that cannot be applied.
        $this->messenger->addWarning($this->t('The model declares the module @name as required by its recipe, but no such module exists on this site. It has been skipped.', [
          '@name' => $module,
        ]));
        continue;
      }
      $dependencies['module'][] = $module;
    }

    // Build the whole recipe payload before touching the file system, so that
    // nothing is deleted until it is known what replaces it.
    $actions = [];
    $imports = [];
    $configFiles = [];
    foreach ($dependencies['config'] as $configName) {
      $config = $this->configStorage->read($configName);
      if (!$config) {
        if (in_array($configName, $declaredConfig, TRUE)) {
          // A dependency that has vanished is a different problem and stays
          // silent, but a name the maintainer typed into the model is worth
          // reporting: the recipe is simply missing it otherwise.
          $this->messenger->addWarning($this->t('The model declares the config object @name for export, but it does not exist in the active configuration. It has been skipped.', [
            '@name' => $configName,
          ]));
        }
        continue;
      }
      unset($config['uuid'], $config['_core']);
      if ($configName === $modelConfigName) {
        $config = $this->stripModelerData($config);
      }
      if (str_starts_with($configName, 'user.role.')) {
        $actions[$configName] = [
          'ensure_exists' => [
            'label' => $config['label'],
          ],
          'grantPermissions' => $config['permissions'],
        ];
      }
      else {
        $canBeImported = FALSE;
        foreach ($config['dependencies']['module'] ?? [] as $module) {
          if ($this->isProvidedByModule($module, $configName)) {
            $imports[$module][] = $configName;
            $canBeImported = TRUE;
            break;
          }
        }
        if (!$canBeImported) {
          $configFiles[$configName . '.yml'] = Yaml::encode($config);
        }
      }
    }
    // Config actions the model expresses win over the generated role actions
    // on the rare occasion that both address the same config object, because
    // the model states them explicitly while the role actions are derived.
    foreach ($this->configActionsByName($owner->getConfigActions($entity)) as $configName => $action) {
      $actions[$configName] = $action;
    }

    $this->warnAboutUnexpectedConfigFiles($configDestination, array_keys($configFiles));

    if (file_exists($configDestination) && !$this->fileSystem->deleteRecursive($configDestination)) {
      $this->messenger->addError($this->t('A config directory already exists in the given destination and can not be removed.'));
      return;
    }
    if (file_exists($composerJson) && !$this->fileSystem->unlink($composerJson)) {
      $this->messenger->addError($this->t('A composer.json already exists in the given destination and can not be removed.'));
      return;
    }
    if (file_exists($recipeYml) && !$this->fileSystem->unlink($recipeYml)) {
      $this->messenger->addError($this->t('A recipe.yml already exists in the given destination and can not be removed.'));
      return;
    }
    if (file_exists($readmeMd) && !$this->fileSystem->unlink($readmeMd)) {
      $this->messenger->addError($this->t('A README.md already exists in the given destination and can not be removed.'));
      return;
    }
    if (!$this->fileSystem->mkdir($configDestination, FileSystem::CHMOD_DIRECTORY, TRUE)) {
      $this->messenger->addError($this->t('The destination does not exist or is not writable.'));
      return;
    }
    if (!is_writable($configDestination)) {
      $this->messenger->addError($this->t('The destination is not writable.'));
      return;
    }
    $this->fileSystem->prepareDirectory($destination);
    $this->fileSystem->prepareDirectory($configDestination);

    foreach ($configFiles as $fileName => $data) {
      $this->fileSystem->saveData($data, $configDestination . '/' . $fileName, FileExists::Replace);
    }

    // Values the model expresses win over what a previous export left in the
    // files. The preservation itself stays in place for everything the model
    // still cannot express, as a migration aid for published recipes.
    $expressed = $summaryIsExpressed ? ['description'] : [];
    if ($recipes) {
      $expressed[] = 'recipes';
    }
    $composer = $this->mergeComposer($existingComposer, $this->getComposer($entity->id(), $namespace, $summary, $dependencies['module']), $expressed);
    $recipe = $this->mergeRecipe($existingRecipe, $this->getRecipe($name, $summary, $dependencies['module'], $actions, $imports, $recipes), $expressed);
    $this->fileSystem->saveData(json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . PHP_EOL, $composerJson, FileExists::Replace);
    $this->fileSystem->saveData(Yaml::encode($recipe), $recipeYml, FileExists::Replace);
    $this->fileSystem->saveData($this->getReadme($entity->id(), $name, $documentation, $namespace, $owner->docBaseUrl()), $readmeMd, FileExists::Replace);
  }

  /**
   * Turns the model's config action list into the recipe's name-keyed map.
   *
   * A model stores its config actions as a list, because a config name always
   * contains dots and Drupal rejects a dot in any config array key. The recipe
   * file format keys its actions by config name, so the two shapes differ and
   * the conversion belongs here, at the boundary between them.
   *
   * Entries without a config name are skipped rather than producing an action
   * under an empty key, which a recipe could not apply.
   *
   * @param array $configActions
   *   The config actions as a list of maps, each with a "config" key holding
   *   the config name and an "actions" key holding the actions for it.
   *
   * @return array
   *   The config actions keyed by config name.
   *
   * @see \Drupal\Core\Config\ConfigBase::validateKeys()
   */
  protected function configActionsByName(array $configActions): array {
    $actions = [];
    foreach ($configActions as $configAction) {
      if (!is_array($configAction)) {
        continue;
      }
      $configName = $configAction['config'] ?? '';
      if (!is_string($configName) || $configName === '') {
        continue;
      }
      $actions[$configName] = $configAction['actions'] ?? [];
    }
    return $actions;
  }

  /**
   * Warns about config files that the export is about to delete.
   *
   * The config directory is wiped and regenerated on every export, which is
   * correct once the directory is fully derived from the model. A recipe whose
   * model has not adopted the export_config setting yet may still carry a
   * hand-added file there, and losing it silently is what this warning ends.
   *
   * @param string $configDestination
   *   The config directory of the recipe.
   * @param array $expected
   *   The file names the export is about to write into that directory.
   */
  protected function warnAboutUnexpectedConfigFiles(string $configDestination, array $expected): void {
    if (!is_dir($configDestination)) {
      return;
    }
    foreach ($this->fileSystem->scanDirectory($configDestination, '/\.yml$/', ['recurse' => FALSE]) as $file) {
      if (in_array($file->filename, $expected, TRUE)) {
        continue;
      }
      $this->messenger->addWarning($this->t('The config file @name in the recipe is not generated by this export and is being removed. Add its config object to the model to keep it.', [
        '@name' => $file->filename,
      ]));
    }
  }

  /**
   * Derives a one-line summary from the long-form documentation.
   *
   * Used when a model does not express a summary of its own, so that an
   * existing model still yields a sensible recipe description without being
   * edited first.
   *
   * @param string $documentation
   *   The documentation of the model.
   *
   * @return string
   *   The derived summary, or an empty string if there is nothing to derive
   *   it from.
   */
  protected function summaryFromDocumentation(string $documentation): string {
    $documentation = trim($documentation);
    if ($documentation === '') {
      return '';
    }
    // The leading paragraph, that is everything up to the first blank line,
    // with its own line breaks collapsed into single spaces.
    $paragraph = (string) preg_split('/\R[ \t]*\R/', $documentation, 2)[0];
    $paragraph = trim((string) preg_replace('/\s+/', ' ', $paragraph));
    if (mb_strlen($paragraph) <= self::SUMMARY_MAX_LENGTH) {
      return $paragraph;
    }
    // A leading paragraph running over several sentences is no longer a
    // one-line description, so fall back to its first sentence.
    if (preg_match('/^.*?[.!?](?=\s)/u', $paragraph, $matches) === 1 && mb_strlen($matches[0]) <= self::SUMMARY_MAX_LENGTH) {
      return $matches[0];
    }
    // Neither is short enough, so cut on the last word boundary that leaves
    // room for the ellipsis within the limit.
    $suffix = ' ...';
    $truncated = mb_substr($paragraph, 0, self::SUMMARY_MAX_LENGTH - mb_strlen($suffix));
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== FALSE) {
      $truncated = mb_substr($truncated, 0, $lastSpace);
    }
    return rtrim($truncated, " \t\n\r\0\x0B.,;:") . $suffix;
  }

  /**
   * Removes the modeler specific payload from the model's own config.
   *
   * A recipe is a distribution artifact, and the consuming site needs the
   * model rather than the diagram of whichever modeler the author happened to
   * use. The raw data is redundant, because the model is fully described by
   * its own config, it is by far the largest part of the exported file, and it
   * binds the recipe to a modeler the consuming site may not have installed.
   * Storage is forced to none for the same reason, so that a re-save on the
   * consuming site does not reinstate a payload the recipe never carried.
   *
   * Everything else in the map is portable model metadata and is preserved,
   * most notably the label, which is the model's only human-readable name.
   *
   * @param array $config
   *   The config data of the model's own config entity.
   *
   * @return array
   *   The config data without the modeler specific payload.
   */
  protected function stripModelerData(array $config): array {
    $settings = $config['third_party_settings'][self::THIRD_PARTY_PROVIDER] ?? NULL;
    if (!is_array($settings)) {
      return $config;
    }
    unset($settings['data'], $settings['modeler_id']);
    $settings['storage'] = Settings::STORAGE_OPTION_NONE;
    $config['third_party_settings'][self::THIRD_PARTY_PROVIDER] = $settings;
    return $config;
  }

  /**
   * Reads an existing JSON metadata file.
   *
   * @param string $filename
   *   The filename to read.
   *
   * @return array
   *   The decoded data, or an empty array when the file does not exist.
   */
  private function readExistingJson(string $filename): array {
    if (!file_exists($filename)) {
      return [];
    }
    $data = json_decode((string) file_get_contents($filename), TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
      throw new \UnexpectedValueException(sprintf('%s does not contain a JSON object.', $filename));
    }
    return $data;
  }

  /**
   * Reads an existing YAML metadata file.
   *
   * @param string $filename
   *   The filename to read.
   *
   * @return array
   *   The decoded data, or an empty array when the file does not exist.
   */
  private function readExistingYaml(string $filename): array {
    if (!file_exists($filename)) {
      return [];
    }
    $data = Yaml::decode((string) file_get_contents($filename));
    if (!is_array($data)) {
      throw new \UnexpectedValueException(sprintf('%s does not contain a YAML mapping.', $filename));
    }
    return $data;
  }

  /**
   * Merges generated composer metadata into an existing file.
   *
   * Dependency information and package identity are refreshed. Additional
   * package metadata is preserved, and so is a curated description for as long
   * as the model does not express a summary of its own.
   *
   * @param array $existing
   *   The existing composer metadata.
   * @param array $generated
   *   The newly generated composer metadata.
   * @param array $expressed
   *   The keys the model expresses itself, which therefore overwrite what the
   *   existing file holds instead of being preserved.
   *
   * @return array
   *   The merged composer metadata.
   */
  protected function mergeComposer(array $existing, array $generated, array $expressed = []): array {
    if (!$existing) {
      return $generated;
    }

    $result = $existing;
    foreach (['name', 'type', 'license', 'require'] as $key) {
      if (array_key_exists($key, $generated)) {
        $result[$key] = $generated[$key];
      }
      else {
        unset($result[$key]);
      }
    }
    if (in_array('description', $expressed, TRUE) || !array_key_exists('description', $result)) {
      $result['description'] = $generated['description'];
    }
    return $result;
  }

  /**
   * Merges generated recipe metadata into an existing file.
   *
   * Model-derived values are refreshed while recipe composition, curated
   * descriptions, and config actions not owned by the exporter are preserved.
   * That preservation is a migration aid for recipes whose model does not
   * express these values yet: as soon as the model does express one, the
   * model wins.
   *
   * @param array $existing
   *   The existing recipe metadata.
   * @param array $generated
   *   The newly generated recipe metadata.
   * @param array $expressed
   *   The keys the model expresses itself, which therefore overwrite what the
   *   existing file holds instead of being preserved.
   *
   * @return array
   *   The merged recipe metadata.
   */
  protected function mergeRecipe(array $existing, array $generated, array $expressed = []): array {
    if (!$existing) {
      return $generated;
    }

    $result = $existing;
    foreach (['name', 'type', 'install'] as $key) {
      if (array_key_exists($key, $generated)) {
        $result[$key] = $generated[$key];
      }
      else {
        unset($result[$key]);
      }
    }
    if (in_array('description', $expressed, TRUE) || !array_key_exists('description', $result)) {
      $result['description'] = $generated['description'];
    }
    if (in_array('recipes', $expressed, TRUE)) {
      $result['recipes'] = $generated['recipes'];
    }

    $config = is_array($result['config'] ?? NULL) ? $result['config'] : [];
    $generatedConfig = $generated['config'] ?? [];
    if (array_key_exists('import', $generatedConfig)) {
      $config['import'] = $generatedConfig['import'];
    }
    else {
      unset($config['import']);
    }
    if (!array_key_exists('strict', $config)) {
      $config['strict'] = $generatedConfig['strict'] ?? FALSE;
    }

    $actions = [];
    foreach ($config['actions'] ?? [] as $configName => $action) {
      if (!str_starts_with($configName, 'user.role.')) {
        $actions[$configName] = $action;
      }
    }
    foreach ($generatedConfig['actions'] ?? [] as $configName => $action) {
      $actions[$configName] = $action;
    }
    if ($actions) {
      $config['actions'] = $actions;
    }
    else {
      unset($config['actions']);
    }
    $result['config'] = $config;

    return $result;
  }

  /**
   * Gets the default name for the recipe.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The model owner's config entity.
   *
   * @return string
   *   The default name for the recipe.
   */
  public function defaultName(ConfigEntityInterface $entity): string {
    return (string) $entity->label();
  }

  /**
   * Helper function to determine if a config name is provided by given module.
   *
   * @param string $module
   *   The module.
   * @param string $configName
   *   The config name.
   *
   * @return bool
   *   TRUE, if that module provides that config, FALSE otherwise.
   */
  private function isProvidedByModule(string $module, string $configName): bool {
    $pathname = $this->fileSystem->dirName($this->moduleExtensionList->getPathname($module));
    foreach (['install', 'optional'] as $item) {
      if (file_exists($pathname . '/config/' . $item . '/' . $configName . '.yml')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Builds the content of the composer.json file.
   *
   * @param string $id
   *   The recipe ID.
   * @param string $namespace
   *   The namespace.
   * @param string $description
   *   The recipe description.
   * @param array $modules
   *   The list of required module names.
   *
   * @return array<string, array<string,string>|string>
   *   The content of the composer.json file as an array.
   */
  protected function getComposer(string $id, string $namespace, string $description, array $modules = []): array {
    $composer = [
      'name' => $namespace . '/' . $id,
      'type' => 'drupal-recipe',
      'description' => $description,
      'license' => 'GPL-2.0-or-later',
    ];
    if ($modules) {
      $list = $this->moduleExtensionList->getList();
      foreach ($modules as $module) {
        $path = $this->moduleExtensionList->getPath($module);
        if (!str_starts_with($path, 'core/modules')) {
          foreach ($list[$module]->requires ?? [] as $key => $dependency) {
            if (str_starts_with($path, $this->moduleExtensionList->getPath($key) . '/')) {
              $module = $key;
              break;
            }
          }
          $composer['require']['drupal/' . $module] = '*';
        }
      }
    }
    return $composer;
  }

  /**
   * Builds the content of the recipe file.
   *
   * @param string $name
   *   The recipe name.
   * @param string $description
   *   The recipe description.
   * @param array $modules
   *   The list of required modules.
   * @param array $actions
   *   The list of config actions.
   * @param array $imports
   *   The list of config imports keyed by module name.
   * @param array $recipes
   *   The list of recipes to include.
   *
   * @return array<string, array|string>
   *   The content of the recipe file as an array.
   */
  protected function getRecipe(string $name, string $description, array $modules = [], array $actions = [], array $imports = [], array $recipes = []): array {
    $recipe = [
      'name' => $name,
      'description' => $description,
      'type' => 'Workflow',
    ];
    if ($recipes) {
      $recipe['recipes'] = $recipes;
    }
    if ($modules) {
      $recipe['install'] = $modules;
    }
    if ($actions) {
      $recipe['config']['actions'] = $actions;
    }
    if ($imports) {
      $recipe['config']['import'] = $imports;
    }
    if (!isset($recipe['config'])) {
      $recipe['config'] = [];
    }
    $recipe['config']['strict'] = FALSE;
    return $recipe;
  }

  /**
   * Builds the content of the readme file.
   *
   * @param string $id
   *   The ID of the recipe.
   * @param string $name
   *   The recipe name.
   * @param string $description
   *   The recipe description.
   * @param string $namespace
   *   The namespace.
   * @param string|null $url
   *   The optional base URL for relative links.
   *
   * @return string
   *   The content of the readme file.
   */
  protected function getReadme(string $id, string $name, string $description, string $namespace, ?string $url): string {
    if ($url) {
      $description = str_replace(['](/', '.md)'], [
        '](' . $url . '/',
        ')',
      ], $description);
    }
    return <<<end_of_readme
## Recipe: $name

ID: $id

$description

### Installation

```shell
## Import recipe
composer require $namespace/$id

# Apply recipe with Drush (requires version 13 or later):
drush recipe ../recipes/$id

# Apply recipe without Drush:
cd web && php core/scripts/drupal recipe ../recipes/$id

# Rebuilding caches is optional, sometimes required:
drush cr
```
end_of_readme;
  }

}
