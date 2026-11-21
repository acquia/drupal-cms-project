<?php

namespace Drupal\eca_base\Hook;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eca\EcaState;
use Drupal\eca\Event\TriggerEvent;

/**
 * Implements base hooks for the ECA Base submodule.
 */
class BaseHooks {

  /**
   * Constructs a new BaseHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
    protected DateFormatterInterface $dateFormatter,
    protected LoggerChannelInterface $logger,
    protected EcaState $state,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->triggerEvent->dispatchFromPlugin('eca_base:eca_cron', $this->state, $this->dateFormatter, $this->logger);
  }

}
