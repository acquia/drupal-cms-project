<?php

declare(strict_types=1);

namespace Drupal\Tests\Dashboard\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\dashboard\Hook\GinThemeIntegration;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for dashboard gin integration.
 */
#[Group('dashboard')]
class GinThemeIntegrationTest extends UnitTestCase {

  /**
   * Tests library info alter.
   */
  #[DataProvider('provider')]
  public function testLibraryInfoAlter(string $extension, ActiveTheme $activeTheme, bool $mustAddLibraries): void {
    $themeManager = $this->prophesize(ThemeManagerInterface::class);
    $themeManager->getActiveTheme()->willReturn($activeTheme);

    $libraries = [];
    $libraries['dashboard']['css']['theme'] = [];
    $sut = new GinThemeIntegration($themeManager->reveal());
    $libraries = $sut->libraryInfoAlter($libraries, $extension);

    if ($mustAddLibraries) {
      $this->assertArrayHasKey('css/dashboard.gin.css', $libraries['dashboard']['css']['theme']);
    }
    else {
      $this->assertArrayNotHasKey('css/dashboard.gin.css', $libraries['dashboard']['css']['theme']);
    }
  }

  /**
   * Provider of active themes.
   */
  public static function provider(): \Generator {
    yield 'different extension' => [
      'another_extension',
      new ActiveTheme(['name' => 'gin', 'base_theme_extensions' => []]),
      FALSE,
    ];
    yield 'gin' => [
      'dashboard',
      new ActiveTheme(['name' => 'gin', 'base_theme_extensions' => []]),
      TRUE,
    ];
    yield 'a gin sub-theme' => [
      'dashboard',
      new ActiveTheme(['name' => 'gin_tonic', 'base_theme_extensions' => ['gin' => NULL]]),
      TRUE,
    ];
  }

}
