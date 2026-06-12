<?php

declare(strict_types=1);

namespace Drupal\trash_test;

use Drupal\Component\Datetime\Time;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A test-only implementation of the time service.
 */
class TestTime extends Time {

  public function __construct(
    RequestStack $requestStack,
    #[Autowire(service: 'keyvalue')]
    protected KeyValueFactoryInterface $keyValueFactory,
  ) {
    parent::__construct($requestStack);
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime(): int {
    $offset = $this->keyValueFactory->get('trash_test')->get('time_offset', 0);
    return parent::getRequestTime() + $offset;
  }

}
