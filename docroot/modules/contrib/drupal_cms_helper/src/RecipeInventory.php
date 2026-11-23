<?php

namespace Drupal\drupal_cms_helper;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Recipe\Recipe;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides machine-usable information about a recipe and its dependencies.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final readonly class RecipeInventory {

  public function __construct(
    private ModuleExtensionList $moduleList,
    private ThemeExtensionList $themeList,
    private ProjectResolver $projectResolver,
  ) {}

  /**
   * Lists extensions that a recipe installs.
   *
   * @param \Drupal\Core\Recipe\Recipe ...$stack
   *   The stack of recipes being analyzed.
   *
   * @return array<string, string>
   *   A map of extension names to Composer package names. This includes all
   *   extensions the recipe will install, and their dependencies.
   */
  public function getExtensions(Recipe ...$stack): array {
    $handler = function (Recipe $recipe): array {
      $data = Yaml::parseFile($recipe->path . '/recipe.yml');
      $extensions = $data['install'] ?? [];

      foreach ($extensions as $name) {
        array_push($extensions, ...$this->getDependencies($name));
      }
      $extensions = array_unique($extensions);
      $extensions = array_map($this->getExtension(...), $extensions);

      return array_combine(
        array_map(fn (Extension $extension): string => $extension->getName(), $extensions),
        array_map($this->projectResolver->getPackageName(...), $extensions),
      );
    };

    $extensions = [];
    foreach ($stack as $recipe) {
      $extensions += $handler($recipe);
    }
    ksort($extensions);
    return $extensions;
  }

  /**
   * Lists where all of a recipe's imported config is from.
   *
   * @param \Drupal\Core\Recipe\Recipe ...$stack
   *   The stack of recipes being analyzed.
   *
   * @return \Drupal\drupal_cms_helper\PathMapStorage
   *   A read-only config storage.
   */
  public function listImportSources(Recipe ...$stack): PathMapStorage {
    $list = [];
    foreach ($stack as $recipe) {
      $list += $this->getImportSources($recipe);
    }
    ksort($list);
    return new PathMapStorage($list);
  }

  /**
   * Lists all content provided by a recipe.
   *
   * @param \Drupal\Core\Recipe\Recipe ...$stack
   *   The stack of recipes being analyzed.
   *
   * @return array<string, string>
   *   A map of content UUIDs to entity type IDs.
   */
  public function getContent(Recipe ...$stack): array {
    $handler = function (Recipe $recipe): array {
      $content = $recipe->content->data;

      return array_combine(
        array_map(fn (array $item): string => $item['_meta']['uuid'], $content),
        array_map(fn (array $item): string => $item['_meta']['entity_type'], $content),
      );
    };

    $items = [];
    foreach ($stack as $recipe) {
      $items += $handler($recipe);
    }
    return $items;
  }

  /**
   * Lists the config that a recipe imports.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   A recipe.
   *
   * @return array<string, string>
   *   A map whose keys are the config names that this recipe will import, and
   *   values are the path of the config file.
   */
  private function getImportSources(Recipe $recipe): array {
    $list_storage = function (FileStorage $storage): array {
      $list = $storage->listAll();
      return array_combine($list, array_map($storage->getFilePath(...), $list));
    };

    $list = $list_storage(
      new FileStorage($recipe->path . '/config'),
    );

    $imports = $recipe->config->config['import'] ?? [];
    foreach ($imports as $extension_name => $what) {
      if ($what === NULL) {
        continue;
      }
      $path = $this->getExtension($extension_name)->getPath();

      $from_extension = [
        ...$list_storage(
          new FileStorage($path . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY),
        ),
        ...$list_storage(
          new FileStorage($path . '/' . InstallStorage::CONFIG_OPTIONAL_DIRECTORY),
        ),
      ];

      if (is_array($what)) {
        $from_extension = array_intersect_key($from_extension, array_flip($what));
      }
      $list += $from_extension;
    }
    return $list;
  }

  /**
   * Lists all dependencies of an extension, recursively.
   *
   * @param string $name
   *   The name of an extension.
   * @param array<string, bool> $seen
   *   The extensions that have already been examined, to prevent infinite
   *   recursion.
   *
   * @return array<int, string>
   *   All dependencies of the extension.
   */
  private function getDependencies(string $name, array $seen = []): array {
    if (isset($seen[$name])) {
      return [];
    }
    $seen[$name] = TRUE;

    // @phpstan-ignore-next-line property.notFound
    $dependencies = array_keys($this->getExtension($name)->requires);

    foreach ($dependencies as $dependency) {
      assert(is_string($dependency));
      array_push($dependencies, ...$this->getDependencies($dependency, $seen));
    }
    return $dependencies;
  }

  /**
   * Returns the full stack of recipes that a particular recipe will apply.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   A recipe.
   *
   * @return \Drupal\Core\Recipe\Recipe[]
   *   The full stack of recipes that the given recipe will apply (including
   *   itself), in the other they will be applied.
   */
  public static function toStack(Recipe $recipe): array {
    $stack = [$recipe];
    foreach ($recipe->recipes->recipes as $r) {
      array_unshift($stack, ...self::toStack($r));
    }
    return $stack;
  }

  /**
   * Returns an extension, regardless of type.
   *
   * @param string $name
   *   The extension name.
   *
   * @return \Drupal\Core\Extension\Extension
   *   The extension, if found.
   */
  private function getExtension(string $name): Extension {
    try {
      return $this->moduleList->get($name);
    }
    catch (UnknownExtensionException) {
      return $this->themeList->get($name);
    }
  }

}
