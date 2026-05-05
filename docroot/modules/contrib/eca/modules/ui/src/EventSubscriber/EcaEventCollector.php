<?php

namespace Drupal\eca_ui\EventSubscriber;

use Drupal\Core\Link;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eca\Processor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Collects all ECA events applied during the request lifecycle.
 *
 * This subscriber runs on KernelEvents::RESPONSE with a very low priority
 * to capture events that fire after hook_page_bottom, such as those triggered
 * during response assembly. The collected events are stored on the request
 * attributes so that other components (e.g., the debug panel in
 * RenderHooks::pageBottom) can access the full list.
 */
final class EcaEventCollector implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs an EcaEventCollector object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Collects late ECA events on kernel response.
   *
   * Drains any remaining events from the Processor that were dispatched
   * after the page render phase, merges them with the early-collected
   * events, and stores the complete list as a request attribute.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest() || !($this->state->get('_eca_internal_debug_mode', FALSE) ?? FALSE)) {
      return;
    }
    $events = [];
    foreach (Processor::getAppliedEvents() as $processDebugger) {
      if (!$processDebugger->isStarted()) {
        continue;
      }
      $events[] = Link::createFromRoute($processDebugger->getEventLabel(), 'entity.eca.edit_form', ['eca' => $processDebugger->getEcaId()], [
        'query' => [
          'select' => $processDebugger->getEventId(),
          'hash' => $processDebugger->getHistoryHash(),
        ],
      ])->toString();
    }
    if ($events) {
      $event->getResponse()->setContent(str_replace('</body>', '<div id="eca-ui-debug-applied-events"><h2>' . $this->t('ECA events applied on this page') . '</h2><div class="item-list"><ul><li>' . implode('</li><li>', $events) . '</li></ul></div></div></body>', $event->getResponse()->getContent()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Use a very low priority to run after most other response subscribers,
      // maximizing the chance that we capture all ECA events.
      KernelEvents::RESPONSE => ['onResponse', -1024],
    ];
  }

}
