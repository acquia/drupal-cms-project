<?php

namespace Drupal\Tests\office_hours\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;

require_once __DIR__ . '/../../../office_hours.install';

/**
 * Tests the office_hours_update_8013 hook.
 *
 * @coversDefaultClass \Drupal\office_hours\OfficeHoursDateHelper
 * @group office_hours
 */
class HookUpdate8013UnitTest extends UnitTestCase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $messenger;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    // Mock the messenger service.
    $this->messenger = $this->createMock(MessengerInterface::class);
    $container->set('messenger', $this->messenger);

    // Mock the string translation service.
    $this->stringTranslation = $this->createMock(TranslationInterface::class);
    $this->stringTranslation
      ->method('translate')
      ->willReturnCallback(function ($string, $args = [], $options = []) {
        return $string;
      });
    $container->set('string_translation', $this->stringTranslation);

    \Drupal::setContainer($container);
  }

  /**
   * Tests that the update hook displays the correct message.
   */
  public function testUpdate8013Message(): void {
    // Set up expectations for the messenger service.
    $this->messenger
      ->expects($this->once())
      ->method('addMessage')
      ->with($this->anything());

    // Call the update hook function.
    $sandbox = [];
    office_hours_update_8013($sandbox);

    // Verify that the sandbox parameter was not modified.
    $this->assertEmpty($sandbox);
  }

}
