<?php

declare(strict_types=1);

namespace Drupal\eca_htmx\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Marks matching routes as HTMX routes by setting the _htmx_route option.
 *
 * This action is designed to react to the "Alter route" event provided by the
 * eca_misc module (event id "routing", deriver "alter"). That event carries a
 * \Drupal\Core\Routing\RouteBuildEvent, which exposes the full RouteCollection
 * via getRouteCollection(). We read the collection directly from the triggering
 * event (via getEvent()), which is the cleanest mechanism: it operates on the
 * same collection the rest of the route-build pipeline uses, so the option is
 * persisted when route building finishes.
 *
 * When the option _htmx_route is TRUE, Drupal core treats the route as an HTMX
 * route and serves the minimal "drupal_htmx" response wrapper.
 */
#[Action(
  id: 'eca_htmx_set_route',
  label: new TranslatableMarkup('HTMX: mark route'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Mark one or more routes as HTMX routes by setting the "_htmx_route" option on them. Reacts to the "Alter route" event of the ECA Miscellaneous module. The route name may contain a "*" wildcard.'),
  version_introduced: '3.1.3',
)]
class SetRoute extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'route_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['route_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Route name'),
      '#description' => $this->t('The name of the route(s) to mark as HTMX routes, for example <em>entity.node.canonical</em>. A trailing or embedded <em>*</em> acts as a wildcard, for example <em>view.*</em> matches every route whose name starts with <em>view.</em>.'),
      '#default_value' => $this->configuration['route_name'],
      '#required' => TRUE,
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['route_name'] = $form_state->getValue('route_name');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = (isset($this->event) && $this->event instanceof RouteBuildEvent)
      ? AccessResult::allowed()
      : AccessResult::forbidden('This action can only run on the "Alter route" event.');
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!isset($this->event) || !($this->event instanceof RouteBuildEvent)) {
      return;
    }
    $pattern = trim((string) $this->tokenService->replaceClear($this->configuration['route_name']));
    if ($pattern === '') {
      return;
    }
    $collection = $this->event->getRouteCollection();
    foreach ($collection->all() as $name => $route) {
      if ($this->routeNameMatches($pattern, $name)) {
        $route->setOption('_htmx_route', TRUE);
      }
    }
  }

  /**
   * Determines whether a route name matches the configured pattern.
   *
   * Supports a simple "*" wildcard which matches any sequence of characters.
   * Without a wildcard, the match is exact.
   *
   * @param string $pattern
   *   The configured pattern, optionally containing "*" wildcards.
   * @param string $name
   *   The route name to test.
   *
   * @return bool
   *   TRUE if the route name matches the pattern.
   */
  protected function routeNameMatches(string $pattern, string $name): bool {
    if (!str_contains($pattern, '*')) {
      return $pattern === $name;
    }
    $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
    return (bool) preg_match($regex, $name);
  }

}
