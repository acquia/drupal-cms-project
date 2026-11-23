<?php

namespace Drupal\modeler\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drupal\modeler_api\Plugin\ModelerPluginManager;

/**
 * Implements link hooks for the Modeler module.
 */
class LinkHooks {

  /**
   * Whether the htmx library is required.
   */
  protected static ?bool $htmxLibraryRequired = NULL;

  /**
   * The modeler API controller class.
   */
  protected const string MODELER_API_CONTROLLER = 'Drupal\modeler_api\Controller\ModelerApi';

  /**
   * Constructs a new LinkHooks object.
   */
  public function __construct(
    protected RouteProviderInterface $routeProvider,
    protected ModelerPluginManager $pluginManager,
  ) {}

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_links')]
  public function linkPreprocess(array &$variables): void {
    if (self::$htmxLibraryRequired === TRUE) {
      $variables['#attached']['library'][] = 'core/drupal.htmx';
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_item_list')]
  public function itemListPreprocess(array &$variables): void {
    if (!empty($variables['attributes']['data-modeler-api-edit-links'])) {
      $variables['#attached']['library'][] = 'core/drupal.htmx';
    }
  }

  /**
   * Implements hook_link_alter().
   *
   * Applies HTMX attributes to links pointing to modeler_api edit or view
   * routes, enabling inline content loading within the workflow modeler.
   */
  #[Hook('link_alter')]
  public function linkAlter(array &$variables): void {
    if (self::$htmxLibraryRequired === FALSE) {
      return;
    }
    /** @var \Drupal\Core\Url $url */
    $url = $variables['url'];
    if (!$url->isRouted()) {
      return;
    }
    if (count($this->pluginManager->getDefinitions()) !== 2) {
      // Let's only apply the hook if this modeler and the fallback is
      // available, otherwise the user still needs the choice.
      self::$htmxLibraryRequired = FALSE;
      return;
    }
    $routeName = $url->getRouteName();
    if (!$this->isModelerApiAddOrEditOrViewRoute($routeName)) {
      return;
    }
    // For generic add routes (without a specific modeler), resolve the direct
    // modeler-specific URL to avoid a server-side redirect that would lose
    // HTMX parameters (wrapper format and ajax page state).
    $htmxUrl = $this->resolveDirectAddUrl($routeName) ?? $url;
    self::$htmxLibraryRequired = TRUE;
    // Build a temporary render array to apply HTMX attributes.
    $item = [
      '#url' => $htmxUrl,
    ];
    (new Htmx())
      ->get($item['#url'])
      ->onlyMainContent()
      ->select('#workflow-modeler-wrapper')
      ->target('.page-wrapper')
      ->swap('afterbegin')
      ->applyTo($item);
    // Merge the HTMX attributes back into the link variables.
    $variables['options']['attributes'] = array_merge(
      $variables['options']['attributes'] ?? [],
      $item['#attributes'] ?? [],
    );
  }

  /**
   * Resolves a generic add route to the direct modeler-specific URL.
   *
   * When only one modeler (plus fallback) exists, the generic add route
   * redirects to the modeler-specific add route. For HTMX requests, this
   * redirect causes loss of wrapper format and ajax page state parameters,
   * resulting in a full HTML page response with duplicate scripts. This
   * method returns the direct URL to bypass the redirect.
   *
   * @param string $routeName
   *   The route name to check.
   *
   * @return \Drupal\Core\Url|null
   *   The direct modeler-specific add URL, or NULL if not applicable.
   */
  protected function resolveDirectAddUrl(string $routeName): ?Url {
    try {
      $route = $this->routeProvider->getRouteByName($routeName);
    }
    catch (\Exception) {
      return NULL;
    }
    $controller = $route->getDefault('_controller');
    if ($controller !== self::MODELER_API_CONTROLLER . '::add') {
      return NULL;
    }
    // If the route already has a modelerId, no redirect will happen.
    if ($route->getDefault('modelerId') !== NULL) {
      return NULL;
    }
    $ownerId = $route->getDefault('ownerId');
    if ($ownerId === NULL) {
      return NULL;
    }
    // Get the single non-fallback modeler plugin ID.
    $definitions = $this->pluginManager->getDefinitions();
    unset($definitions['fallback']);
    if (count($definitions) !== 1) {
      return NULL;
    }
    $modelerId = key($definitions);
    try {
      return Url::fromRoute('modeler_api.add.' . $ownerId . '.' . $modelerId);
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Checks if a route is a modeler_api edit or view route.
   *
   * @param string $routeName
   *   The route name to check.
   *
   * @return bool
   *   TRUE if the route uses the modeler_api edit or view controller.
   */
  protected function isModelerApiAddOrEditOrViewRoute(string $routeName): bool {
    try {
      $route = $this->routeProvider->getRouteByName($routeName);
    }
    catch (\Exception) {
      return FALSE;
    }
    $controller = $route->getDefault('_controller');
    if ($controller === NULL) {
      return FALSE;
    }
    return $controller === self::MODELER_API_CONTROLLER . '::edit'
      || $controller === self::MODELER_API_CONTROLLER . '::view'
      || $controller === self::MODELER_API_CONTROLLER . '::add';
  }

}
