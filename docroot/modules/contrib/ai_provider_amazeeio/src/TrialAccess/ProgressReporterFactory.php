<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

/**
 * Default progress reporter factory.
 *
 * @internal
 */
final class ProgressReporterFactory implements ProgressReporterFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function forCurrentRuntime(): ProgressReporterInterface {
    if (PHP_SAPI === 'cli') {
      return new ConsoleProgressReporter();
    }

    return new NullProgressReporter();
  }

}
