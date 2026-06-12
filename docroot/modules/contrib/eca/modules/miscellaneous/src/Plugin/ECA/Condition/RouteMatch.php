<?php

namespace Drupal\eca_misc\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca_misc\Plugin\RouteInterface;
use Drupal\eca_misc\Plugin\RouteTrait;

/**
 * Condition plugin for matching the name of the route.
 */
#[EcaCondition(
  id: 'eca_route_match',
  label: new TranslatableMarkup('Route match'),
  description: new TranslatableMarkup('Gets and compares the name of the route.'),
  version_introduced: '1.0.0',
)]
class RouteMatch extends StringComparisonBase {

  use RouteTrait;

  /**
   * {@inheritdoc}
   */
  protected function getLeftValue(): string {
    return $this->getRouteMatch()->getRouteName() ?? '';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->configuration['route'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'request' => RouteInterface::ROUTE_CURRENT,
      'route' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $this->requestFormField($form);
    $form['route'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Route name'),
      '#description' => $this->t('The routes and their parameters can be found in the <em>MODULE.routing.yml</em> file, e.g. the route name <em>entity.node.preview</em>.'),
      '#default_value' => $this->configuration['route'],
      '#weight' => -70,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['request'] = $form_state->getValue('request');
    $this->configuration['route'] = $form_state->getValue('route');
    parent::submitConfigurationForm($form, $form_state);
  }

}
