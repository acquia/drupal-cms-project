<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Unit\VersionPolicy;

use Drupal\automatic_updates\Validator\VersionPolicy\ForbidDevSnapshot;
use Drupal\Tests\automatic_updates\Traits\VersionPolicyTestTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * @internal
 */
#[Group('automatic_updates')]
#[CoversClass(ForbidDevSnapshot::class)]
class ForbidDevSnapshotTest extends UnitTestCase {

  use VersionPolicyTestTrait;

  /**
   * Tests that trying to update from a dev snapshot raises an error.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string[]|null $expected_errors
   *   The expected error messages, or NULL if none are expected.
   */
  #[TestWith(['9.8.0', NULL])]
  #[TestWith(['9.8.0-alpha3', NULL])]
  #[TestWith(['9.8.0-beta7', NULL])]
  #[TestWith(['9.8.0-rc2', NULL])]
  #[TestWith([
    '9.8.0-dev',
    ['Drupal cannot be automatically updated from the installed version, 9.8.0-dev, because automatic updates from a dev version to any other version are not supported.'],
  ])]
  public function testForbidDevSnapshot(string $installed_version, ?array $expected_errors): void {
    $rule = new ForbidDevSnapshot();
    $this->assertPolicyErrors($rule, $installed_version, '9.8.1', $expected_errors ?? []);
  }

}
