<?php

declare(strict_types=1);

namespace Drupal\Tests\cva\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the html_cva Twig function works in a sandboxed environment.
 */
#[Group('cva')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
final class CvaRenderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['cva', 'cva_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that html_cva() renders variant classes correctly.
   */
  public function testCvaRendering(): void {
    $this->drupalGet('/cva-test/render');
    $this->assertSession()->statusCodeEquals(200);

    // The "nice" variant should produce the "friendly" class.
    $this->assertSession()->responseContains('class="cva-friendliness friendly"');

    // The "mean" variant should produce the "unfriendly" class.
    $this->assertSession()->responseContains('class="cva-friendliness unfriendly"');
  }

}
