<?php

namespace Drupal\Tests\canvas_block_twig_suggestions\Unit;

use Drupal\canvas_block_twig_suggestions\CanvasRegionTracker;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CanvasRegionTracker.
 *
 * @coversDefaultClass \Drupal\canvas_block_twig_suggestions\CanvasRegionTracker
 * @group canvas_block_twig_suggestions
 */
class CanvasRegionTrackerTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    CanvasRegionTracker::resetForTesting();
  }

  /**
   * Test that getCurrentRegion returns null when no region has been set.
   *
   * @covers ::getCurrentRegion
   */
  public function testGetCurrentRegionInitiallyNull(): void {
    $this->assertNull(CanvasRegionTracker::getCurrentRegion());
  }

  /**
   * Test preRenderRegion sets the current region from #region.
   *
   * @covers ::preRenderRegion
   * @covers ::getCurrentRegion
   */
  public function testPreRenderRegionSetsCurrentRegion(): void {
    $element = ['#region' => 'header'];

    $result = CanvasRegionTracker::preRenderRegion($element);

    $this->assertSame('header', CanvasRegionTracker::getCurrentRegion());
    $this->assertSame($element, $result);
  }

  /**
   * Test preRenderRegion does not change state when #region is absent.
   *
   * @covers ::preRenderRegion
   */
  public function testPreRenderRegionWithoutRegionProperty(): void {
    $element = ['#some_other_property' => 'value'];

    $result = CanvasRegionTracker::preRenderRegion($element);

    $this->assertNull(CanvasRegionTracker::getCurrentRegion());
    $this->assertSame($element, $result);
  }

  /**
   * Test that a second preRenderRegion call overwrites the previous region.
   *
   * @covers ::preRenderRegion
   * @covers ::getCurrentRegion
   */
  public function testPreRenderRegionOverwritesPreviousRegion(): void {
    CanvasRegionTracker::preRenderRegion(['#region' => 'header']);
    $this->assertSame('header', CanvasRegionTracker::getCurrentRegion());

    CanvasRegionTracker::preRenderRegion(['#region' => 'footer']);
    $this->assertSame('footer', CanvasRegionTracker::getCurrentRegion());
  }

  /**
   * Test that #region is cast to string.
   *
   * @covers ::preRenderRegion
   */
  public function testPreRenderRegionCastsToString(): void {
    // Drupal render arrays could theoretically contain non-string values.
    $element = ['#region' => 123];

    CanvasRegionTracker::preRenderRegion($element);

    $this->assertSame('123', CanvasRegionTracker::getCurrentRegion());
  }

  /**
   * Test trustedCallbacks returns the expected callbacks.
   *
   * @covers ::trustedCallbacks
   */
  public function testTrustedCallbacks(): void {
    $callbacks = CanvasRegionTracker::trustedCallbacks();
    $this->assertSame(['preRenderRegion'], $callbacks);
  }

  /**
   * Test resetForTesting clears the region.
   *
   * @covers ::resetForTesting
   */
  public function testResetForTesting(): void {
    CanvasRegionTracker::preRenderRegion(['#region' => 'content']);
    $this->assertSame('content', CanvasRegionTracker::getCurrentRegion());

    CanvasRegionTracker::resetForTesting();
    $this->assertNull(CanvasRegionTracker::getCurrentRegion());
  }

}
