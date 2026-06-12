<?php

namespace Drupal\navigation_extra_tools\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver class to add extra links to the navigation menus.
 */
class ToolsMenuDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteProviderInterface $routeProvider,
    protected ThemeHandlerInterface $themeHandler,
    protected ConfigFactoryInterface $configFactory,
    protected AccountInterface $currentUser,
    protected MenuLinkTreeInterface $menuLinkTree,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('router.route_provider'),
      $container->get('theme_handler'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('menu.link_tree'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    // If module Devel is enabled.
    if ($this->moduleHandler->moduleExists('devel')) {

      // Load devel menu options configured by the devel module form
      // admin/config/development/devel/toolbar.
      $parameters = new MenuTreeParameters();
      $parameters->onlyEnabledLinks()->setTopLevelOnly();
      $develMenuTree = $this->menuLinkTree->load('devel', $parameters);

      $links['devel'] = [
        'title' => $this->t('Development'),
        'description' => 'Development functions provided by the Devel module.',
        'route_name' => 'navigation_extra_tools.devel',
        'parent' => 'navigation_extra_tools.help',
        'weight' => '-8',
      ] + $base_plugin_definition;

      foreach ($develMenuTree as $key => $element) {
        // Add by default in tools menu.
        if ($key == 'devel.cache_clear' || $key == 'devel.run_cron') {
          continue;
        }

        // Add only if the link is selected in Devel Toolbar settings.
        $develToolbarConfig = $this->configFactory->get('devel.toolbar.settings');
        if (!$develToolbarConfig->get('toolbar_items') || !in_array($key, $develToolbarConfig->get('toolbar_items'))) {
          continue;
        }

        $link = $element->link;
        $links[$key] = [
          'title' => $link->getTitle(),
          'route_name' => $link->getRouteName(),
          'parent' => $base_plugin_definition['id'] . ':devel',
          'weight' => $link->getWeight(),
        ] + $base_plugin_definition;
      }

      if ($this->moduleHandler->moduleExists('webprofiler')) {
        $links['devel.webprofiler'] = [
          'title' => $this->t('Webprofiler settings'),
          'route_name' => 'webprofiler.settings',
          'parent' => $base_plugin_definition['id'] . ':devel',
          'weight' => '-21',
        ] + $base_plugin_definition;
      }
      // If module Devel PHP is enabled.
      if ($this->moduleHandler->moduleExists('devel_php') && $this->routeExists('devel_php.execute_php')) {
        $links['devel.devel_php.execute_php'] = [
          'title' => $this->t('Execute PHP Code'),
          'route_name' => 'devel_php.execute_php',
          'parent' => $base_plugin_definition['id'] . ':devel',
        ] + $base_plugin_definition;
      }
    }

    // If Project Browser module is enabled.
    if ($this->moduleHandler->moduleExists('project_browser')) {
      $links['project_browser'] = [
        'title' => $this->t('Project Browser'),
        'description' => $this->t('Options for Project Browser module.'),
        'route_name' => 'navigation_extra_tools.project_browser',
        'parent' => 'navigation_extra_tools.help',
        'weight' => '-8',
      ] + $base_plugin_definition;
      $links['project_browser.clear_storage'] = [
        'title' => $this->t('Clear storage'),
        'route_name' => 'navigation_extra_tools.project_browser.clear_storage',
        'parent' => $base_plugin_definition['id'] . ':project_browser',
        'weight' => '-31',
      ] + $base_plugin_definition;
    }

    return $links;
  }

  /**
   * Determine if a route exists by name.
   *
   * @param string $route_name
   *   The name of the route to check.
   *
   * @return bool
   *   Whether a route with that route name exists.
   */
  public function routeExists($route_name) {
    return (count($this->routeProvider->getRoutesByNames([$route_name])) === 1);
  }

}
