<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

/**
 * Factory for amazee.ai trial account provisioning.
 *
 * @internal
 */
interface TrialAccountProvisionerFactoryInterface {

  /**
   * Creates a new trial account provisioner.
   */
  public function create(ProgressReporterInterface $progressReporter): TrialAccountProvisionerInterface;

}
