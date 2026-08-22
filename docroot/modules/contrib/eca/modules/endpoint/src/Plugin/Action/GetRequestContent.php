<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Get the request content.
 */
#[Action(
  id: 'eca_endpoint_get_request_content',
  label: new TranslatableMarkup('Request: Get content'),
)]
#[EcaAction(
  version_introduced: '1.1.0',
)]
class GetRequestContent extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): string {
    return (string) $this->getRequest()->getContent();
  }

}
