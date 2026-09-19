<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Kernel\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetImage;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the 'image' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetImage
 * @runTestsInSeparateProcesses
 */
#[CoversClass(PropWidgetImage::class)]
#[Group('custom_field')]
#[RunTestsInSeparateProcesses]
class PropWidgetImageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field',
    'file',
    'system',
    'user',
  ];

  /**
   * The plugin under test.
   *
   * @var \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetImage
   */
  protected PropWidgetImage $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installSchema('file', ['file_usage']);
    $this->plugin = $this->container->get('plugin.manager.custom_field_component_prop_widget')
      ->createInstance('image');
  }

  /**
   * Tests that getPropValue() returns NULL for non-array input.
   */
  public function testGetPropValueReturnsNullForNonArray(): void {
    $this->assertNull($this->plugin->getPropValue(NULL));
    $this->assertNull($this->plugin->getPropValue('foo'));
    $this->assertNull($this->plugin->getPropValue(42));
  }

  /**
   * Tests that getPropValue() returns the array as-is for valid input.
   */
  public function testGetPropValueReturnsArrayAsIs(): void {
    $input = [
      'src' => 'https://example.com/image.jpg',
      'alt' => 'An image',
      'width' => 800,
      'height' => 600,
    ];
    $this->assertSame($input, $this->plugin->getPropValue($input));
  }

  /**
   * Tests that massageValue() returns empty array value when no fid is present.
   */
  public function testMassageValueReturnsEmptyValueWhenNoFid(): void {
    $result = $this->plugin->massageValue(['value' => []]);
    $this->assertSame('image', $result['widget']);
    $this->assertSame([], $result['value']);
  }

  /**
   * Tests that massageValue() sets file permanent and returns image data.
   */
  public function testMassageValueSetsFilePermanentAndReturnsImageData(): void {
    $file = File::create([
      'uri' => 'public://test.jpg',
      'status' => 0,
    ]);
    $file->save();

    $result = $this->plugin->massageValue([
      'value' => [
        'fid' => $file->id(),
        'alt' => 'Test image',
        'width' => 800,
        'height' => 600,
      ],
    ]);

    $this->assertSame('image', $result['widget']);
    $this->assertIsArray($result['value']);
    $this->assertArrayHasKey('src', $result['value']);
    $this->assertArrayHasKey('alt', $result['value']);
    $this->assertSame('Test image', $result['value']['alt']);
    $this->assertSame(800, $result['value']['width']);
    $this->assertSame(600, $result['value']['height']);

    // Verify file was made permanent.
    $file = File::load($file->id());
    $this->assertTrue($file->isPermanent());
  }

  /**
   * Tests that massageValue() returns empty array when file doesn't exist.
   */
  public function testMassageValueReturnsEmptyValueWhenFileNotFound(): void {
    $result = $this->plugin->massageValue([
      'value' => ['fid' => 99999],
    ]);
    $this->assertSame('image', $result['widget']);
    $this->assertSame([], $result['value']);
  }

}
