<?php

declare(strict_types=1);

namespace Drupal\Tests\eca_migrate\Unit\process;

use Drupal\eca\Event\TriggerEvent;
use Drupal\eca_migrate\Event\EcaMigrateProcessEvent;
use Drupal\eca_migrate\Plugin\migrate\process\Eca;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the eca migrate process plugin.
 */
#[Group('eca')]
#[Group('eca_migrate')]
#[CoversClass('\Drupal\eca_migrate\Plugin\migrate\process\Eca')]
class EcaProcessPluginTest extends MigrateProcessTestCase {

  /**
   * The TriggerEvent service or a mock.
   */
  protected TriggerEvent|MockObject $triggerEvent;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $configuration['token_name'] = ['processed_value'];
    $this->triggerEvent = $this->createStub(TriggerEvent::class);
    $this->plugin = new Eca($configuration, 'eca', [], $this->triggerEvent);
    parent::setUp();
  }

  /**
   * Tests successful eca processing without event.
   */
  public function testEcaWithoutEvent(): void {
    $value = ['foo' => 'bar'];
    $expected = ['foo' => 'bar'];
    $destination_property = 'destination_property';

    $trigger_event = $this->createMock(TriggerEvent::class);
    $trigger_event->expects($this->once())
      ->method('dispatchFromPlugin')
      ->willReturn(NULL);
    $plugin = new Eca(['token_name' => ['processed_value']], 'eca', [], $trigger_event);

    $value = $plugin->transform($value, $this->migrateExecutable, $this->row, $destination_property);
    $this->assertSame($expected, $value);
  }

  /**
   * Tests successful eca processing with event.
   *
   * Without an injected migration, an empty migration ID is dispatched.
   */
  public function testEcaWithEvent(): void {
    $value = ['foo' => 'bar'];
    $expected = ['foo' => 'bars'];
    $destination_property = 'destination_property';

    $event = $this->createMock(EcaMigrateProcessEvent::class);
    $event->expects($this->once())
      ->method('getValue')
      ->willReturn($expected);

    $trigger_event = $this->createMock(TriggerEvent::class);
    $trigger_event->expects($this->once())
      ->method('dispatchFromPlugin')
      ->with(
        'migrate:process',
        $value,
        $this->row,
        $destination_property,
        ''
      )
      ->willReturn($event);
    $plugin = new Eca(['token_name' => ['processed_value']], 'eca', [], $trigger_event);

    $value = $plugin->transform($value, $this->migrateExecutable, $this->row, $destination_property);
    $this->assertSame($expected, $value);
  }

  /**
   * Tests that the injected migration's ID is dispatched to the event.
   */
  public function testEcaDispatchesMigrationId(): void {
    $value = ['foo' => 'bar'];
    $expected = ['foo' => 'bars'];
    $destination_property = 'destination_property';

    $trigger_event = $this->createMock(TriggerEvent::class);
    $migration = $this->createMock(MigrationInterface::class);
    $migration->expects($this->once())
      ->method('id')
      ->willReturn('my_migration');
    $plugin = new Eca(['token_name' => ['processed_value']], 'eca', [], $trigger_event, $migration);

    $event = $this->createMock(EcaMigrateProcessEvent::class);
    $event->expects($this->once())
      ->method('getValue')
      ->willReturn($expected);

    $trigger_event->expects($this->once())
      ->method('dispatchFromPlugin')
      ->with(
        'migrate:process',
        $value,
        $this->row,
        $destination_property,
        'my_migration'
      )
      ->willReturn($event);

    $value = $plugin->transform($value, $this->migrateExecutable, $this->row, $destination_property);
    $this->assertSame($expected, $value);
  }

  /**
   * Tests that multiple() defaults to FALSE without the "multiple" config key.
   */
  public function testMultipleDefaultsToFalse(): void {
    $plugin = new Eca(['token_name' => ['processed_value']], 'eca', [], $this->triggerEvent);
    $this->assertFalse($plugin->multiple());
  }

  /**
   * Tests that multiple() reflects the "multiple" config key.
   *
   * When set to TRUE, the pipeline applies subsequent process plugins that do
   * not handle multiples themselves (e.g. "callback: trim") to each element of
   * the returned list individually.
   */
  public function testMultipleFromConfiguration(): void {
    $plugin = new Eca([
      'token_name' => ['processed_value'],
      'multiple' => TRUE,
    ], 'eca', [], $this->triggerEvent);
    $this->assertTrue($plugin->multiple());

    // A falsy value keeps multiples handling disabled.
    $plugin = new Eca([
      'token_name' => ['processed_value'],
      'multiple' => FALSE,
    ], 'eca', [], $this->triggerEvent);
    $this->assertFalse($plugin->multiple());
  }

}
