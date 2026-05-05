<?php

declare(strict_types=1);

namespace Drupal\morphos\Hook;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Contains hook implementations for Morphos.
 */
final class ThemeHooks {

  /**
   * The Drupal root.
   */
  private static ?string $appRoot = NULL;

  /**
   * Constructs a new ThemeHooks instance.
   *
   * @param \Drupal\Core\Extension\ThemeSettingsProvider $themeSettings
   *   The theme settings provider.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeList
   *   The theme extension list.
   * @param string $appRoot
   *   The Drupal application root.
   */
  public function __construct(
    private readonly ThemeSettingsProvider $themeSettings,
    private readonly RequestStack $requestStack,
    private readonly ThemeExtensionList $themeList,
    #[Autowire(param: 'app.root')] string $appRoot,
  ) {
    self::$appRoot ??= $appRoot;
  }

  /**
   * Implements template_preprocess_image_widget().
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];

    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

  /**
   * Implements template_preprocess_html().
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    // Get the theme base path for font preloading.
    $variables['morphos_path'] = $this->requestStack->getCurrentRequest()
      ->getBasePath() . '/' . $this->themeList->getPath('morphos');

    // Get default scheme from theme settings.
    $variables['scheme'] = $this->themeSettings->getSetting("definition.default.scheme");

    // Get header and footer sticky settings.
    $skins = $this->themeSettings->getSetting('skins');
    $default_skin = $this->themeSettings->getSetting('definition.default.skin')
      ?? (is_array($skins) ? array_key_first($skins) : NULL);

    if (!empty($skins) && !empty($default_skin)) {
      require_once self::$appRoot . '/' . $this->themeList->getPath('morphos') . '/theme_colors/theme_colors_variables.inc';
      $active_skin = _get_apply_skin($skins) ?? $default_skin;

      $variables['header_sticky'] = $skins[$active_skin]['settings']['header_sticky'] ?? FALSE;
      $variables['footer_sticky'] = $skins[$active_skin]['settings']['footer_sticky'] ?? FALSE;
    }
  }

  /**
   * Implements template_preprocess_views_view().
   */
  #[Hook('preprocess_views_view')]
  #[Hook('preprocess_views_view_unformatted')]
  public function preprocessView(array &$variables): void {
    $view = $variables['view'];
    assert($view instanceof ViewExecutable);
    $view_tags = preg_split('/\s+/', $view->storage->get('tag'));
    $variables['snap'] = in_array('snap', $view_tags, TRUE);
  }

  /**
   * Determines whether the current route is managed by Canvas.
   *
   * Returns TRUE for Canvas page entities, Canvas API routes, and nodes
   * rendered via an enabled Canvas content template.
   */
  public static function isCanvasRoute(): bool {
    $route_match = \Drupal::routeMatch();
    $route_name = $route_match->getRouteName() ?? '';

    // Canvas page entities and all Canvas API routes.
    if ($route_name === 'entity.canvas_page.canonical'
      || str_starts_with($route_name, 'canvas.api.')) {
      return TRUE;
    }

    // Nodes rendered via an enabled Canvas content template.
    if ($route_name === 'entity.node.canonical') {
      $node = $route_match->getParameter('node');
      if ($node instanceof FieldableEntityInterface) {
        $template = ContentTemplate::loadForEntity($node, 'full');
        return $template !== NULL && $template->status();
      }
    }

    return FALSE;
  }

  /**
   * Implements template_preprocess_page().
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $request = $this->requestStack->getCurrentRequest();
    // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
    $route_match = \Drupal::routeMatch();
    $variables['is_canvas_page'] = self::isCanvasRoute();

    // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
    $page_title = \Drupal::service('title_resolver')
      ->getTitle($request, $route_match->getRouteObject());
    $variables['page_title'] = $page_title;
  }

  /**
   * Implements template_preprocess_block().
   *
   * Passes header menu style and megamenu columns from the active skin
   * settings to the header main menu block template.
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(array &$variables): void {
    if (($variables['plugin_id'] ?? '') !== 'system_menu_block:main') {
      return;
    }

    $skins = $this->themeSettings->getSetting('skins');
    $default_skin = $this->themeSettings->getSetting('definition.default.skin')
      ?? (is_array($skins) ? array_key_first($skins) : NULL);

    if (empty($skins) || empty($default_skin)) {
      $variables['header_menu_style'] = 'dropdown';
      $variables['megamenu_columns'] = '4';
      return;
    }

    require_once self::$appRoot . '/' . $this->themeList->getPath('morphos') . '/theme_colors/theme_colors_variables.inc';
    $active_skin = _get_apply_skin($skins) ?? $default_skin;

    $variables['header_menu_style'] = $skins[$active_skin]['settings']['header_menu_style'] ?? 'dropdown';
    $variables['megamenu_columns'] = $skins[$active_skin]['settings']['header_menu_megamenu_cols'] ?? '4';
  }

}
