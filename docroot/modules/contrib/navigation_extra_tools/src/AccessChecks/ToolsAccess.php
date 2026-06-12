<?php

namespace Drupal\navigation_extra_tools\AccessChecks;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Class for validating user has permission to see Tools menu.
 */
class ToolsAccess implements AccessInterface {

  public function __construct(
    protected AccessManagerInterface $accessManager,
    protected MenuLinkTreeInterface $menuTree,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Check if any children are available to user.
   *
   * As we only care that there are allowed links, bail out as soon as we find
   * one. Since each item will have its own permission, we only need to check
   * the immediate children.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $subtree
   *   The subtree currently being checked.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   */
  private function checkTreeAccess(array $subtree, AccountInterface $account): bool {
    foreach ($subtree as $item) {
      // Get the route name of the menu item.
      $route = $item->link->getRouteName();
      // Make sure it's a named route, and not the front page.
      if ($route) {
        if ($this->accessManager->checkNamedRoute(
          route_name: $route,
          account: $account,
          return_as_object: TRUE,
        )->isAllowed()) {
          // As soon as we find an allowed route, we know the parent should be
          // visible, so return a positive integer.
          return TRUE;
        }
      }
    }
    // If we haven't found an available child, we shouldn't have access.
    return FALSE;
  }

  /**
   * Check user has access to Tools menu.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check access for.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Result of routing.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The user access to the route.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account): AccessResult {
    $subtree = NULL;
    switch ($route_match->getRouteName()) {
      case 'navigation_extra_tools.overview':
        // Fetch the first level of the "tools" submenu of the "admin" menu,
        // excluding disabled items.
        $parameters = new MenuTreeParameters();
        $parameters->setRoot('navigation_extra_tools.help');
        $parameters->excludeRoot();
        $parameters->setMaxDepth(1);
        $parameters->onlyEnabledLinks();
        $subtree = $this->menuTree->load('admin', $parameters);
        // Check the user has access to at least one entry in the subtree.
        return AccessResult::allowedIf($this->checkTreeAccess($subtree, $account));

      case 'navigation_extra_tools.devel':
        // If module Devel is enabled.
        if ($this->moduleHandler->moduleExists('devel')) {
          // Devel menu should be enabled if user has either of the permissions
          // of sub-menus, but as one of them is coming from the Devel module,
          // we can't specify it in the routing file.
          return AccessResult::allowedIfHasPermissions(
            account: $account,
            permissions: ['administer site configuration', 'access devel information'],
            conjunction: 'OR',
          );
        }
    }
    // We shouldn't reach here, but if we do, access should be denied.
    return AccessResult::forbidden();
  }

}
