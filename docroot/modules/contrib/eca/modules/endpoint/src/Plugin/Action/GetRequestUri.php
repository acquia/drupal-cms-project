<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Get the requested uri.
 */
#[Action(
  id: 'eca_endpoint_get_request_uri',
  label: new TranslatableMarkup('Request: Get uri'),
)]
#[EcaAction(
  version_introduced: '1.1.0',
)]
class GetRequestUri extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): string {
    return $this->getRequest()->getRequestUri();
  }

}
