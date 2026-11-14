<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Get the content type of the request.
 */
#[Action(
  id: 'eca_endpoint_get_request_content_type',
  label: new TranslatableMarkup('Request: Get content type'),
)]
#[EcaAction(
  version_introduced: '1.1.0',
)]
class GetRequestContentType extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): ?string {
    if ($request = $this->getRequest()) {
      return $request->getContentTypeFormat();
    }
    return '';
  }

}
