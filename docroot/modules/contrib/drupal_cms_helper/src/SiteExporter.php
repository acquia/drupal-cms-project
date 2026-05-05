<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageCopyTrait;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DefaultContent\Exporter;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Exports the current site as a recipe.
 *
 * @api
 *   This is part of Drupal CMS's developer-facing API and may be relied upon.
 *   You may also take advantage of the public helper methods `loadAllContent()`
 *   and `getExtensionRequirements()`.
 */
final class SiteExporter implements LoggerAwareInterface {

  use LoggerAwareTrait;
  use StorageCopyTrait;
  use StringTranslationTrait;

  /**
   * The directory where Composer installs recipes, or FALSE if there is none.
   */
  private string|false|null $cookbook = NULL;

  public function __construct(
    private readonly ModuleExtensionList $moduleList,
    private readonly ThemeExtensionList $themeList,
    private readonly FileSystemInterface $fileSystem,
    #[Autowire(service: 'config.storage.export')] private readonly StorageInterface $storage,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ConfigManagerInterface $configManager,
    private readonly Exporter $contentExporter,
    #[Autowire(param: 'app.root')] private readonly string $appRoot,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly ClassResolverInterface $classResolver,
  ) {}

  /**
   * Exports the current site's configuration and content into a recipe.
   *
   * @param string $destination
   *   The path where the recipe should be created.
   * @param string|null $base
   *   (optional) The path of a recipe to use as the base for the export, or
   *   NULL to not use a base recipe at all.
   */
  public function export(string $destination, ?string $base = NULL): void {
    if ($base && is_dir($base)) {
      $this->copyBaseRecipe($base, $destination);
    }
    else {
      if ($base) {
        $this->logger?->warning('Base recipe %path was not found. Exporting the site anyway, but this may produce unintended results.', [
          '%path' => $base,
        ]);
      }
      $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    }

    $listener = $this->classResolver->getInstanceFromDefinition(GenericConfigurationListener::class);
    assert($listener instanceof GenericConfigurationListener);
    $listener->convertFrontPagePathToAlias = TRUE;
    $this->eventDispatcher->addListener(ConfigEvents::STORAGE_TRANSFORM_EXPORT, $listener);

    // Initially, just export all config as files. Then we'll convert certain
    // items to config actions.
    // @todo Use a plain FileStorage object when
    //   https://www.drupal.org/i/3002532 is released.
    $storage = new class ($destination . '/config') extends FileStorage {

      /**
       * {@inheritdoc}
       */
      public function write($name, array $data): bool {
        if (preg_match('/^language\.entity\.(?!und|zxx)/', $name)) {
          $data['dependencies']['config'][] = 'language.entity.und';
          $data['dependencies']['config'][] = 'language.entity.zxx';
        }
        return parent::write($name, $data);
      }

    };
    self::replaceStorageContents($this->storage, $storage);
    // From here on out, we're only going to modify the default collection. Any
    // other collections probably just contain translations, and config actions
    // are not translatable (yet).
    $storage = $storage->createCollection(StorageInterface::DEFAULT_COLLECTION);
    // The core.extension config should never be included in a recipe.
    $storage->delete('core.extension');
    // We're exporting a site template, which is meant to be shared, so we don't
    // need to protect the configuration.
    $this->fileSystem->delete($destination . '/config/.htaccess');

    $actions = [];
    foreach ($storage->listAll() as $name) {
      if ($this->isAction($name)) {
        $actions[$name] = $this->toAction($name, $storage->read($name));
        $storage->delete($name);
      }
    }
    // The site name and mail are almost always collected during the install
    // process and shouldn't be exported.
    unset(
      $actions['system.site']['simpleConfigUpdate']['name'],
      $actions['system.site']['simpleConfigUpdate']['mail'],
    );

    $extensions = $this->getInstalledExtensions();
    $recipe = [
      'name' => $this->configFactory->get('system.site')->get('name'),
      // This marks the recipe as a site template.
      'type' => 'Site',
      'install' => array_keys($extensions),
      'config' => [
        // Do a lenient comparison against extant config. In the early
        // installer, the active config storage will be an InstallStorage object
        // that has loaded and enumerated all simple config shipped by modules.
        // This will likely differ from the simple config from the recipe, so
        // strict mode will likely fail. If this recipe is a site template being
        // applied at install time (i.e., the main reason to export a site as a
        // recipe), lenient mode doesn't make much difference; during the actual
        // install process, only the config shipped with required modules (e.g.,
        // System and User) will be present when the recipe is applied, and that
        // stuff is exported as config actions.
        'strict' => FALSE,
        'actions' => $actions,
      ],
    ];
    file_put_contents($destination . '/recipe.yml', Yaml::encode($recipe));
    $this->writeComposerJson($destination, $extensions);

    // Export all content, with its dependencies, as files.
    $loader = $this->classResolver->getInstanceFromDefinition(ContentLoader::class);
    foreach ($loader as $entity) {
      $this->contentExporter->exportWithDependencies($entity, $destination . '/content');
    }
  }

