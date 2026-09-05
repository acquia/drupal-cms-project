<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\eca\PluginManager\Condition;
use Drupal\eca\PluginManager\Event;
use Drupal\eca\Service\Conditions;
use Drupal\eca\Service\Events;
use Drupal\eca\Token\TokenInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the condition and event plugin collection services.
 */
#[Group('eca')]
class PluginCollectionServicesTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    drupal_static_reset('eca_conditions');
    drupal_static_reset('eca_events');
  }

  /**
   * Tests that the condition collection cache can be reset.
   */
  public function testConditionCollectionCacheCanBeReset(): void {
    $manager = $this->createMock(Condition::class);
    $manager->expects($this->exactly(2))
      ->method('getDefinitions')
      ->willReturn([]);
    $service = new Conditions(
      $manager,
      $this->createStub(LoggerChannelInterface::class),
      $this->createStub(EntityTypeManagerInterface::class),
      $this->createStub(LanguageManagerInterface::class),
      $this->createStub(TokenInterface::class),
      $this->createStub(ModuleExtensionList::class),
    );

    $service->conditions();
    $service->conditions();
    drupal_static_reset('eca_conditions');
    $service->conditions();
  }

  /**
   * Tests that the event collection cache can be reset.
   */
  public function testEventCollectionCacheCanBeReset(): void {
    $manager = $this->createMock(Event::class);
    $manager->expects($this->exactly(2))
      ->method('getDefinitions')
      ->willReturn([]);
    $service = new Events(
      $manager,
      $this->createStub(LoggerChannelInterface::class),
      $this->createStub(ModuleExtensionList::class),
    );

    $service->events();
    $service->events();
    drupal_static_reset('eca_events');
    $service->events();
  }

  /**
   * Tests that event instantiation failures are logged and skipped.
   */
  public function testEventInstantiationFailureIsLogged(): void {
    $manager = $this->createMock(Event::class);
    $manager->method('getDefinitions')->willReturn([
      'broken_event' => [],
    ]);
    $manager->method('createInstance')
      ->with('broken_event', [])
      ->willThrowException(new \TypeError('Broken event'));
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        'The event plugin %pluginid can not be initialized. ECA is ignoring this event. The issue with this event: %msg',
        [
          '%pluginid' => 'broken_event',
          '%msg' => 'Broken event',
        ],
      );
    $service = new Events(
      $manager,
      $logger,
      $this->createStub(ModuleExtensionList::class),
    );

    $this->assertSame([], $service->events());
  }

}
