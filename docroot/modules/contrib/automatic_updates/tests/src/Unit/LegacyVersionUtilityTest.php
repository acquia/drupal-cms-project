<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Unit;

use Drupal\package_manager\LegacyVersionUtility;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * @internal
 */
#[Group('automatic_updates')]
#[CoversClass(LegacyVersionUtility::class)]
class LegacyVersionUtilityTest extends UnitTestCase {

  /**
   * @param string $version_number
   *   The version number to covert.
   * @param string $expected
   *   The expected result.
   */
  #[TestWith(['8.x-1.2', '1.2.0'])]
  #[TestWith(['8.x-1.2-alpha1', '1.2.0-alpha1'])]
  #[TestWith(['1.2.0', '1.2.0'])]
  #[TestWith(['1.2.0-alpha1', '1.2.0-alpha1'])]
  public function testConvertToSemanticVersion(string $version_number, string $expected): void {
    $this->assertSame($expected, LegacyVersionUtility::convertToSemanticVersion($version_number));
  }

  /**
   * @param string $version_number
   *   The version number to covert.
   * @param string|null $expected
   *   The expected result.
   */
  #[TestWith(['1.2.0', '8.x-1.2'])]
  #[TestWith(['1.2.0-alpha1', '8.x-1.2-alpha1'])]
  #[TestWith(['8.x-1.2', '8.x-1.2'])]
  #[TestWith(['8.x-1.2-alpha1', '8.x-1.2-alpha1'])]
  #[TestWith(['1.2.3', NULL])]
  #[TestWith(['1.2.3-alpha1', NULL])]
  public function testConvertToLegacyVersion(string $version_number, ?string $expected): void {
    $this->assertSame($expected, LegacyVersionUtility::convertToLegacyVersion($version_number));
  }

}
