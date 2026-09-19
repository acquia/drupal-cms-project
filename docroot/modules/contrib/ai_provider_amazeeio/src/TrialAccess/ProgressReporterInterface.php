<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

/**
 * Shows progress information on a user interface.
 */
interface ProgressReporterInterface {

  /**
   * Start progress reporting.
   */
  public function start(string $message): void;

  /**
   * Advance progress by a number of steps.
   */
  public function advance(int $steps = 1): void;

  /**
   * Finish progress reporting.
   */
  public function finish(string $message = ''): void;

  /**
   * Write an informational line.
   */
  public function info(string $message): void;

  /**
   * Write an error line.
   */
  public function error(string $message): void;

}
