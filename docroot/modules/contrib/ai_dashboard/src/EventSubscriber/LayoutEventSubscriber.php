<?php

namespace Drupal\ai_dashboard\EventSubscriber;

use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Contain events subscribers for layout builder.
 */
class LayoutEventSubscriber implements EventSubscriberInterface {

  /**
   * Reacts to section component build render array event.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $build = $event->getBuild();
    $contexts = $event->getContexts();
    if (isset($contexts['dashboard'])) {
      $dashboard = $contexts['dashboard']->getContextData()->getValue();
      if ($dashboard && $dashboard->id() === 'ai_dashboard') {
        $build['#attached']['library'][] = 'ai_dashboard/ai_dashboard';
      }
    }
    $event->setBuild($build);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => [
        'onBuildRender',
      ],
    ];
  }

}
