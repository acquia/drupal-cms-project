<?php

namespace Drupal\eca_migrate\Event;

/**
 * Events dispatched by the eca_migrate module.
 */
final class EcaMigrateEvents {

  /**
   * Dispatches on migrate processing.
   *
   * @Event
   */
  public const string PROCESS = 'eca_migrate.process';

}
