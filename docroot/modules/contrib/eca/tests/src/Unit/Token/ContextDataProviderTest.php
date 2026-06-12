<?php

namespace Drupal\Tests\eca\Unit\Token;

use Drupal\eca\Token\ContextDataProvider;
use Drupal\eca\Token\DynamicDataProviderInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ContextDataProvider, focusing on DynamicDataProviderInterface.
 *
 * @see \Drupal\eca\Token\ContextDataProvider
 */
#[Group('eca')]
#[Group('eca_core')]
class ContextDataProviderTest extends TestCase {

  /**
   * The context data provider under test.
   */
  private ContextDataProvider $provider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->provider = new ContextDataProvider();
    // Reset the static stack to ensure test isolation.
    $this->resetStack();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->resetStack();
    parent::tearDown();
  }

  /**
   * Resets the static stack via reflection.
   */
  private function resetStack(): void {
    $prop = new \ReflectionProperty(ContextDataProvider::class, 'stack');
    $prop->setValue(NULL, []);
  }

  /**
   * Tests that ContextDataProvider implements DynamicDataProviderInterface.
   */
  public function testImplementsInterface(): void {
    $this->assertInstanceOf(DynamicDataProviderInterface::class, $this->provider);
  }

  /**
   * Tests getAvailableKeys() returns empty array when stack is empty.
   */
  public function testGetAvailableKeysEmpty(): void {
    $this->assertSame([], $this->provider->getAvailableKeys());
  }

  /**
   * Tests getAvailableKeys() returns keys from a single stack level.
   */
  public function testGetAvailableKeysSingleLevel(): void {
    $data = ['entity' => 'node_1', 'message' => 'hello'];
    $this->provider->push($data);

    $keys = $this->provider->getAvailableKeys();
    sort($keys);
    $this->assertSame(['entity', 'message'], $keys);
  }

  /**
   * Tests getAvailableKeys() merges keys across multiple stack levels.
   */
  public function testGetAvailableKeysMultipleLevels(): void {
    $data1 = ['entity' => 'node_1', 'message' => 'hello'];
    $data2 = ['image_uri' => '/path/to/image.png', 'entity' => 'node_2'];
    $this->provider->push($data1);
    $this->provider->push($data2);

    $keys = $this->provider->getAvailableKeys();
    sort($keys);
    $this->assertSame(['entity', 'image_uri', 'message'], $keys);
  }

  /**
   * Tests getAvailableKeys() reflects pop operations.
   */
  public function testGetAvailableKeysAfterPop(): void {
    $data1 = ['entity' => 'node_1'];
    $data2 = ['image_uri' => '/path/to/image.png'];
    $this->provider->push($data1);
    $this->provider->push($data2);

    $this->provider->pop();

    $keys = $this->provider->getAvailableKeys();
    $this->assertSame(['entity'], $keys);
  }

  /**
   * Tests getAvailableKeys() returns no duplicates for overlapping keys.
   */
  public function testGetAvailableKeysNoDuplicates(): void {
    $data1 = ['entity' => 'node_1', 'message' => 'a'];
    $data2 = ['entity' => 'node_2', 'message' => 'b'];
    $this->provider->push($data1);
    $this->provider->push($data2);

    $keys = $this->provider->getAvailableKeys();
    sort($keys);
    $this->assertSame(['entity', 'message'], $keys);
    $this->assertCount(2, $keys);
  }

}
