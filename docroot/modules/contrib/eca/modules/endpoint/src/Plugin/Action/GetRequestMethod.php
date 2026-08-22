<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Get the request method.
 */
#[Action(
  id: 'eca_endpoint_get_request_method',
  label: new TranslatableMarkup('Request: Get method'),
)]
#[EcaAction(
  version_introduced: '1.1.0',
)]
class GetRequestMethod extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): string {
    return $this->getRequest()->getMethod();
  }

}
