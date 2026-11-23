<?php

namespace Drupal\Tests\eca_queue\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\eca\Token\DynamicDataProviderInterface;
use Drupal\eca_queue\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Task class, focusing on DynamicDataProviderInterface.
 *
 * @see \Drupal\eca_queue\Task
 */
#[Group('eca')]
#[Group('eca_queue')]
class TaskTest extends TestCase {

  /**
   * Creates a Task with the given data array.
   *
   * @param array $data
   *   The token data.
   *
   * @return \Drupal\eca_queue\Task
   *   The task instance.
   */
  private function createTask(array $data = []): Task {
    $time = $this->createStub(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(1000);
    return new Task($time, 'test_task', NULL, $data);
  }

  /**
   * Tests that Task implements DynamicDataProviderInterface.
   */
  public function testImplementsInterface(): void {
    $task = $this->createTask();
    $this->assertInstanceOf(DynamicDataProviderInterface::class, $task);
  }

  /**
   * Tests getAvailableKeys() returns empty array when no data is set.
   */
  public function testGetAvailableKeysEmpty(): void {
    $task = $this->createTask();
    $this->assertSame([], $task->getAvailableKeys());
  }

  /**
   * Tests getAvailableKeys() returns keys from the data array.
   */
  public function testGetAvailableKeysWithData(): void {
    $task = $this->createTask([
      'entity' => 'node_1',
      'message' => 'hello world',
      'image_uri' => '/path/to/image.png',
    ]);

    $keys = $task->getAvailableKeys();
    sort($keys);
    $this->assertSame(['entity', 'image_uri', 'message'], $keys);
  }

  /**
   * Tests getAvailableKeys() is consistent with hasData() and getData().
   */
  public function testGetAvailableKeysConsistentWithDataAccess(): void {
    $task = $this->createTask([
      'entity' => 'node_1',
      'message' => 'hello',
    ]);

    foreach ($task->getAvailableKeys() as $key) {
      $this->assertTrue($task->hasData($key), "hasData('$key') should be TRUE");
      $this->assertNotNull($task->getData($key), "getData('$key') should not be NULL");
    }

    $this->assertFalse($task->hasData('nonexistent'));
    $this->assertNull($task->getData('nonexistent'));
  }

}