  /**
   * Copies a base recipe into the destination directory.
   *
   * Everything from the base recipe will be copied, except for its content.
   * Any `*.example` files in the base recipe will have the `.example` suffix
   * stripped.
   *
   * @param string $base
   *   The path of the base recipe.
   * @param string $destination
   *   The destination directory.
   */
  private function copyBaseRecipe(string $base, string $destination): void {
    $finder = Finder::create()
      ->in($base)
      ->files()
      ->ignoreVCS(TRUE)
      ->ignoreDotFiles(FALSE)
      // Exclude the content, configuration, and `recipe.yml` from the base
      // recipe, since those will be regenerated.
      ->notPath(['config', 'content'])
      ->notName('recipe.yml');

    $file_system = new Filesystem();
    $file_system->mirror($base, $destination, $finder, ['override' => TRUE]);

    // Rename any `*.example` files to remove the `.example` suffix, so that
    // the files will actually be used.
    $finder = Finder::create()
      ->in($destination)
      ->files()
      ->ignoreDotFiles(FALSE)
      ->name(['*.example', '.*.example']);

    foreach ($finder as $file) {
      $path = $file->getPathname();
      $file_system->rename($path, substr($path, 0, -8), TRUE);
    }
  }

  /**
   * Finds all installed modules and themes.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   All installed extensions, keyed by machine name.
   */
  private function getInstalledExtensions(): array {
    $modules = array_intersect_key(
      $this->moduleList->getList(),
      $this->moduleList->getAllInstalledInfo(),
    );
    $themes = array_intersect_key(
      $this->themeList->getList(),
      $this->themeList->getAllInstalledInfo(),
    );
    return array_filter(
      [...$modules, ...$themes],
      // Install profiles should always be excluded from recipes.
      fn (Extension $e): bool => $e->getType() !== 'profile',
    );
  }

  /**
   * Generates Composer version constraints for a set of extensions.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   A set of extensions.
   *
   * @return array<string, string>
   *   An array of Composer version constraints, keyed by package name.
   */
  public function getExtensionRequirements(array $extensions): array {
    $requirements = [];

    foreach ($extensions as $name => $extension) {
      $package_name = str_starts_with($extension->getPath(), 'core/')
        ? 'drupal/core'
        : $this->getPackageName($extension);

      try {
        $version = InstalledVersions::getPrettyVersion($package_name);
        $stability = VersionParser::parseStability($version);
        $stability = VersionParser::normalizeStability($stability);
      }
      catch (\OutOfBoundsException) {
        $message = $this->t('Cannot determine a version constraint for @type @name because the Composer package @package does not appear to be installed. Falling back to an allow-all (*) constraint for now, but it is strongly recommended that you adjust it. See https://getcomposer.org/doc/articles/versions.md for more information about version constraints.', [
          '@type' => $extension->getType(),
          '@name' => $name,
          '@package' => $package_name,
        ]);
        $this->logger?->warning((string) $message);

        $version = '*';
        $stability = 'dev';
      }
      $requirements[$package_name] = $stability === 'dev' ? $version : "^$version";

      if ($stability !== 'stable') {
        $message = $this->t('Package @package has a @stability version constraint, which may prevent the recipe from being installed into projects that require stable dependencies.', [
          '@stability' => $stability,
          '@package' => $package_name,
        ]);
        $this->logger?->warning((string) $message);
      }
    }
    return $requirements;
  }

  /**
   * Alters `composer.json` to match the site being exported.
   *
   * - The `name` key is automatically generated from the name of the
   *   destination directory.
   * - The `type` key is always set to `drupal-recipe`.
   * - The `require` section is completely overwritten with version constraints
   *   generated for all installed extensions.
   *
   * @param string $destination
   *   The directory where the site is being exported.
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   All installed extensions.
   */
  private function writeComposerJson(string $destination, array $extensions): void {
    $destination .= '/composer.json';

    $data = file_exists($destination)
      ? Json::decode(file_get_contents($destination))
      : [];

    // The site template is not installable without a name, so set a sensible
    // default.
    $data['name'] = 'drupal/' . basename(dirname($destination));
    $data['type'] = Recipe::COMPOSER_PROJECT_TYPE;

    // Rebuild the list of requirements.
    $data['require'] = $this->getExtensionRequirements($extensions);

    // Remove development-only stuff from drupal_cms_site_template_base (and
    // possibly others).
    unset(
      $data['require']['drupal/site_template_helper'],
      $data['extra']['drupal-site-template'],
    );

    $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($destination, $data);
  }

