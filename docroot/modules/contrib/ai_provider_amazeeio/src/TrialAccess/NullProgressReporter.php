<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

/**
 * Null progress reporter.
 *
 * @internal
 */
final class NullProgressReporter implements ProgressReporterInterface {

  /**
   * {@inheritdoc}
   */
  public function start(string $message): void {}

  /**
   * {@inheritdoc}
   */
  public function advance(int $steps = 1): void {}

  /**
   * {@inheritdoc}
   */
  public function finish(string $message = ''): void {}

  /**
   * {@inheritdoc}
   */
  public function info(string $message): void {}

  /**
   * {@inheritdoc}
   */
  public function error(string $message): void {}

}
