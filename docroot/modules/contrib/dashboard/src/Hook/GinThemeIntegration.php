<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Gin theme integration hooks.
 */
class GinThemeIntegration {

  public function __construct(
    protected ThemeManagerInterface $themeManager,
  ) {}

  /**
   * If the admin theme is gin, we add extra css that uses gin styling.
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): array {
    if ($extension != 'dashboard') {
      return $libraries;
    }

    $active_theme = $this->themeManager->getActiveTheme();
    $base_theme_extensions = $active_theme->getBaseThemeExtensions();

    if ($active_theme->getName() === 'gin' || in_array('gin', array_keys($base_theme_extensions))) {
      $libraries['dashboard']['css']['theme'] += ['css/dashboard.gin.css' => []];
    }

    return $libraries;
  }

}
