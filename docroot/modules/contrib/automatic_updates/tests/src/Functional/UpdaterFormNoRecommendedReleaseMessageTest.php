<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests messages on the updater form when there is no recommended release.
 *
 * @internal
 */
#[Group('automatic_updates')]
#[RunTestsInSeparateProcesses]
class UpdaterFormNoRecommendedReleaseMessageTest extends AutomaticUpdatesFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'automatic_updates_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $account = $this->drupalCreateUser([
      'administer software updates',
      'administer site configuration',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Data provider for testMessages().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerMessages(): array {
    $dir = static::getDrupalRoot() . '/core/modules/package_manager/tests/fixtures/release-history';

    return [
      'current' => [
        $dir . '/drupal.9.8.1-security.xml',
        '9.8.1',
        FALSE,
        'status',
      ],
      'not current' => [
        $dir . '/drupal.9.8.2.xml',
        '9.7.1',
        TRUE,
        'status',
      ],
      'insecure' => [
        $dir . '/drupal.9.8.1-security.xml',
        '9.7.1',
        TRUE,
        'error',
      ],
    ];
  }

  /**
   * Tests messages when there is no recommended release.
   *
   * @param string $release_metadata
   *   The path of the release metadata to use.
   * @param string $installed_version
   *   The currently installed version of Drupal core.
   * @param bool $updates_available
   *   Whether or not any available updates will be detected.
   * @param string $expected_message_type
   *   The expected type of message (status or error).
   */
  #[DataProvider('providerMessages')]
  public function testMessages(string $release_metadata, string $installed_version, bool $updates_available, string $expected_message_type): void {
    $this->setReleaseMetadata($release_metadata);
    $this->mockActiveCoreVersion($installed_version);
    $this->checkForUpdates();
    $this->drupalGet('/admin/reports/updates/update');

    $assert_session = $this->assertSession();
    if ($updates_available) {
      $assert_session->statusMessageContains('Updates were found, but they must be performed manually.', $expected_message_type);
      $assert_session->linkExists('the list of available updates');
    }
    else {
      $assert_session->statusMessageContains('No update available', $expected_message_type);
    }
    $assert_session->buttonNotExists('Update');
  }

}