  /**
   * Exports a config item as a config action.
   *
   * @param string $name
   *   The name of the config item.
   * @param array $data
   *   The config item's data.
   *
   * @return array
   *   The config item, represented as a config action.
   */
  private function toAction(string $name, array $data): array {
    $entity_keys = $this->configManager->loadConfigEntityByName($name)
      ?->getEntityType()
      ->getKeys();

    // If we have an array of entity keys, then this is a config entity.
    if (is_array($entity_keys)) {
      // The `id` and `uuid` keys cannot be changed by the `setProperties`
      // config action, so delete them. In fact, we don't need to touch ANY
      // entity keys.
      // @see \Drupal\Core\Config\Action\Plugin\ConfigAction\SetProperties
      foreach ($entity_keys as $key) {
        unset($data[$key]);
      }
      // Let dependencies be recalculated on save.
      unset($data['dependencies']);

      return ['setProperties' => $data];
    }
    return ['simpleConfigUpdate' => $data];
  }

  /**
   * Determines if a config object needs to be exported in config actions.
   *
   * This is true for all default config shipped by core itself, as well as the
   * System and User modules, because those are guaranteed to be installed
   * before anything else is.
   *
   * @param string $name
   *   The name of a config object.
   *
   * @return bool
   *   Whether the config object can be exported as a file, or needs to be
   *   represented as a config action.
   */
  private function isAction(string $name): bool {
    static $list;
    if ($list === NULL) {
      $list = [];

      $directory = InstallStorage::CONFIG_INSTALL_DIRECTORY;
      $storages = [
        new FileStorage($this->appRoot . "/core/$directory"),
        new FileStorage($this->moduleList->getPath('system') . "/$directory"),
        new FileStorage($this->moduleList->getPath('user') . "/$directory"),
      ];
      foreach ($storages as $storage) {
        $list = array_merge($list, $storage->listAll());
      }
    }
    return in_array($name, $list, TRUE);
  }

  /**
   * Tries to get the Composer package name for a specific extension.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension.
   *
   * @return string
   *   The extension's package name, or `drupal/NAME` if it can't be determined.
   */
  private function getPackageName(Extension $extension): string {
    // Statically cache the locations of the PHP interpreter and Composer, since
    // they're a tad expensive to compute.
    static $php, $composer;

    $php ??= (new PhpExecutableFinder())->find();

    // Try to determine where Composer is.
    if ($composer === NULL) {
      $finder = new ExecutableFinder();
      $finder->addSuffix('.phar');
      // If Composer is locally installed in the project, include it in the
      // search.
      try {
        $extra_directories = [
          InstalledVersions::getInstallPath('composer/composer') . '/bin',
        ];
      }
      catch (\OutOfBoundsException) {
        $extra_directories = [];
      }
      // If we can't find Composer, hope it's just globally available somehow.
      $composer = $finder->find('composer', 'composer', $extra_directories);
    }

    $process = new Process([
      $php,
      $composer,
      'config',
      'name',
      '--working-dir=' . $this->appRoot . DIRECTORY_SEPARATOR . $extension->getPath(),
    ]);
    if ($process->run() === 0) {
      return trim($process->getOutput());
    }
    $fallback = 'drupal/' . ($extension->info['project'] ?? $extension->getName());

    $message = $this->t('Could not determine the Composer package name for @type @name; assuming @fallback.', [
      '@type' => $extension->getType(),
      '@name' => basename($fallback),
      '@fallback' => $fallback,
    ]);
    $this->logger?->warning((string) $message);
    return $fallback;
  }

  /**
   * Returns the path to a recipe, or the path where Composer installs them.
   *
   * @param string|null $name
   *   (optional) A recipe package name, or NULL to return the path where
   *   Composer is configured to install recipes.
   *
   * @return string|null
   *   A path which may or may not exist, or NULL if it cannot be determined.
   */
  public function getRecipePath(?string $name = NULL): ?string {
    if ($this->cookbook === FALSE) {
      return NULL;
    }
    elseif ($this->cookbook) {
      return rtrim(
        str_replace(['{$vendor}', '{$name}'], $name ? explode('/', $name, 2) : '', $this->cookbook),
        '.' . DIRECTORY_SEPARATOR,
      );
    }

    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    $project_root = realpath($project_root);
    assert(is_string($project_root));

    $data = Json::decode(
      file_get_contents($project_root . DIRECTORY_SEPARATOR . 'composer.json'),
    );
    $directory = array_find_key(
      $data['extra']['installer-paths'] ?? [],
      fn (array $criteria): bool => in_array('type:' . Recipe::COMPOSER_PROJECT_TYPE, $criteria, TRUE),
    );
    $this->cookbook = $directory
      ? $project_root . DIRECTORY_SEPARATOR . ltrim($directory, '.' . DIRECTORY_SEPARATOR)
      : FALSE;

    return $this->getRecipePath($name);
  }

}
