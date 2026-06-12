<?php

namespace Drupal\Tests\canvas_block_twig_suggestions\Unit;

use Drupal\canvas_block_twig_suggestions\CanvasRegionTracker;
use PHPUnit\Framework\TestCase;

// Load the .module file so we can call the real hook functions.
require_once __DIR__ . '/../../../canvas_block_twig_suggestions.module';

/**
 * Integration-style tests for the full suggestion alteration flow.
 *
 * These tests simulate what core produces and verify the end-to-end result
 * by calling the real hook function.
 *
 * @group canvas_block_twig_suggestions
 */
class HookIntegrationTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    CanvasRegionTracker::resetForTesting();
  }

  /**
   * Test the full flow: region tracked, then Canvas block altered.
   *
   * Simulates the render order: preRenderRegion fires first (setting the
   * region), then theme_suggestions_block_alter fires for each block.
   */
  public function testFullFlowCanvasBlock(): void {
    // Step 1: Region pre_render fires.
    CanvasRegionTracker::preRenderRegion(['#region' => 'footer']);

    // Step 2: Core generates suggestions for a Canvas block.
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

    // Step 3: Our hook fires.
    canvas_block_twig_suggestions_theme_suggestions_block_alter($suggestions, $variables);

    // UUID suggestion removed, region suggestion added.
    $expected = [
      'block__system',
      'block__system_menu_block',
      'block__system_menu_block__main',
      'block__footer__system_menu_block_main',
    ];

    $this->assertSame($expected, $suggestions);
  }

  /**
   * Test that regular blocks pass through unmodified.
   */
  public function testFullFlowRegularBlock(): void {
    CanvasRegionTracker::preRenderRegion(['#region' => 'header']);

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
   * Test two Canvas blocks in different regions get distinct suggestions.
   *
   * Simulates: header renders (preRender), then block A gets altered;
   * then footer renders (preRender), then block B gets altered.
   */
  public function testTwoBlocksInDifferentRegions(): void {
    // Header region renders.
    CanvasRegionTracker::preRenderRegion(['#region' => 'header']);

    $header_suggestions = [
      'block__system',
      'block__system_branding_block',
      'block__aaaa1111-2222-3333-4444-555566667777',
    ];
    $header_variables = [
      'elements' => [
        '#id' => 'aaaa1111-2222-3333-4444-555566667777',
        '#plugin_id' => 'system_branding_block',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($header_suggestions, $header_variables);

    $this->assertSame([
      'block__system',
      'block__system_branding_block',
      'block__header__system_branding_block',
    ], $header_suggestions);

    // Footer region renders.
    CanvasRegionTracker::preRenderRegion(['#region' => 'footer']);

    $footer_suggestions = [
      'block__system',
      'block__system_branding_block',
      'block__bbbb1111-2222-3333-4444-555566667777',
    ];
    $footer_variables = [
      'elements' => [
        '#id' => 'bbbb1111-2222-3333-4444-555566667777',
        '#plugin_id' => 'system_branding_block',
      ],
    ];

    canvas_block_twig_suggestions_theme_suggestions_block_alter($footer_suggestions, $footer_variables);

    $this->assertSame([
      'block__system',
      'block__system_branding_block',
      'block__footer__system_branding_block',
    ], $footer_suggestions);
  }

}
