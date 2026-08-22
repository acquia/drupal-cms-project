<?php

declare(strict_types=1);

namespace Drupal\eca_htmx\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Forces the current request to render the minimal HTMX response.
 *
 * This action sets the "_wrapper_format" query parameter to "drupal_htmx" on
 * the active request. Drupal core's MainContentViewSubscriber inspects
 * $request->query->has('_wrapper_format'); once present with the
 * "drupal_htmx" value, core returns the minimal main-content render instead of
 * a full HTML page (and adds X-Robots-Tag: noindex).
 *
 * This makes a dynamic, config-less minimal response possible from within an
 * ECA model, complementing the static "eca_htmx_set_route" action.
 */
#[Action(
  id: 'eca_htmx_minimal_response',
  label: new TranslatableMarkup('HTMX: minimal response'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Forces the current request to return the minimal HTMX response instead of a full HTML page, by setting the "_wrapper_format" query parameter to "drupal_htmx".'),
  version_introduced: '3.1.3',
)]
class MinimalResponse extends ConfigurableActionBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->requestStack->getCurrentRequest()
      ? AccessResult::allowed()
      : AccessResult::forbidden('No request available.');
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return;
    }
    $request->query->set('_wrapper_format', 'drupal_htmx');
  }

}
