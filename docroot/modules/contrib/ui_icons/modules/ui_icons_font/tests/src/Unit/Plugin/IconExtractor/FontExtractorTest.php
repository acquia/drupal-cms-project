<?php

declare(strict_types=1);

// cspell:ignore corge
namespace Drupal\Tests\ui_icons_font\Unit;

use Drupal\Core\Theme\Icon\Exception\IconPackConfigErrorException;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Tests\Core\Theme\Icon\IconTestTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons_font\Plugin\IconExtractor\FontExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the FontExtractor class.
 *
 * @internal
 */
#[CoversClass(FontExtractor::class)]
#[Group('ui_icons')]
class FontExtractorTest extends UnitTestCase {

  use IconTestTrait;

  private const TEST_MODULE_PATH = __DIR__ . '/../../../../../tests/modules/ui_icons_font_test';

  /**
   * This test plugin id (icon pack id).
   */
  private string $pluginId = 'test_font';

  /**
   * Test the FontExtractor::discoverIcons() method.
   */
  public function testDiscoverIconsFont(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'icons/foo.codepoints',
            'icons/foo.json',
            'icons/foo.yml',
            'icons/foo.yaml',
            'icons/foo',
            'icons/foo.none',
          ],
        ],
        'template' => '_foo_',
        'absolute_path' => self::TEST_MODULE_PATH,

      ],
      $this->pluginId,
      [],
    );

    $result = $fontExtractorPlugin->discoverIcons();

    $prefix = $this->pluginId . IconDefinition::ICON_SEPARATOR;
    $expected = [
      $prefix . 'codepoints_foo' => [
        'content' => 'bar',
      ],
      $prefix . 'codepoints_baz' => [
        'content' => 'corge',
      ],
      $prefix . 'json_foo' => [],
      $prefix . 'json_baz' => [],
      $prefix . 'yml_foo' => [],
      $prefix . 'yml_baz' => [],
      $prefix . 'yaml_foo' => [],
      $prefix . 'yaml_baz' => [],
    ];

    foreach ($result as $icon_full_id => $icon_data) {
      $this->assertSame($expected[$icon_full_id], $icon_data);
    }
  }

  /**
   * Test the FontExtractor::discoverIcons() method with empty files.
   */
  public function testDiscoverIconsFontEmpty(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'icons/empty.codepoints',
            'icons/empty.json',
            'icons/empty.ttf',
            'icons/empty.yml',
            'icons/empty.yaml',
          ],
        ],
        'template' => '_foo_',
        'absolute_path' => self::TEST_MODULE_PATH,

      ],
      $this->pluginId,
      [],
    );

    $result = $fontExtractorPlugin->discoverIcons();
    $this->assertEmpty($result);
  }

  /**
   * Test the FontExtractor::discoverIcons() method with empty files.
   */
  public function testDiscoverIconsFontInvalid(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'icons/invalid.json',
            'icons/invalid.yml',
            'icons/invalid.yaml',
          ],
        ],
        'template' => '_foo_',
        'absolute_path' => self::TEST_MODULE_PATH,

      ],
      $this->pluginId,
      [],
    );

    $result = $fontExtractorPlugin->discoverIcons();
    $this->assertEmpty($result);
  }

  /**
   * Test the FontExtractor::discoverIcons() method with non existent files.
   */
  public function testDiscoverIconsFontNoFile(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'icons/do_not_exist.codepoints',
            'icons/do_not_exist.json',
            'icons/do_not_exist.yml',
            'icons/do_not_exist.yaml',
          ],
        ],
        'template' => '_foo_',
        'absolute_path' => self::TEST_MODULE_PATH,

      ],
      $this->pluginId,
      [],
    );

    // PHPUnit 10 cannot expect warnings, so we have to catch them ourselves.
    // Thanks to: Drupal\Tests\Component\PhpStorage\FileStorageTest.
    $messages = [];
    set_error_handler(function (int $errno, string $errstr) use (&$messages): void {
      $messages[] = [$errno, $errstr];
    });

    $fontExtractorPlugin->discoverIcons();

    restore_error_handler();

    $this->assertCount(4, $messages);
    $this->assertSame(E_WARNING, $messages[0][0]);
    $this->assertStringContainsString('Failed to open stream: No such file or directory', $messages[0][1]);
  }

  /**
   * Test the FontExtractor::discoverIcons() method with ttf file.
   */
  public function testDiscoverIconsFontTtf(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'icons/foo.ttf',
          ],
          'offset' => 3,
        ],
        'template' => '_foo_',
        'absolute_path' => self::TEST_MODULE_PATH,

      ],
      $this->pluginId,
      [],
    );

    $result = $fontExtractorPlugin->discoverIcons();

    if (!class_exists('\FontLib\Font')) {
      $this->assertEmpty($result);
      return;
    }

    $prefix = $this->pluginId . IconDefinition::ICON_SEPARATOR;
    $expected = [
      $prefix . 'at' => [],
      $prefix . 'A' => [],
    ];

    foreach ($result as $icon_full_id => $icon_data) {
      $this->assertSame($expected[$icon_full_id], $icon_data);
    }
  }

  /**
   * Test the PathExtractor::discoverIcons() method with no sources.
   */
  public function testDiscoverIconsExceptionNoSources(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'config' => [],
      ],
      $this->pluginId,
      [],
    );

    $this->expectException(IconPackConfigErrorException::class);
    $this->expectExceptionMessage('Missing `config: sources` in your definition, extractor test_font require this value.');
    $fontExtractorPlugin->discoverIcons();
  }

  /**
   * Test the FontExtractor::loadIcon() method.
   */
  public function testLoadIcon(): void {
    $fontExtractorPlugin = new FontExtractor(
      [
        'id' => $this->pluginId,
        'config' => [
          'sources' => [
            'foo/bar.ttf',
          ],
        ],
        'template' => '_foo_',
      ],
      $this->pluginId,
      [],
    );

    $data = [
      'icon_id' => 'foo:bar',
      'content' => '_baz_',
    ];
    $result = $fontExtractorPlugin->loadIcon($data);

    $expected = $this->createTestIcon([
      'id' => $this->pluginId,
      'pack_id' => $this->pluginId,
      'icon_id' => $data['icon_id'],
      'template' => '_foo_',
      'source' => '',
      'content' => '_baz_',
    ]);
    $this->assertEquals($expected, $result);
  }

}
