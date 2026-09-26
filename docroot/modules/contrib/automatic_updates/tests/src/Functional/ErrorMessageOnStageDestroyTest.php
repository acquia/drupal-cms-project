<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\UpdateSandboxManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests error message when the stage the user is interacting with is destroyed.
 *
 * @internal
 */
#[Group('automatic_updates')]
#[RunTestsInSeparateProcesses]
class ErrorMessageOnStageDestroyTest extends AutomaticUpdatesFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates', 'automatic_updates_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setReleaseMetadata(static::getDrupalRoot() . '/core/modules/package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml');
    $this->mockActiveCoreVersion('9.8.0');

    $this->drupalLogin($this->createUser([
      'administer site configuration',
      'administer software updates',
      'access site reports',
    ]));
  }

  /**
   * Tests error message on previous stage destroy.
   */
  public function testMessagesOnStageDestroy(): void {
    $this->getStageFixtureManipulator()
      ->setCorePackageVersion('9.8.1');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->checkForUpdates();
    $this->drupalGet('/admin/reports/updates/update');
    $assert_session->buttonExists('Update to 9.8.1');
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateReady('9.8.1');
    $sandbox_manager = $this->container->get(UpdateSandboxManager::class);
    $random_message = new TranslatableMarkup("Do you get my message?");
    // @see \Drupal\Tests\package_manager\Kernel\StageTest::testStoreDestroyInfo()
    // @see \Drupal\automatic_updates\CronUpdateRunner::performUpdate()
    $sandbox_manager->destroy(TRUE, $random_message);
    $this->checkForMetaRefresh();
    $page->pressButton('Continue');
    $assert_session->pageTextContains((string) $random_message);
  }

}
