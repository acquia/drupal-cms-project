<?php

namespace Drupal\gin_login\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\gin_login\Services\GinLoginRouteService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Contains \Drupal\gin_login\Theme\ThemeNegotiator.
 *
 * Credit to jimconte
 * https://jimconte.com/blog/web/dynamic-theme-switching-in-drupal-8.
 */
class ThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * ConfigFactoryInterface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The Gin Login Route Service.
   *
   * @var \Drupal\gin_login\Services\GinLoginRouteService
   */
  protected GinLoginRouteService $ginLoginRouteService;

  /**
   * Constructor for service initialization.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory interface.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The current request stack.
   * @param \Drupal\gin_login\Services\GinLoginRouteService $ginLoginRouteService
   *   The Gin Login Route Service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, RequestStack $requestStack, GinLoginRouteService $ginLoginRouteService) {
    $this->configFactory = $configFactory;
    $this->requestStack = $requestStack;
    $this->ginLoginRouteService = $ginLoginRouteService;
  }

  /**
   * RouteMatchInterface.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route Match.
   *
   * @return bool
   *   Returns boolean
   */
  public function applies(RouteMatchInterface $route_match) {
    return $this->negotiateRoute($route_match) ? TRUE : FALSE;
  }

  /**
   * RouteMatchInterface.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route Match.
   *
   * @return null|string
   *   Returns Null or String.
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return $this->negotiateRoute($route_match) ?: NULL;
  }

  /**
   * Function that does all of the work in selecting a theme.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route Match.
   *
   * @return bool|string
   *   Returns Boolean or String.
   */
  private function negotiateRoute(RouteMatchInterface $route_match) {
    $route_definitions = $this->ginLoginRouteService->getLoginRouteDefinitions();
    $route_name = $route_match->getRouteName() ?? '';
    $request_route = $this->requestStack->getCurrentRequest()?->attributes->get('_route') ?? '';

    if (
      array_key_exists($route_name, $route_definitions) ||
      array_key_exists($request_route, $route_definitions)
    ) {
      return $this->configFactory->get('system.theme')->get('admin');
    }

    return FALSE;
  }

}
