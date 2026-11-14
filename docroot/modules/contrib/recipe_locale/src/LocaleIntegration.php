<?php

declare(strict_types=1);

namespace Drupal\recipe_locale;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\locale\File\LocaleFileManager;
use Drupal\locale\LocaleConfigManager;
use Drupal\locale\LocaleDefaultOptions;
use Drupal\locale\LocaleFetch;
use Drupal\locale\LocaleProjectRepository;
use Drupal\locale\LocaleSource;

/**
 * Connects the recorded recipes to the Interface Translation module.
 *
 * Only registered when Locale is installed.
 *
 * @see \Drupal\recipe_locale\RecipeLocaleServiceProvider
 */
final class LocaleIntegration {

  use StringTranslationTrait;

  public function __construct(
    private readonly RecipeTracker $tracker,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LocaleProjectRepository $projectRepository,
    private readonly LocaleSource $localeSource,
    private readonly LocaleFileManager $fileManager,
    private readonly LocaleFetch $localeFetch,
    private readonly LocaleConfigManager $localeConfigManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Tells Locale about new recipes and downloads their translations.
   *
   * This does what Locale does when a module is installed. It does nothing
   * during site install: the installer downloads translations for every
   * project at the end.
   *
   * @param string[] $names
   *   Project names that were added or changed.
   *
   * @see locale_system_update()
   */
  public function update(array $names): void {
    if (InstallerKernel::installationAttempted()) {
      return;
    }
    // Rebuild the project list, so the recipes are in it.
    $projects = $this->projectRepository->buildProjects();
    $names = array_values(array_intersect($names, array_keys($projects)));
    if (!$names || !$this->hasTranslatableLanguages()) {
      return;
    }
    if (!$this->configFactory->get('locale.settings')->get('translation.import_enabled')) {
      return;
    }
    $batch = $this->localeFetch->buildUpdateBatch($names, [], LocaleDefaultOptions::updateOptions());
    if ($batch) {
      batch_set($batch);
    }
    // Locale only refreshes config it already knows the strings of. The
    // config a recipe shipped is new to it, so translate that explicitly once
    // the files are in. Later imports then find these strings on their own.
    batch_set([
      'title' => $this->t('Translating recipe configuration'),
      'operations' => [[self::class . ':batchTranslateConfig', [$names]]],
    ]);
  }

  /**
   * Applies the imported translations to the config some recipes shipped.
   *
   * @param string[] $names
   *   Project names of the recipes.
   * @param string[] $langcodes
   *   Language codes, or empty for all languages on the site.
   *
   * @return int
   *   The number of config objects that changed.
   */
  public function translateConfig(array $names, array $langcodes = []): int {
    $config_names = $this->tracker->getShippedConfigNames($names);
    if (!$config_names) {
      return 0;
    }
    return $this->localeConfigManager->updateConfigTranslations($config_names, $langcodes);
  }

  /**
   * Implements callback_batch_operation().
   *
   * @param string[] $names
   *   Project names of the recipes.
   * @param array<string, mixed>|\ArrayAccess $context
   *   The batch context.
   */
  public function batchTranslateConfig(array $names, array|\ArrayAccess &$context): void {
    $context['results']['recipe_locale_config'] = $this->translateConfig($names);
    $context['message'] = $this->t('Translated the configuration of @count recipes.', ['@count' => count($names)]);
  }

  /**
   * Removes a recipe from the list and from Locale's data.
   *
   * @param string $name
   *   The project name.
   */
  public function remove(string $name): void {
    $this->cleanUp([$name]);
    $this->tracker->forget($name);
  }

  /**
   * Removes every recipe from Locale's data.
   *
   * Used when the module is uninstalled. The list in config goes away with the
   * module; this takes care of what Locale stored about the recipes.
   */
  public function removeAll(): void {
    $names = array_keys($this->tracker->getAll());
    foreach ($this->projectRepository->getAll() as $project_name => $project) {
      if ($project->type === RecipeTracker::PROJECT_TYPE) {
        $names[] = $project_name;
      }
    }
    $this->cleanUp(array_unique($names));
  }

  /**
   * Deletes what Locale stored about some projects: files, status, records.
   *
   * These are the same steps Locale takes when a module is uninstalled.
   *
   * @param string[] $names
   *   Project names.
   *
   * @see locale_system_remove()
   */
  private function cleanUp(array $names): void {
    $names = array_values(array_intersect($names, array_keys($this->projectRepository->getAll())));
    if (!$names) {
      return;
    }
    // The files are found through the project records, so delete them first.
    $this->fileManager->deleteTranslationFiles($names, []);
    $this->localeSource->deleteSources($names);
    $this->projectRepository->deleteMultiple($names);
  }

  /**
   * Tells whether the site has a language Locale can translate into.
   *
   * English only counts when Locale is set to translate English too, the same
   * rule core applies before it downloads translations.
   */
  private function hasTranslatableLanguages(): bool {
    $languages = $this->languageManager->getLanguages();
    if (!$this->configFactory->get('locale.settings')->get('translate_english')) {
      unset($languages['en']);
    }
    return (bool) $languages;
  }

}
