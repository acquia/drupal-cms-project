<?php

namespace Drupal\Tests\eca_migrate\Kernel;

use Drupal\eca\PluginManager\Event;
use Drupal\eca_migrate\Event\EcaMigrateProcessEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Row;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the "eca_migrate" event plugin.
 */
#[Group('eca')]
#[Group('eca_migrate')]
#[RunTestsInSeparateProcesses]
class EcaMigrateEventTest extends KernelTestBase {

  /**
   * The event manager.
   */
  protected Event $eventManager;

  /**
   * A process row stub.
   */
  protected Row $row;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'migrate',
    'user',
    'eca',
    'eca_migrate',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->eventManager = \Drupal::service('plugin.manager.eca.event');
    $this->row = $this->createStub(Row::class);
  }

  /**
   * Tests proper event instantiation.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testProperInstantiation(): void {
    /** @var \Drupal\eca_migrate\Plugin\ECA\Event\MigrateEvent $event */
    $event = $this->eventManager->createInstance('migrate:process', []);
    $this->assertEquals('migrate', $event->getBaseId());
  }

  /**
   * Tests plugin discovery and getData behavior.
   */
  public function testEventDataTokens(): void {
    /** @var \Drupal\eca_migrate\Plugin\ECA\Event\MigrateEvent $ecaMigrateEvent */
    $migrateEvent = $this->eventManager->createInstance('migrate:process');

    $value = 'foo_value';
    $row_source = [
      'source_field' => 'expected_value',
      'constants' => [
        'mid' => 'test_migration',
      ],
    ];
    $row = new Row($row_source, [], TRUE);
    $row_destination = ['source_field' => 'expected_destination_value'];
    $row->setDestinationProperty(key($row_destination), reset($row_destination));
    $destination_property = 'foo_title';
    $ecaMigrateProcessEvent = new EcaMigrateProcessEvent($value, $row, $destination_property);

    $migrateEvent->setEvent($ecaMigrateProcessEvent);

    $this->assertEquals('foo_value', $migrateEvent->getData('value'));
    $row = $migrateEvent->getData('row')->getValue();
    $source = $row['values']['source']['values'];
    $this->assertSame('expected_value', $source['source_field']);
    $this->assertSame('test_migration', $source['constants']['values']['mid']);
    $this->assertEquals(1, $row['values']['is_stub']);
    $destination = $row['values']['destination']['values'];
    $this->assertSame($row_destination, $destination);
    $this->assertEquals('foo_title', $migrateEvent->getData('destination_property'));
  }

  /**
   * Tests cleanupAfterSuccessors modifies event value.
   */
  public function testCleanupAfterSuccessors(): void {
    // Scalar.
    $expected = 'changed_value';
    $this->assertEquals($expected, $this->cleanupAfterSuccessors($expected));

    // Array.
    $expected = ['foo' => 'changed_value_foo', 'bar' => 'changed_value_bar'];
    $return_value = $this->cleanupAfterSuccessors($expected);
    $this->assertSame($expected, $return_value['values']);

    // Entity.
    $expected = User::create([
      'name' => 'Created User',
      'mail' => 'user@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $this->assertSame($expected, $this->cleanupAfterSuccessors($expected));
  }

  /**
   * Returns token value from EcaMigrateEvent.
   *
   * @param mixed $expected
   *   The token value.
   *
   * @return mixed
   *   The value returned by EcaMigrateEvent::cleanupAfterSuccessors().
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function cleanupAfterSuccessors(mixed $expected): mixed {
    $value = 'foo_value';
    $destination_property = 'foo_title';
    $configuration = ['token_name' => 'processed_value'];
    /** @var \Drupal\eca_migrate\Plugin\ECA\Event\MigrateEvent $ecaMigrateEvent */
    $ecaMigrateEvent = $this->eventManager->createInstance('migrate:process', $configuration);

    $ecaMigrateProcessEvent = new EcaMigrateProcessEvent($value, $this->row, $destination_property);
    $ecaMigrateEvent->setEvent($ecaMigrateProcessEvent);

    /** @var \Drupal\eca\Token\TokenInterface $tokenServices */
    $tokenServices = \Drupal::service('eca.token_services');
    $tokenServices->addTokenData('processed_value', $expected);
    $ecaMigrateEvent->cleanupAfterSuccessors();
    return $ecaMigrateProcessEvent->getValue();
  }

}
