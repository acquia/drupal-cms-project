<?php

declare(strict_types=1);

namespace Drupal\google_tag\Plugin\GoogleTag\Event;

use Drupal\webform\WebformSubmissionInterface;

/**
 * Webform purchase event.
 *
 * @GoogleTagEvent(
 *   id = "webform_purchase",
 *   label = @Translation("Purchase (Webform)"),
 *   dependency = "webform",
 *   context_definitions = {
 *      "submission" = @ContextDefinition("entity:webform_submission")
 *   }
 * )
 */
final class WebformPurchase extends EventBase {

  /**
   * {@inheritDoc}
   */
  public function getData(): array {
    $submission = $this->getContextValue('submission');
    if (!($submission instanceof WebformSubmissionInterface)) {
      return [];
    }

    return [
      'webform_id' => $submission->getWebform()->id(),
      'submission_id' => $submission->id(),
    ];
  }

}
