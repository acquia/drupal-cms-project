<?php

declare(strict_types=1);

namespace Drupal\navigation_extra_tools\Controller;

use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\Menu\ContextualLinkManager;
use Drupal\Core\Menu\LocalActionManager;
use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Theme\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Navigation Extra Tools routes.
 */
final class NavigationExtraToolsController extends ReloadableControllerBase {

  /**
   * The controller constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\CronInterface $cron
   *   The cron runner.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $pluginManagerMenuLink
   *   The menu link manager.
   * @param \Drupal\Core\Menu\ContextualLinkManager $pluginManagerMenuContextualLink
   *   The contextual menu link manager.
   * @param \Drupal\Core\Menu\LocalTaskManager $pluginManagerMenuLocalTask
   *   The local task menu link manager.
   * @param \Drupal\Core\Menu\LocalActionManager $pluginManagerMenuLocalAction
   *   The local action menu link manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheRender
   *   The render cache.
   * @param \Drupal\Core\Asset\AssetQueryStringInterface $assetQueryString
   *   The asset query string service, used for clearing CSS and JS cache.
   * @param \Drupal\Core\Plugin\CachedDiscoveryClearerInterface $pluginCacheClearer
   *   The plugin cache clearer.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheMenu
   *   The menu cache.
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   *   The twig environment.
   * @param \Drupal\Core\Theme\Registry $themeRegistry
   *   The theme registry.
   */
  public function __construct(
    readonly RequestStack $requestStack,
    private readonly CronInterface $cron,
    private readonly MenuLinkManagerInterface $pluginManagerMenuLink,
    private readonly ContextualLinkManager $pluginManagerMenuContextualLink,
    private readonly LocalTaskManager $pluginManagerMenuLocalTask,
    private readonly LocalActionManager $pluginManagerMenuLocalAction,
    private readonly CacheBackendInterface $cacheRender,
    private readonly AssetQueryStringInterface $assetQueryString,
    private readonly CachedDiscoveryClearerInterface $pluginCacheClearer,
    private readonly CacheBackendInterface $cacheMenu,
    private readonly TwigEnvironment $twig,
    private readonly Registry $themeRegistry,
  ) {
    parent::__construct($requestStack);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('request_stack'),
      $container->get('cron'),
      $container->get('plugin.manager.menu.link'),
      $container->get('plugin.manager.menu.contextual_link'),
      $container->get('plugin.manager.menu.local_task'),
      $container->get('plugin.manager.menu.local_action'),
      $container->get('cache.render'),
      $container->get('asset.query_string'),
      $container->get('plugin.cache_clearer'),
      $container->get('cache.menu'),
      $container->get('twig'),
      $container->get('theme.registry'),
    );
  }

  /**
   * Flushes all caches.
   */
  public function flushAll() {
    drupal_flush_all_caches();
    $this->messenger()->addMessage($this->t('All caches cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Flushes css and javascript caches.
   */
  public function flushJsCss() {
    $this->assetQueryString->reset();
    $this->messenger()->addMessage($this->t('CSS and JavaScript cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Flushes plugins caches.
   */
  public function flushPlugins() {
    $this->pluginCacheClearer->clearCachedDefinitions();
    $this->messenger()->addMessage($this->t('Plugins cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Resets all static caches.
   */
  public function flushStatic() {
    drupal_static_reset();
    $this->messenger()->addMessage($this->t('Static cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clears all cached menu data.
   */
  public function flushMenu() {
    $this->cacheMenu->deleteAll();
    $this->pluginManagerMenuLink->rebuild();
    $this->pluginManagerMenuContextualLink->clearCachedDefinitions();
    $this->pluginManagerMenuLocalTask->clearCachedDefinitions();
    $this->pluginManagerMenuLocalAction->clearCachedDefinitions();
    $this->messenger()->addMessage($this->t('Routing and links cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clear the rendered cache.
   */
  public function cacheRender() {
    $this->cacheRender->deleteAll();
    $this->messenger()->addMessage($this->t('Render cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clears all cached views data.
   */
  public function flushViews() {
    views_invalidate_cache();
    $this->messenger()->addMessage($this->t('Views cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clears the twig cache.
   */
  public function flushTwig() {
    $this->twig->invalidate();
    $this->messenger()->addMessage($this->t('Twig cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Rebuild the theme registry.
   */
  public function themeRebuild() {
    $this->themeRegistry->reset();
    $this->messenger()->addMessage($this->t('Theme registry rebuilt.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Run the cron.
   */
  public function runCron() {
    $this->cron->run();
    $this->messenger()->addMessage($this->t('Cron ran successfully.'));
    return new RedirectResponse($this->reloadPage());
  }

}
