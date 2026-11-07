<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

/**
 * Provisions an anonymous amazee.ai trial account and stores the credentials.
 *
 * @internal
 */
interface TrialAccountProvisionerInterface {

  /**
   * Provisions (or reuses) an amazee.ai trial account.
   *
   * @throws \Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisioningException
   */
  public function provision(): TrialAccountProvisioningResult;

}
