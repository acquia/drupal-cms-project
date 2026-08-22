<?php

namespace Drupal\eca_views\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eca\Event\TriggerEvent;
use Drupal\views\Plugin\views\cache\CachePluginBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Implements views hooks for the ECA Views submodule.
 */
class ViewsHooks {

  /**
   * Constructs a new ViewsHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
  ) {}

  /**
   * Implements hook_views_query_substitutions().
   */
  #[Hook('views_query_substitutions')]
  public function viewsQuerySubstitutions(ViewExecutable $view): array {
    /** @var \Drupal\eca_views\Event\QuerySubstitutions $event */
    $event = $this->triggerEvent->dispatchFromPlugin('eca_views:query_substitutions', $view);
    return $event->getSubstitutions();
  }

  /**
   * Implements hook_views_pre_view().
   */
  #[Hook('views_pre_view')]
  public function viewsPreView(ViewExecutable $view, string $display_id, array &$args): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:pre_view', $view, $display_id, $args);
  }

  /**
   * Implements hook_views_pre_build().
   */
  #[Hook('views_pre_build')]
  public function viewsPreBuild(ViewExecutable $view): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:pre_build', $view);
  }

  /**
   * Implements hook_views_post_build().
   */
  #[Hook('views_post_build')]
  public function viewsPostBuild(ViewExecutable $view): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:post_build', $view);
  }

  /**
   * Implements hook_views_pre_execute().
   */
  #[Hook('views_pre_execute')]
  public function viewsPreExecute(ViewExecutable $view): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:pre_execute', $view);
  }

  /**
   * Implements hook_views_post_execute().
   */
  #[Hook('views_post_execute')]
  public function viewsPostExecute(ViewExecutable $view): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:post_execute', $view);
  }

  /**
   * Implements hook_views_pre_render().
   */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:pre_render', $view);
  }

  /**
   * Implements hook_views_post_render().
   */
  #[Hook('views_post_render')]
  public function viewsPostRender(ViewExecutable $view, array &$output, CachePluginBase $cache): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:post_render', $view, $output);
  }

  /**
   * Implements hook_views_query_alter().
   */
  #[Hook('views_query_alter')]
  public function viewsQueryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:query_alter', $view, $query);
  }

}
