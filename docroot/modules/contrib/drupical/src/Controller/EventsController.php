<?php

declare(strict_types=1);

namespace Drupal\drupical\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\drupical\EventsRenderer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for events feed AJAX operations.
 */
class EventsController extends ControllerBase {

  /**
   * Constructs an EventsController object.
   *
   * @param \Drupal\drupical\EventsRenderer $eventsRenderer
   *   The EventsRenderer service.
   */
  public function __construct(
    protected EventsRenderer $eventsRenderer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('drupical.renderer')
    );
  }

  /**
   * AJAX callback to load more events.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function loadMore(Request $request): AjaxResponse {
    $response = new AjaxResponse();

    $offset = (int) $request->query->get('offset', 0);
    $limit = (int) $request->query->get('limit', 5);

    // Get more events efficiently.
    $total_count = $this->eventsRenderer->getTotalCount();
    $events = $this->eventsRenderer->getEvents($offset, $limit);
    $has_more = ($offset + count($events)) < $total_count;

    // Render new events.
    if (!empty($events)) {
      foreach ($events as $event) {
        $event_build = [
          '#theme' => 'drupical_item',
          '#event' => $event,
          '#featured' => $event->featured,
        ];
        $response->addCommand(new AppendCommand('.events-table tbody', $event_build));
      }
    }

    // Update or remove Load More button.
    if (!$has_more) {
      $response->addCommand(new RemoveCommand('.events-load-more'));
    }
    else {
      // Update button with new offset.
      $new_offset = $offset + $limit;
      $new_button = [
        '#type' => 'container',
        '#attributes' => ['class' => ['events-load-more']],
        'link' => [
          '#type' => 'link',
          '#title' => $this->t('Load More Events'),
          '#url' => Url::fromRoute('drupical.load_more', [], [
            'query' => [
              'offset' => $new_offset,
              'limit' => $limit,
            ],
          ]),
          '#attributes' => [
            'class' => ['button', 'button--extrasmall', 'use-ajax'],
            'data-offset' => $new_offset,
            'data-limit' => $limit,
          ],
        ],
      ];
      $response->addCommand(new ReplaceCommand('.events-load-more', $new_button));
    }

    return $response;
  }

}
