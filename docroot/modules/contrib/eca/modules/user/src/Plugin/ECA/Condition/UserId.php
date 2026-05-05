<?php

namespace Drupal\eca_user\Plugin\ECA\Condition;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;

/**
 * Plugin implementation of the ECA condition of a user's id.
 */
#[EcaCondition(
  id: 'eca_user_id',
  label: new TranslatableMarkup('ID of user'),
  description: new TranslatableMarkup('Compares a user ID with a loaded ID of a given user account.'),
  version_introduced: '1.0.0',
)]
class UserId extends CurrentUserId {

  use UserTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if ($account = $this->loadUserAccount()) {
      // We need to cast the ID to string to avoid false positives when an
      // empty string value get compared to integer 0.
      $result = (string) $this->tokenService->replace($this->configuration['user_id']) === (string) $account->id();
      return $this->negationCheck($result);
    }
    return FALSE;
  }

}
