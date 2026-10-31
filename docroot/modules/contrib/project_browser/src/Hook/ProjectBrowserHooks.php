<?php

declare(strict_types=1);

namespace Drupal\project_browser\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Implements hooks for the Project Browser module.
 */
final class ProjectBrowserHooks {

  use StringTranslationTrait;

  public function __construct(
    #[AutowireServiceClosure(ThemeManagerInterface::class)] private readonly \Closure $themeManager,
  ) {}

  #[Hook('help')]
  public function help(string $route_name): string {
    $output = '';
    if ($route_name === 'help.page.project_browser') {
      $output .= '<h3>' . $this->t('About') . '</h3>';
      $output .= '<p>' . $this->t("The Project Browser module allows users to easily search for available Drupal modules from your site. Enhanced filtering is provided so you can find what you need.") . '</p>';
      $output .= '<p>' . $this->t('For more information, see the <a href=":project_browser">online documentation for the Project Browser module</a>.', [':project_browser' => 'https://www.drupal.org/docs/contributed-modules/project-browser']) . '</p>';
      $output .= '<h3>' . $this->t('Uses') . '</h3>';
      $output .= '<dl>';
      $output .= '<dt>' . $this->t('Browsing modules') . '</dt>';
      $output .= '<dd>' . $this->t('Users who have the <em>Administer modules</em> can browse modules and recipes from the <em>Extend</em> section.') . '</dd>';
      $output .= '<dt>' . $this->t('Accessing more modules') . '</dt>';
      $output .= '<dd>' . $this->t('Users who have the <em>Administer site configuration</em> permission can select where to search for modules from the <a href=":project_browser_settings">Project Browser settings page</a>. This can include the modules already on your site as well as contributed modules on Drupal.org', [':project_browser_settings' => Url::fromRoute('project_browser.settings')->toString()]) . '</dd>';
      $output .= '</dl>';
    }
    return $output;
  }

  #[Hook('theme')]
  public function theme(): array {
    return [
      'project_browser_main_app' => [
        'render element' => 'element',
        'initial preprocess' => static::class . '::preprocessMainApp',
      ],
    ];
  }

  /**
   * Preprocess function for the project_browser_main_app theme hook.
   *
   * @param array $variables
   *   The variables to pass to the template.
   */
  public function preprocessMainApp(array &$variables): void {
    $variables['id'] = $variables['element']['#id'];

    if (($this->themeManager)()->getActiveTheme()->getName() === 'gin') {
      $variables['#attached']['library'] = 'project_browser/internal.gin-styles';
    }
  }

}
