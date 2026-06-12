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

    if ($name === NULL) {
      $name = $this->defaultName($entity);
    }
    $description = $owner->getDocumentation($entity);
    $dependencies = [
      'config' => [
        $owner->configEntityProviderId() . '.' . $owner->configEntityTypeId() . '.' . $entity->id(),
      ],
      'module' => [],
    ];
    if ($owner->storageMethod($entity) === Settings::STORAGE_OPTION_SEPARATE) {
      $dependencies['config'][] = 'modeler_api.data_model.' . $owner->storageId($entity);
    }
    $this->api->getNestedDependencies($dependencies, $entity->getDependencies());

    $actions = [];
    $imports = [];
    foreach ($dependencies['config'] as $configName) {
      $config = $this->configStorage->read($configName);
      if (!$config) {
        continue;
      }
      unset($config['uuid'], $config['_core']);
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
          $this->fileSystem->saveData(Yaml::encode($config), $configDestination . '/' . $configName . '.yml', FileExists::Replace);
        }
      }
    }

    $this->fileSystem->saveData(json_encode($this->getComposer($entity->id(), $namespace, $name, $dependencies['module']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . PHP_EOL, $composerJson, FileExists::Replace);
    $this->fileSystem->saveData(Yaml::encode($this->getRecipe($name, $description, $dependencies['module'], $actions, $imports)), $recipeYml, FileExists::Replace);
    $this->fileSystem->saveData($this->getReadme($entity->id(), $name, $description, $namespace, $owner->docBaseUrl()), $readmeMd, FileExists::Replace);
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
      $composer['require'] = [
        'drupal/core' => '>=11.2',
      ];
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
   *
   * @return array<string, array|string>
   *   The content of the recipe file as an array.
   */
  protected function getRecipe(string $name, string $description, array $modules = [], array $actions = [], array $imports = []): array {
    $recipe = [
      'name' => $name,
      'description' => $description,
      'type' => 'Workflow',
    ];
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
