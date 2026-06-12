<?php

namespace Drupal\eca_user\Plugin\ECA\Condition;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;

/**
 * Plugin implementation of the ECA condition of any user's permissions.
 */
#[EcaCondition(
  id: 'eca_user_permission',
  label: new TranslatableMarkup('User has permission'),
  description: new TranslatableMarkup('Checks, whether a given user account has a given permission.'),
  version_introduced: '1.0.0',
)]
class UserPermission extends CurrentUserPermission {

  use UserTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if ($account = $this->loadUserAccount()) {
      return $this->negationCheck($account->hasPermission($this->configuration['permission']));
    }
    return FALSE;
  }

}
