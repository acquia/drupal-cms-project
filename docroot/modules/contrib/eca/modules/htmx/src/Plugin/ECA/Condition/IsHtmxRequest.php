<?php

declare(strict_types=1);

namespace Drupal\eca_htmx\Plugin\ECA\Condition;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca_htmx\HtmxRequestInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ECA condition that evaluates whether the current request is an HTMX request.
 */
#[EcaCondition(
  id: 'eca_htmx_is_request',
  label: new TranslatableMarkup('HTMX: is request'),
  description: new TranslatableMarkup('Evaluates to TRUE when the current request was sent by HTMX (the HX-Request header is present).'),
  version_introduced: '3.1.3',
)]
class IsHtmxRequest extends ConditionBase {

  /**
   * The HTMX request info service.
   *
   * @var \Drupal\eca_htmx\HtmxRequestInfo
   */
  protected HtmxRequestInfo $htmxRequestInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->htmxRequestInfo = $container->get('eca_htmx.request_info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    return $this->negationCheck($this->htmxRequestInfo->isRequest());
  }

}
