<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel;

use Drupal\Core\Asset\AttachedAssets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the gtag and gtm scripts do not block rendering.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class ScriptPlacementTest extends GoogleTagTestCase {

  /**
   * Tests that the gtag and gtm libraries load in the footer.
   *
   * @param string $library
   *   The library name.
   * @param string $file
   *   The library's script file.
   *
   * @dataProvider providerLibraries
   */
  #[DataProvider('providerLibraries')]
  public function testFooterPlacement(string $library, string $file): void {
    $assets = AttachedAssets::createFromRenderArray([
      '#attached' => [
        'library' => [$library],
      ],
    ]);
    [$header, $footer] = $this->container->get('asset.resolver')
      ->getJsAssets($assets, FALSE);

    $file = $this->container->get('extension.list.module')
      ->getPath('google_tag') . '/' . $file;
    self::assertArrayHasKey($file, $footer);
    self::assertArrayNotHasKey($file, $header);
    // Nothing the library depends on may be pulled into the header either.
    self::assertSame([], $header);
  }

  /**
   * Data provider for testFooterPlacement().
   *
   * @return array<string, array{string, string}>
   *   The library name and its script file.
   */
  public static function providerLibraries(): array {
    return [
      'gtag' => ['google_tag/gtag', 'js/gtag.js'],
      'gtm' => ['google_tag/gtm', 'js/gtm.js'],
    ];
  }

}
