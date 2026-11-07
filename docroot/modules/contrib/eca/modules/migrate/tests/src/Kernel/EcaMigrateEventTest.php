<?php

namespace Drupal\Tests\eca_migrate\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\eca\Entity\Objects\EcaEvent as EcaEventObject;
use Drupal\eca\PluginManager\Event;
use Drupal\eca_migrate\Event\EcaMigrateProcessEvent;
use Drupal\eca_migrate\Plugin\ECA\Event\MigrateEvent;
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
    ];
    $row = new Row($row_source, [], TRUE);
    $row_destination = ['source_field' => 'expected_destination_value'];
    $row->setDestinationProperty(key($row_destination), reset($row_destination));
    $destination_property = 'foo_title';
    $ecaMigrateProcessEvent = new EcaMigrateProcessEvent($value, $row, $destination_property, 'test_migration');

    $migrateEvent->setEvent($ecaMigrateProcessEvent);

    $this->assertEquals('foo_value', $migrateEvent->getData('value'));
    $row = $migrateEvent->getData('row')->getValue();
    $source = $row['values']['source']['values'];
    $this->assertSame('expected_value', $source['source_field']);
    $this->assertEquals(1, $row['values']['is_stub']);
    $destination = $row['values']['destination']['values'];
    $this->assertSame($row_destination, $destination);
    $this->assertEquals('foo_title', $migrateEvent->getData('destination_property'));
    $this->assertEquals('test_migration', $migrateEvent->getData('migration_id'));
  }

  /**
   * Tests wildcard-based restriction by migration ID and destination property.
   */
  public function testAppliesForWildcard(): void {
    $row = new Row(['source_field' => 'value'], [], TRUE);
    $event = new EcaMigrateProcessEvent('value', $row, 'foo_title', 'test_migration');
    $eventName = EcaMigrateProcessEvent::class;

    // No restriction.
    $this->assertTrue(MigrateEvent::appliesForWildcard($event, $eventName, '*::*'));
    // Matching migration ID.
    $this->assertTrue(MigrateEvent::appliesForWildcard($event, $eventName, 'test_migration::*'));
    // Matching destination property.
    $this->assertTrue(MigrateEvent::appliesForWildcard($event, $eventName, '*::foo_title'));
    // Matching both.
    $this->assertTrue(MigrateEvent::appliesForWildcard($event, $eventName, 'test_migration::foo_title'));
    // Non-matching migration ID.
    $this->assertFalse(MigrateEvent::appliesForWildcard($event, $eventName, 'other_migration::*'));
    // Non-matching destination property.
    $this->assertFalse(MigrateEvent::appliesForWildcard($event, $eventName, '*::other_property'));
    // Matching migration ID but non-matching destination property.
    $this->assertFalse(MigrateEvent::appliesForWildcard($event, $eventName, 'test_migration::other_property'));
  }

  /**
   * Tests wildcard generation from event plugin configuration.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testGenerateWildcard(): void {
    /** @var \Drupal\eca_migrate\Plugin\ECA\Event\MigrateEvent $migrateEvent */
    $migrateEvent = $this->eventManager->createInstance('migrate:process');

    $ecaEvent = $this->createStub(EcaEventObject::class);
    $ecaEvent->method('getConfiguration')->willReturn([
      'migration_id' => 'test_migration',
      'destination_property' => 'foo_title',
    ]);
    $wildcard = $migrateEvent->generateWildcard('eca_id', $ecaEvent);
    $this->assertEquals('test_migration::foo_title', $wildcard);

    $ecaEventEmpty = $this->createStub(EcaEventObject::class);
    $ecaEventEmpty->method('getConfiguration')->willReturn([
      'migration_id' => '',
      'destination_property' => '',
    ]);
    $wildcard = $migrateEvent->generateWildcard('eca_id', $ecaEventEmpty);
    $this->assertEquals('*::*', $wildcard);
  }

  /**
   * Tests that the migration_id form field is a dynamically populated select.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testMigrationIdSelectOptions(): void {
    // Enable the test module that provides a discoverable migration.
    $this->enableModules(['eca_migrate_test']);

    /** @var \Drupal\eca_migrate\Plugin\ECA\Event\MigrateEvent $migrateEvent */
    $migrateEvent = $this->eventManager->createInstance('migrate:process');
    $form = $migrateEvent->buildConfigurationForm([], new FormState());

    $this->assertSame('select', $form['migration_id']['#type']);
    // The empty "any migration" option is always present.
    $this->assertArrayHasKey('', $form['migration_id']['#options']);
    // The test migration is listed with its label and ID.
    $this->assertArrayHasKey('eca_migrate_test_migration', $form['migration_id']['#options']);
    $this->assertSame(
      'ECA migrate test migration (eca_migrate_test_migration)',
      $form['migration_id']['#options']['eca_migrate_test_migration'],
    );

    // The destination property remains a free-text field.
    $this->assertSame('textfield', $form['destination_property']['#type']);
  }

  /**
   * Tests cleanupAfterSuccessors modifies event value.
   */
  public function testCleanupAfterSuccessors(): void {
    // Scalar.
    $expected = 'changed_value';
    $this->assertEquals($expected, $this->cleanupAfterSuccessors($expected));

    // Single value array.
    $expected = ['changed_value_foo'];
    $return_value = $this->cleanupAfterSuccessors($expected);
    $this->assertSame($expected, $return_value);

    $expected = ['foo' => 'changed_value_foo'];
    $return_value = $this->cleanupAfterSuccessors($expected);
    $this->assertSame($expected, $return_value);

    // Multiple values array.
    $expected = ['changed_value_foo', 'changed_value_bar'];
    $return_value = $this->cleanupAfterSuccessors($expected);
    $this->assertSame($expected, $return_value);

    $expected = ['foo' => 'changed_value_foo', 'bar' => 'changed_value_bar'];
    $return_value = $this->cleanupAfterSuccessors($expected);
    $this->assertSame($expected, $return_value);

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
