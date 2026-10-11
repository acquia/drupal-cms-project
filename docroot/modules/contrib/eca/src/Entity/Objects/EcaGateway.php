<?php

namespace Drupal\eca\Entity\Objects;

use Drupal\eca\Entity\Eca;
use Drupal\eca\ProcessDebugger;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides an ECA item of type gateway for internal processing.
 */
class EcaGateway extends EcaObject {

  /**
   * Gateway type.
   *
   * @var int
   */
  protected int $type;

  /**
   * Gateway constructor.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param string $id
   *   The gateway ID provided by the modeller.
   * @param string $label
   *   The gateway label.
   * @param \Drupal\eca\Entity\Objects\EcaEvent $event
   *   The ECA event object which started the process towards this gateway.
   * @param int $type
   *   The gateway type.
   */
  public function __construct(Eca $eca, string $id, string $label, EcaEvent $event, int $type) {
    parent::__construct($eca, $id, $label, $event);
    $this->type = $type;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ProcessDebugger $debugger, ?EcaObject $predecessor, Event $event, array $context): bool {
    if (!parent::execute($debugger, $predecessor, $event, $context)) {
      return FALSE;
    }
    $debugger->execute($this->getId(), NULL);
    return TRUE;
  }

}
