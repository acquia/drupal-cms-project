<?php

namespace Drupal\Tests\canvas_block_twig_suggestions\Unit;

use Drupal\canvas_block_twig_suggestions\CanvasRegionTracker;
use PHPUnit\Framework\TestCase;

// Load the .module file so we can call the real hook functions.
require_once __DIR__ . '/../../../canvas_block_twig_suggestions.module';

/**
 * Tests for canvas_block_twig_suggestions_theme_suggestions_block_alter().
 *
 * Core's BlockThemeHooks::themeSuggestionsBlock() generates the #id-based
 * suggestion as 'block__' . $id with hyphens preserved (no strtr). So for a
 * Canvas UUID like 'a4ada991-2613-4901-99c4-f23c2efc02b0', the suggestion is
 * 'block__a4ada991-2613-4901-99c4-f23c2efc02b0' (hyphens intact).
 *
 * @group canvas_block_twig_suggestions
 */
class CanvasBlockTwigSuggestionsTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    CanvasRegionTracker::resetForTesting();
  }

  /**
   * Test that a Canvas-placed block gets UUID suggestion stripped and
   * region-aware suggestion added.
   */
  public function testCanvasBlockGetsRegionSuggestion(): void {
    CanvasRegionTracker::preRenderRegion(['#region' => 'footer']);

    $suggestions = [
      'block__system',
      'block__system_menu_block',
      'block__system_menu_block__main',
      'block__a4ada991-2613-4901-99c4-f23c2efc02b0',
    ];

    $variables = [
      'elements' => [
        '#id' => 'a4ada991-2613-4901-99c4-f23c2efc02b0',
        '#plugin_id' => 'system_menu_block:main',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    $expected = [
      'block__system',
      'block__system_menu_block',
      'block__system_menu_block__main',
      'block__footer__system_menu_block_main',
    ];

    $this->assertSame($expected, $suggestions);
  }

  /**
   * Test that a regular (non-Canvas) block is not modified.
   */
  public function testRegularBlockNotModified(): void {
    $suggestions = [
      'block__system',
      'block__system_menu_block',
      'block__system_menu_block__main',
    ];
    $original = $suggestions;

    $variables = [
      'elements' => [
        '#id' => 'system_menu_block_main',
        '#plugin_id' => 'system_menu_block:main',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    $this->assertSame($original, $suggestions);
  }

  /**
   * Test that a Canvas block with no #plugin_id key still has UUID suggestion
   * stripped but no region suggestion added.
   */
  public function testCanvasBlockWithoutPluginId(): void {
    CanvasRegionTracker::preRenderRegion(['#region' => 'header']);

    $suggestions = [
      'block__system',
      'block__a4ada991-2613-4901-99c4-f23c2efc02b0',
    ];

    $variables = [
      'elements' => [
        '#id' => 'a4ada991-2613-4901-99c4-f23c2efc02b0',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    $this->assertSame(['block__system'], $suggestions);
  }

  /**
   * Test that a Canvas block with null plugin_id still has UUID suggestion
   * stripped but no region suggestion added.
   */
  public function testCanvasBlockWithNullPluginId(): void {
    CanvasRegionTracker::preRenderRegion(['#region' => 'header']);

    $suggestions = [
      'block__a4ada991-2613-4901-99c4-f23c2efc02b0',
      'block__system_menu_block__main',
    ];

    $variables = [
      'elements' => [
        '#id' => 'a4ada991-2613-4901-99c4-f23c2efc02b0',
        '#plugin_id' => NULL,
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    $this->assertSame(['block__system_menu_block__main'], $suggestions);
  }

  /**
   * Test that when no region is tracked, no region suggestion is added but
   * the UUID suggestion is still stripped.
   */
  public function testCanvasBlockWithNoRegionTracked(): void {
    $suggestions = [
      'block__system',
      'block__system_menu_block__main',
      'block__a4ada991-2613-4901-99c4-f23c2efc02b0',
    ];

    $variables = [
      'elements' => [
        '#id' => 'a4ada991-2613-4901-99c4-f23c2efc02b0',
        '#plugin_id' => 'system_menu_block:main',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    $expected = [
      'block__system',
      'block__system_menu_block__main',
    ];

    $this->assertSame($expected, $suggestions);
  }

  /**
   * Test that null #id is left alone.
   */
  public function testNullIdNotModified(): void {
    $suggestions = ['block__system'];
    $original = $suggestions;

    $variables = [
      'elements' => [
        '#id' => NULL,
        '#plugin_id' => 'system_menu_block:main',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    $this->assertSame($original, $suggestions);
  }

  /**
   * Test that missing #id key is left alone.
   */
  public function testMissingIdNotModified(): void {
    $suggestions = ['block__system'];
    $original = $suggestions;

    $variables = [
      'elements' => [
        '#plugin_id' => 'system_menu_block:main',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    $this->assertSame($original, $suggestions);
  }

  /**
   * Test multiple UUID-based suggestions are all removed.
   *
   * block_content module also uses $variables['elements']['#id'] directly
   * (hyphens preserved) for suggestions like block__block_content__id__[uuid].
   */
  public function testMultipleUuidSuggestionsRemoved(): void {
    CanvasRegionTracker::preRenderRegion(['#region' => 'content']);

    $uuid = 'b5f3c891-1234-5678-abcd-ef0123456789';
    $suggestions = [
      'block__block_content',
      'block__block_content__basic',
      'block__block_content__id__' . $uuid,
      'block__block_content__id_view__' . $uuid . '__full',
      'block__' . $uuid,
    ];

    $variables = [
      'elements' => [
        '#id' => $uuid,
        '#plugin_id' => 'block_content:basic',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    $expected = [
      'block__block_content',
      'block__block_content__basic',
      'block__content__block_content_basic',
    ];

    $this->assertSame($expected, $suggestions);
  }

  /**
   * Test plugin ID with hyphens gets cleaned to underscores.
   */
  public function testPluginIdWithHyphensGetsCleaned(): void {
    CanvasRegionTracker::preRenderRegion(['#region' => 'header']);

    $suggestions = [
      'block__custom',
      'block__a4ada991-2613-4901-99c4-f23c2efc02b0',
    ];

    $variables = [
      'elements' => [
        '#id' => 'a4ada991-2613-4901-99c4-f23c2efc02b0',
        '#plugin_id' => 'my-custom-plugin:some-type',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    $expected = [
      'block__custom',
      'block__header__my_custom_plugin_some_type',
    ];

    $this->assertSame($expected, $suggestions);
  }

  /**
   * Test region name with hyphens gets cleaned to underscores.
   */
  public function testRegionNameWithHyphensGetsCleaned(): void {
    CanvasRegionTracker::preRenderRegion(['#region' => 'sidebar-left']);

    $suggestions = [
      'block__system',
      'block__a4ada991-2613-4901-99c4-f23c2efc02b0',
    ];

    $variables = [
      'elements' => [
        '#id' => 'a4ada991-2613-4901-99c4-f23c2efc02b0',
        '#plugin_id' => 'system_menu_block:main',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    $expected = [
      'block__system',
      'block__sidebar_left__system_menu_block_main',
    ];

    $this->assertSame($expected, $suggestions);
  }

}
