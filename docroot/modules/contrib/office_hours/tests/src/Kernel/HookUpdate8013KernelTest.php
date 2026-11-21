<?php

namespace Drupal\Tests\office_hours\Kernel;

use Drupal\KernelTests\KernelTestBase;

require_once __DIR__ . '/../../../office_hours.install';

/**
 * Tests the office_hours_update_8013 hook in a kernel environment.
 *
 * @coversDefaultClass \Drupal\office_hours\OfficeHoursDateHelper
 * @group office_hours
 */
class HookUpdate8013KernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'office_hours',
    'system',
    'user',
    'field',
    'entity_test',
  ];

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install the required schemas.
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');

    // Get the actual services from the container.
    $this->messenger = $this->container->get('messenger');
    $this->stringTranslation = $this->container->get('string_translation');
  }

  /**
   * Tests that the update hook function can be called without errors.
   */
  public function testUpdate8013FunctionCallable(): void {
    $sandbox = [];

    // This should not throw any exceptions.
    try {
      office_hours_update_8013($sandbox);
      $this->assertTrue(TRUE, 'The function executed without errors.');
    }
    catch (\Exception $e) {
      $this->fail('The function should not throw an exception: ' . $e->getMessage());
    }
  }

  /**
   * Tests that the update hook function handles empty sandbox correctly.
   */
  public function testUpdate8013EmptySandbox(): void {
    $sandbox = [];

    // Call the function with empty sandbox.
    office_hours_update_8013($sandbox);

    // Verify the sandbox remains empty.
    $this->assertEmpty($sandbox);
  }

}
