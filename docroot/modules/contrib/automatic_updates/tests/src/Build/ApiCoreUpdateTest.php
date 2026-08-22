<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Build;

use Drupal\automatic_updates\UpdateSandboxManager;
use Drupal\package_manager\Event\PostApplyEvent;
use Drupal\package_manager\Event\PostCreateEvent;
use Drupal\package_manager\Event\PostRequireEvent;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreRequireEvent;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests updating core by directly invoking Package Manager's API.
 */
#[Group('automatic_updates')]
final class ApiCoreUpdateTest extends CoreUpdateTestBase {

  /**
   * Tests an end-to-end core update via the API.
   */
  public function testApi(): void {
    $this->createTestProject('RecommendedProject');
    $query = http_build_query([
      'projects' => [
        'drupal' => '9.8.1',
      ],
    ]);
    // Ensure that the update is prevented if the web root and/or vendor
    // directories are not writable.
    $this->assertReadOnlyFileSystemError("/automatic-updates-test-api?$query");

    $mink = $this->getMink();
    $session = $mink->getSession();
    $session->reload();
    $update_status_code = $session->getStatusCode();
    $file_contents = $session->getPage()->getContent();
    $this->assertExpectedStageEventsFired(
      UpdateSandboxManager::class,
      [
        // ::assertReadOnlyFileSystemError attempts to start an update
        // multiple times so 'PreCreateEvent' will be fired multiple times.
        // @see \Drupal\Tests\automatic_updates\Build\CoreUpdateTest::assertReadOnlyFileSystemError()
        PreCreateEvent::class,
        PreCreateEvent::class,
        PreCreateEvent::class,
        PostCreateEvent::class,
        PreRequireEvent::class,
        PostRequireEvent::class,
        PreApplyEvent::class,
        PostApplyEvent::class,
      ],
      message: 'Error response: ' . $file_contents
    );
    // Even though the response is what we expect, assert the status code as
    // well, to be extra-certain that there was no kind of server-side error.
    $this->assertSame(200, $update_status_code);

    $this->assertStringContainsString(
      "const VERSION = '9.8.1';",
      file_get_contents($this->getWebRoot() . '/core/lib/Drupal.php')
    );
    $this->assertUpdateSuccessful('9.8.1');

    $this->assertRequestedChangesWereLogged([
      'Update drupal/core-dev from 9.8.0 to 9.8.1',
      'Update drupal/core-recommended from 9.8.0 to 9.8.1',
    ]);
    $this->assertAppliedChangesWereLogged([
      'Updated drupal/core from 9.8.0 to 9.8.1',
      'Updated drupal/core-dev from 9.8.0 to 9.8.1',
      'Updated drupal/core-recommended from 9.8.0 to 9.8.1',
    ]);
  }

}
