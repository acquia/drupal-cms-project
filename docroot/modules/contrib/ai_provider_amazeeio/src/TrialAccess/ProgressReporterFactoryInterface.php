<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

/**
 * Creates progress reporters appropriate for the current runtime.
 *
 * @internal
 */
interface ProgressReporterFactoryInterface {

  /**
   * Returns a progress reporter suitable for the current runtime.
   */
  public function forCurrentRuntime(): ProgressReporterInterface;

}
