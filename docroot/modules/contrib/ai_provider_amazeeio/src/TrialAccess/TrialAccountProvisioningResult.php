<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

/**
 * Outcome of amazee.ai trial account provisioning.
 *
 * @internal
 */
enum TrialAccountProvisioningResult {
  case Provisioned;
  case AlreadyProvisioned;
}
