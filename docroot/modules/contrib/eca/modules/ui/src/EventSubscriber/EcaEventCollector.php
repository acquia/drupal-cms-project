<?php

namespace Drupal\eca_ui\EventSubscriber;

use Drupal\Core\Link;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eca\Processor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Collects all ECA events applied during the request lifecycle.
 *
 * This subscriber runs on KernelEvents::RESPONSE with a very low priority
 * to capture events that fire after hook_page_bottom, such as those triggered
 * during response assembly. The collected events are rendered into the ECA
 * inspector widget for users that may view or edit ECA models.
 */
final class EcaEventCollector implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs an EcaEventCollector object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider.
   */
  public function __construct(
    protected StateInterface $state,
    protected AccountInterface $currentUser,
    protected RouteProviderInterface $routeProvider,
  ) {}

  /**
   * Renders the ECA inspector widget on kernel response.
   *
   * Collects the events that were applied during the request and, if the
   * current user is permitted to view or edit ECA models, injects the
   * inspector widget into the response. Each applied event links to its ECA
   * model: editable for users with the edit permission, read-only otherwise.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $response = $event->getResponse();
    if (!($response instanceof HtmlResponse)) {
      // Restrict injection to Drupal HTML responses so non-page responses are
      // never modified by this subscriber.
      return;
    }
    $content = $response->getContent();
    if ($content === FALSE) {
      // \Symfony\Component\HttpFoundation\BinaryFileResponse and others don't
      // allow adding content as intended here. Therefore, we're returning
      // early.
      return;
    }
    $canEdit = $this->currentUser->hasPermission('modeler api edit eca');
    $canView = $canEdit || $this->currentUser->hasPermission('modeler api view eca');
    if (!$canView) {
      return;
    }
    // Editable users open the modeler edit form; view-only users open the
    // read-only canonical route, which is the same path without the trailing
    // "/edit" segment. The canonical route is only registered when more than
    // one modeler is available, so fall back to plain labels when the relevant
    // route does not exist.
    $routeName = $canEdit ? 'entity.eca.edit_form' : 'entity.eca.canonical';
    if (!$this->routeExists($routeName)) {
      $routeName = NULL;
    }
    $events = [];
    foreach (Processor::getAppliedEvents() as $processDebugger) {
      if (!$processDebugger->isStarted()) {
        continue;
      }
      $label = $processDebugger->getEventLabel();
      if ($routeName === NULL) {
        $events[] = $label;
        continue;
      }
      $query = ['select' => $processDebugger->getEventId()];
      // The history hash is only meaningful when debug mode recorded a
      // history; without it there is no debugger data to open.
      $hash = $processDebugger->getHistoryHash();
      if ($hash !== '') {
        $query['hash'] = $hash;
      }
      $events[] = Link::createFromRoute($label, $routeName, ['eca' => $processDebugger->getEcaId()], [
        'query' => $query,
      ])->toString();
    }
    if ($events) {
      $response->setContent(str_replace('</body>', '<div id="eca-inspector-applied-events"><h2>' . $this->t('ECA events applied on this page') . '</h2><div class="item-list"><ul><li>' . implode('</li><li>', $events) . '</li></ul></div></div></body>', $content));
    }
  }

  /**
   * Checks whether a route with the given name is registered.
   *
   * @param string $routeName
   *   The route name to check.
   *
   * @return bool
   *   TRUE if the route exists, FALSE otherwise.
   */
  protected function routeExists(string $routeName): bool {
    try {
      $this->routeProvider->getRouteByName($routeName);
      return TRUE;
    }
    catch (RouteNotFoundException) {
      return FALSE;
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
