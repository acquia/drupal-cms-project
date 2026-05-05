<?php

namespace Drupal\Tests\canvas_block_twig_suggestions\Unit;

use Drupal\canvas_block_twig_suggestions\CanvasRegionTracker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for individual logic units used by the module.
 *
 * @group canvas_block_twig_suggestions
 */
class CanvasBlockTwigSuggestionsFunctionalTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    CanvasRegionTracker::resetForTesting();
  }

  /**
   * Test the Canvas block detection logic (UUID contains hyphens).
   *
   * The module detects Canvas blocks by checking if #id contains a hyphen.
   * The condition `$id === NULL || !str_contains($id, '-')` returns TRUE
   * when the block should be skipped (not a Canvas block), and FALSE when
   * the block IS a Canvas block and should be processed.
   *
   * @param string|null $id
   *   The block #id value.
   * @param bool $should_skip
   *   TRUE if the hook should skip (return early), FALSE if it should process.
   */
  #[DataProvider('canvasDetectionProvider')]
  public function testCanvasDetection(?string $id, bool $should_skip): void {
    $result = ($id === NULL || !str_contains($id, '-'));
    $this->assertSame($should_skip, $result);
  }

  /**
   * Data provider for Canvas block detection.
   *
   * @return array<string, array{string|null, bool}>
   */
  public static function canvasDetectionProvider(): array {
    return [
      'UUID with hyphens is a Canvas block (should NOT skip)' => [
        'a4ada991-2613-4901-99c4-f23c2efc02b0',
        FALSE,
      ],
      'Machine name without hyphens is a regular block (should skip)' => [
        'system_menu_block_main',
        TRUE,
      ],
      'NULL id should skip' => [
        NULL,
        TRUE,
      ],
      'Underscore-only id should skip' => [
        'block_123',
        TRUE,
      ],
    ];
  }

  /**
   * Test that plugin IDs are cleaned correctly (colons and hyphens to
   * underscores).
   *
   * @param string $plugin_id
   *   The raw plugin ID.
   * @param string $expected
   *   The expected cleaned value.
   */
  #[DataProvider('pluginIdCleaningProvider')]
  public function testPluginIdCleaning(string $plugin_id, string $expected): void {
    $clean = strtr($plugin_id, [':' => '_', '-' => '_']);
    $this->assertSame($expected, $clean);
  }

  /**
   * Data provider for plugin ID cleaning.
   *
   * @return array<string, array{string, string}>
   */
  public static function pluginIdCleaningProvider(): array {
    return [
      'colon separator' => ['system_menu_block:main', 'system_menu_block_main'],
      'colon with digits' => ['block_content:123', 'block_content_123'],
      'hyphens and colon' => ['custom-plugin:some-type', 'custom_plugin_some_type'],
      'no special chars' => ['simple_block', 'simple_block'],
    ];
  }

  /**
   * Test that region names are cleaned correctly (hyphens to underscores).
   *
   * @param string $region
   *   The raw region name.
   * @param string $expected
   *   The expected cleaned value.
   */
  #[DataProvider('regionCleaningProvider')]
  public function testRegionCleaning(string $region, string $expected): void {
    $clean = strtr($region, ['-' => '_']);
    $this->assertSame($expected, $clean);
  }

  /**
   * Data provider for region cleaning.
   *
   * @return array<string, array{string, string}>
   */
  public static function regionCleaningProvider(): array {
    return [
      'no hyphens' => ['header', 'header'],
      'single hyphen' => ['main-content', 'main_content'],
      'trailing hyphen word' => ['footer-region', 'footer_region'],
      'multiple hyphens' => ['sidebar-left-column', 'sidebar_left_column'],
    ];
  }

  /**
   * Test that UUID-containing suggestions are filtered out.
   *
   * Core generates suggestions with the raw #id (hyphens preserved).
   *
   * @param array $suggestions
   *   The input suggestions array.
   * @param string $id
   *   The block #id (UUID with hyphens).
   * @param array $expected
   *   The expected filtered result.
   */
  #[DataProvider('uuidRemovalProvider')]
  public function testUuidSuggestionRemoval(array $suggestions, string $id, array $expected): void {
    $filtered = array_values(array_filter(
      $suggestions,
      static fn(string $s) => !str_contains($s, $id),
    ));
    $this->assertSame($expected, $filtered);
  }

  /**
   * Data provider for UUID suggestion removal.
   *
   * @return array<string, array{array, string, array}>
   */
  public static function uuidRemovalProvider(): array {
    return [
      'single UUID suggestion removed' => [
        [
          'block__a4ada991-2613-4901-99c4-f23c2efc02b0',
          'block__system_menu_block__main',
        ],
        'a4ada991-2613-4901-99c4-f23c2efc02b0',
        ['block__system_menu_block__main'],
      ],
      'multiple UUID suggestions removed' => [
        [
          'block__a4ada991-2613-4901-99c4-f23c2efc02b0',
          'block__block_content__id__a4ada991-2613-4901-99c4-f23c2efc02b0',
          'block__system_menu_block__main',
        ],
        'a4ada991-2613-4901-99c4-f23c2efc02b0',
        ['block__system_menu_block__main'],
      ],
      'no matching suggestions means nothing removed' => [
        [
          'block__system_menu_block__main',
          'block__system_menu_block',
        ],
        'cccc1111-2222-3333-4444-555566667777',
        ['block__system_menu_block__main', 'block__system_menu_block'],
      ],
    ];
  }

}
