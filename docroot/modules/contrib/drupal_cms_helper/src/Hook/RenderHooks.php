<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class RenderHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * @todo Remove when https://www.drupal.org/i/3551708 is released.
   */
  #[Hook('menu_local_tasks_alter')]
  public function alterBuiltLocalTasks(array &$build, string $route_name): void {
    if ($route_name === 'entity.node.canonical' || $route_name === 'entity.node.edit_form') {
      $url = Url::fromRoute('entity.entity_view_display.node.default', [
        'node_type' => $this->routeMatch->getParameter('node')?->getType(),
      ]);
      $build['tabs'][0]['entity.node.template'] = [
        '#theme' => 'menu_local_task',
        '#link' => [
          'title' => $this->t('Edit template'),
          'url' => $url,
          'localized_options' => [],
        ],
        '#active' => FALSE,
        '#weight' => 5,
        '#access' => $url->access(return_as_object: TRUE),
      ];
    }
  }

}
