<?php

declare(strict_types=1);

namespace Drupal\Tests\eca_migrate\Unit\process;

use Drupal\eca\Event\TriggerEvent;
use Drupal\eca_migrate\Event\EcaMigrateProcessEvent;
use Drupal\eca_migrate\Plugin\migrate\process\Eca;
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
    $this->triggerEvent = $this->createMock(TriggerEvent::class);
    $this->plugin = new Eca($configuration, 'eca', [], $this->triggerEvent);
    parent::setUp();
  }

  /**
   * Tests successful eca processing without event.
   */
  public function testEcaWithoutEvent(): void {
    $this->triggerEvent->expects($this->once())
      ->method('dispatchFromPlugin')
      ->willReturn(NULL);

    $value = $this->plugin->transform(['foo' => 'bar'], $this->migrateExecutable, $this->row, 'destination_property');
    $this->assertSame(['foo' => 'bar'], $value);
  }

  /**
   * Tests successful eca processing with event.
   */
  public function testEcaWithEvent(): void {
    $value = ['foo' => 'bar'];
    $expected = ['foo' => 'bars'];
    $destination_property = 'destination_property';

    $event = $this->createMock(EcaMigrateProcessEvent::class);
    $event->expects($this->once())
      ->method('getValue')
      ->willReturn($expected);

    $this->triggerEvent->expects($this->once())
      ->method('dispatchFromPlugin')
      ->with(
        'migrate:process',
        $value,
        $this->row,
        $destination_property
      )
      ->willReturn($event);

    $value = $this->plugin->transform($value, $this->migrateExecutable, $this->row, $destination_property);
    $this->assertSame($expected, $value);
  }

}
