<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

/**
 * Null trial account provisioner implementation for test environments.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class NullTrialAccessProvisioner implements TrialAccountProvisionerInterface {

  /**
   * {@inheritdoc}
   */
  public function provision(): TrialAccountProvisioningResult {
    return TrialAccountProvisioningResult::Provisioned;
  }

}
