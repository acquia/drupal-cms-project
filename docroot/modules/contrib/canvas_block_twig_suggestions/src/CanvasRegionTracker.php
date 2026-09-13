<?php

declare(strict_types=1);

namespace Drupal\canvas_block_twig_suggestions;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Tracks which region is currently rendering in the page build.
 *
 * Drupal core's HtmlRenderer adds #theme_wrappers and #region to each region
 * render array AFTER the display variant builds the page. When a region render
 * array begins rendering, its #pre_render fires before its children (blocks)
 * render. This class provides that #pre_render callback to capture the region
 * name so it is available during hook_theme_suggestions_block_alter().
 */
final class CanvasRegionTracker implements TrustedCallbackInterface {

  /**
   * The region currently being rendered.
   */
  private static ?string $currentRegion = NULL;

  /**
   * Gets the current region.
   */
  public static function getCurrentRegion(): ?string {
    return self::$currentRegion;
  }

  /**
   * Resets the tracked region. Used by unit tests to isolate state.
   */
  public static function resetForTesting(): void {
    self::$currentRegion = NULL;
  }

  /**
   * #pre_render callback: captures the region name before children render.
   *
   * This callback is added to each region render array in
   * hook_preprocess_page(). It reads the #region property that core's
   * HtmlRenderer::buildPageRenderArray() sets on every non-empty region.
   *
   * @param array $element
   *   The region render array.
   *
   * @return array
   *   The unmodified render array.
   */
  public static function preRenderRegion(array $element): array {
    if (isset($element['#region'])) {
      self::$currentRegion = (string) $element['#region'];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderRegion'];
  }

}
