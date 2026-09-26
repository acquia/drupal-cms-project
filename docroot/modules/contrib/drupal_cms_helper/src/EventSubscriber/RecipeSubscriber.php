<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Reacts to recipe events for Drupal CMS.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class RecipeSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleExtensionList $moduleList,
    private readonly ThemeExtensionList $themeList,
    #[Autowire(param: 'app.root')] private readonly string $appRoot,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RecipeAppliedEvent::class => 'onApply',
      ConsoleEvents::TERMINATE => 'onConsoleTerminate',
    ];
  }

  /**
   * Clears stale caches after recipe apply.
   */
  public function onApply(RecipeAppliedEvent $event): void {
    $recipe = $event->recipe;

    // When installing Drupal using a monolithic site template at the command
    // line, user 1 may be statically cached with stale field definitions, which
    // can cause errors when the account is modified during the installation by
    // \Drupal\Core\Installer\Form\SiteConfigureForm. To prevent that, clear the
    // static cache for user 1.
    // @todo Remove when https://www.drupal.org/i/3578151 is released.
    if ($recipe->type === 'Site' && PHP_SAPI === 'cli' && InstallerKernel::installationAttempted()) {
      $this->entityTypeManager->getStorage('user')->resetCache([1]);
    }

    // If the recipe has installed Language, then we need to ensure at least one
    // configurable language exists (the site's default), ignoring locked ones.
    // @todo Remove when https://www.drupal.org/i/3622004 is released.
    if (in_array('language', $recipe->install->modules, TRUE)) {
      $languages = $this->entityTypeManager->getStorage('configurable_language')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('locked', FALSE)
        ->range(0, 1)
        ->execute();

      if (empty($languages)) {
        // The language manager synthesizes a default even with no entities, so
        // this is the language the site is already pretending it has.
        $language = $this->languageManager->getDefaultLanguage()->getId();
        ConfigurableLanguage::createFromLangcode($language)->save();
      }
    }
  }

  /**
   * Adds additional information to the output of `recipe:info`.
   *
   * @api
   *   The additional information provided by this method is part of Drupal
   *   CMS's developer-facing API and may be relied upon. The method itself is
   *   internal and can be changed or removed at any time without warning.
   */
  public function onConsoleTerminate(ConsoleTerminateEvent $event): void {
    if ($event->getCommand()->getName() === 'recipe:info') {
      $input = $event->getInput();

      $directory = realpath($input->getArgument('path'));
      assert(is_string($directory));

      $stack = $this->toStack(
        Recipe::createFromDirectory($directory),
      );
      $io = new SymfonyStyle($input, $event->getOutput());

      $io->section((string) $this->t('Package'));
      $io->text($this->getPackageName($directory) ?: '?');

      $this->listImportSources($stack, $io);
      $this->listExtensions($stack, $io);
      $this->listContent($stack, $io);
    }
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
  private function toStack(Recipe $recipe): array {
    $stack = [$recipe];
    foreach ($recipe->recipes->recipes as $r) {
      array_unshift($stack, ...$this->toStack($r));
    }
    return $stack;
  }

  /**
   * Lists where all of a recipe's imported config is from.
   *
   * @param \Drupal\Core\Recipe\Recipe[] $stack
   *   The stack of recipes being analyzed.
   * @param \Symfony\Component\Console\Style\StyleInterface $io
   *   The I/O handler.
   */
  private function listImportSources(array $stack, StyleInterface $io): void {
    $list = [];
    foreach ($stack as $recipe) {
      $list += $this->getImportSources($recipe);
    }
    ksort($list);

    $header = array_map('strval', [
      $this->t('Name'),
      $this->t('Path'),
    ]);
    // Awkward as hell, but this is what an array zip looks like in PHP.
    $rows = array_map(NULL, array_keys($list), array_values($list));

    if ($rows) {
      $io->section((string) $this->t('Imports'));
      $io->table($header, $rows);
    }
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
      $path = $this->appRoot . '/' . $this->getExtension($extension_name)->getPath();

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
   * Lists extensions that a recipe installs.
   *
   * @param \Drupal\Core\Recipe\Recipe[] $stack
   *   The stack of recipes being analyzed.
   * @param \Symfony\Component\Console\Style\StyleInterface $io
   *   The I/O handler.
   */
  private function listExtensions(array $stack, StyleInterface $io): void {
    $extensions = [];
    foreach ($stack as $recipe) {
      $extensions += $this->getExtensions($recipe);
    }
    ksort($extensions);

    $header = array_map('strval', [
      $this->t('Name'),
      $this->t('Package'),
    ]);
    $rows = array_map(
      NULL,
      array_keys($extensions),
      array_values($extensions),
    );
    if ($rows) {
      $io->section((string) $this->t('Extensions'));
      $io->table($header, $rows);
    }
  }

  /**
   * Lists the extensions installed by a recipe, and their dependencies.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   A recipe.
   *
   * @return array<string, string>
   *   A map of extension names to Composer package names. This includes all
   *   extensions the recipe will install, and their dependencies.
   */
  private function getExtensions(Recipe $recipe): array {
    $data = Yaml::parseFile($recipe->path . '/recipe.yml');
    $extensions = $data['install'] ?? [];

    foreach ($extensions as $name) {
      array_push($extensions, ...$this->getDependencies($name));
    }
    $extensions = array_unique($extensions);

    return array_combine(
      $extensions,
      array_map($this->detectPackage(...), $extensions),
    );
  }

  /**
   * Lists all content provided by a recipe.
   *
   * @param \Drupal\Core\Recipe\Recipe[] $stack
   *   The stack of recipes being analyzed.
   * @param \Symfony\Component\Console\Style\StyleInterface $io
   *   The I/O handler.
   */
  private function listContent(array $stack, StyleInterface $io): void {
    $items = [];
    foreach ($stack as $recipe) {
      $items += $this->getContent($recipe);
    }

    $header = array_map('strval', [
      $this->t('Type'),
      $this->t('UUID'),
    ]);
    $rows = array_map(
      NULL,
      array_values($items),
      array_keys($items),
    );
    if ($rows) {
      $io->section((string) $this->t('Content'));
      $io->table($header, $rows);
    }
  }

  /**
   * Lists a recipe's content.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   A recipe.
   *
   * @return array<string, string>
   *   A map of content UUIDs to entity type IDs.
   */
  private function getContent(Recipe $recipe): array {
    $content = $recipe->content->data;

    return array_combine(
      array_map(fn (array $item): string => $item['_meta']['uuid'], $content),
      array_map(fn (array $item): string => $item['_meta']['entity_type'], $content),
    );
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
   * Returns the Composer package name for a particular path.
   *
   * @param string $path
   *   The path of a directory containing `composer.json`.
   *
   * @return string|null
   *   The package name, or NULL if it can't be determined.
   */
  private function getPackageName(string $path): ?string {
    if (str_contains($path, '/core/recipes/')) {
      return 'drupal/core';
    }

    static $cache = [];
    if (array_key_exists($path, $cache)) {
      return $cache[$path];
    }
    $file = $path . '/composer.json';
    if (file_exists($file)) {
      $data = Json::decode((string) file_get_contents($file));
      $name = $data['name'] ?? NULL;
    }
    return $cache[$path] = $name ?? NULL;
  }

  /**
   * Determines which Composer package an extension belongs to.
   *
   * @param string $name
   *   The name of an extension.
   *
   * @return string
   *   The Composer package name, or NULL if it cannot be determined.
   */
  private function detectPackage(string $name): ?string {
    $extension = $this->getExtension($name);
    $path = $extension->getPath();

    if ($extension->info['project'] ?? NULL) {
      return 'drupal/' . $extension->info['project'];
    }
    elseif (str_starts_with($path, 'core/')) {
      return 'drupal/core';
    }

    // Try to get the package name from the nearest `composer.json`.
    $path = $this->appRoot . '/' . $path;
    while (str_contains($path, $this->appRoot . '/')) {
      $name = $this->getPackageName($path);
      if ($name) {
        return $name;
      }
      $path = dirname($path);
    }
    return NULL;
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
