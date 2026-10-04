<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Get the client IP.
 */
#[Action(
  id: 'eca_endpoint_get_client_ip',
  label: new TranslatableMarkup('Request: Get client IP'),
)]
#[EcaAction(
  version_introduced: '1.1.0',
)]
class GetClientIp extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): ?string {
    return $this->getRequest()->getClientIp();
  }

}
