<?php

declare(strict_types=1);

namespace Drupal\recipe_locale\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\recipe_locale\RecipeTracker;

/**
 * Hook implementations for the Interface Translation module.
 */
final readonly class LocaleHooks {

  public function __construct(
    private RecipeTracker $tracker,
  ) {}

  /**
   * Implements hook_locale_translation_projects_alter().
   *
   * Adds the recorded recipes to the project list. From then on Locale
   * downloads and updates their translations as it does for modules.
   *
   * @param array<string, array<string, mixed>> $projects
   *   The project list.
   */
  #[Hook('locale_translation_projects_alter')]
  public function localeTranslationProjectsAlter(array &$projects): void {
    $projects += $this->tracker->getProjects();
  }

}
