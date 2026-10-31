<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Build;

use Behat\Mink\Element\DocumentElement;
use Drupal\Tests\WebAssert;

/**
 * Tests an end-to-end update of Drupal core.
 *
 * @internal
 */
abstract class CoreUpdateTestBase extends UpdateTestBase {

  /**
   * WebAssert object.
   *
   * @var \Drupal\Tests\WebAssert
   */
  protected $webAssert;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->webAssert = new WebAssert($this->getMink()->getSession());
  }

  /**
   * {@inheritdoc}
   */
  public function copyCodebase(?\Iterator $iterator = NULL, $working_dir = NULL): void {
    parent::copyCodebase($iterator, $working_dir);

    // Ensure that we will install Drupal 9.8.0 (a fake version that should
    // never exist in real life) initially.
    $this->setUpstreamCoreVersion('9.8.0');
  }

  /**
   * {@inheritdoc}
   */
  public function getCodebaseFinder() {
    // Don't copy .git directories and such, since that just slows things down.
    // We can use ::setUpstreamCoreVersion() to explicitly set the versions of
    // core packages required by the test site.
    return parent::getCodebaseFinder()->ignoreVCS(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function createTestProject(string $template): void {
    putenv('COMPOSER_BIN_DIR');
    parent::createTestProject($template);

    // Ensure that the auto-update CLI tool is present.
    $this->assertFileExists($this->getWorkingPath('project/vendor/bin') . '/auto-update');

    // Prepare an "upstream" version of core, 9.8.1, to which we will update.
    // This version, along with 9.8.0 (which was installed initially), is
    // referenced in our fake release metadata (see
    // fixtures/release-history/drupal.0.0.xml).
    $this->setUpstreamCoreVersion('9.8.1');
    $this->setReleaseMetadata([
      'drupal' => static::getDrupalRoot() . '/core/modules/package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml',
    ]);

    // Ensure that Drupal thinks we are running 9.8.0, then refresh information
    // about available updates and ensure that an update to 9.8.1 is available.
    $this->assertCoreVersion('9.8.0');
    $this->checkForUpdates();
    $this->visit('/admin/reports/updates/update');
    $this->getMink()->assertSession()->pageTextContains('9.8.1');

    $this->assertStatusReportChecksSuccessful();

    // Ensure that Drupal has write-protected the site directory.
    $this->assertDirectoryIsNotWritable($this->getWebRoot() . '/sites/default');
  }

  /**
   * Asserts that the update is prevented if the filesystem isn't writable.
   *
   * @param string $error_url
   *   A URL where we can see the error message which is raised when parts of
   *   the file system are not writable. This URL will be visited twice: once
   *   for the web root, and once for the vendor directory.
   */
  protected function assertReadOnlyFileSystemError(string $error_url): void {
    $directories = [
      'Drupal' => rtrim($this->getWebRoot(), './'),
    ];

    // The location of the vendor directory depends on which project template
    // was used to build the test site, so just ask Composer where it is.
    $directories['vendor'] = $this->runComposer('composer config --absolute vendor-dir', 'project');

    $assert_session = $this->getMink()->assertSession();
    foreach ($directories as $type => $path) {
      chmod($path, 0555);
      $this->assertDirectoryIsNotWritable($path);
      $this->visit($error_url);
      $assert_session->pageTextContains("The $type directory \"$path\" is not writable.");
      chmod($path, 0755);
      $this->assertDirectoryIsWritable($path);
    }
  }

  /**
   * Asserts that a specific version of Drupal core is running.
   *
   * Assumes that a user with permission to view the status report is logged in.
   *
   * @param string $expected_version
   *   The version of core that should be running.
   */
  protected function assertCoreVersion(string $expected_version): void {
    $this->visit('/admin/reports/status');
    $item = $this->getMink()
      ->assertSession()
      ->elementExists('css', 'h3:contains("Drupal Version")')
      ->getParent()
      ->getText();
    $this->assertStringContainsString($expected_version, $item);
  }

  /**
   * Asserts that Drupal core was updated successfully.
   *
   * Assumes that a user with appropriate permissions is logged in.
   *
   * @param string $expected_version
   *   The expected active version of Drupal core.
   */
  protected function assertUpdateSuccessful(string $expected_version): void {
    $mink = $this->getMink();
    $page = $mink->getSession()->getPage();
    $assert_session = $mink->assertSession();

    // There should be log messages, but no errors or warnings should have been
    // logged by Automatic Updates.
    // The use of the database log here implies one can only retrieve log
    // entries through the dblog UI. This seems non-ideal but it is the choice
    // that requires least custom configuration or custom code. Using the
    // `syslog` or `syslog_test` module  or the `@RestResource=dblog` plugin for
    // the `rest` module require more additional code than the inflexible log
    // querying below.
    $this->visit('/admin/reports/dblog');
    $type_select_field = $assert_session->fieldExists('Type');

    // Automatic Updates may not have logged any entries but if it did there
    // should not have been any errors or warnings.
    if ($type_select_field->find('named', ['option', 'automatic_updates'])) {
      $assert_session->pageTextNotContains('No log messages available.');
      $page->selectFieldOption('Type', 'automatic_updates');
      $page->selectFieldOption('Severity', 'Emergency', TRUE);
      $page->selectFieldOption('Severity', 'Alert', TRUE);
      $page->selectFieldOption('Severity', 'Critical', TRUE);
      $page->selectFieldOption('Severity', 'Warning', TRUE);
      $page->pressButton('Filter');
      $assert_session->pageTextContains('No log messages available.');
    }

    // \Drupal\automatic_updates\Routing\RouteSubscriber::alterRoutes() sets
    // `_automatic_updates_status_messages: skip` on the route for the path
    // `/admin/modules/reports/status`, but not on the `/admin/reports` path. So
    // to test AdminStatusCheckMessages::displayAdminPageMessages(), another
    // page must be visited. `/admin/reports` was chosen, but it could be
    // another too.
    $this->visit('/admin/reports');
    $assert_session->statusCodeEquals(200);
    // @see \Drupal\automatic_updates\Validation\AdminStatusCheckMessages::displayAdminPageMessages()
    $this->webAssert->statusMessageNotExists('error');
    $this->webAssert->statusMessageNotExists('warning');

    $web_root = $this->getWebRoot();
    $placeholder = file_get_contents("$web_root/core/README.txt");
    $this->assertSame("Placeholder for Drupal core $expected_version.", $placeholder);

    foreach (['default.settings.php', 'default.services.yml'] as $file) {
      $file = $web_root . '/sites/default/' . $file;
      $this->assertFileIsReadable($file);
      // The `default.settings.php` and `default.services.yml` files are
      // explicitly excluded from Package Manager operations, since they are not
      // relevant to existing sites. Therefore, ensure that the changes we made
      // to the original (scaffold) versions of the files are not present in
      // the updated site.
      // @see \Drupal\package_manager\PathExcluder\SiteConfigurationExcluder()
      // @see \Drupal\Tests\package_manager\Build\TemplateProjectTestBase::setUpstreamCoreVersion()
      $this->assertStringNotContainsString("# This is part of Drupal $expected_version.", file_get_contents($file));
    }
    $this->assertDirectoryIsNotWritable("$web_root/sites/default");

    $info = $this->runComposer('composer info --self --format json', 'project', TRUE);

    // The production dependencies should have been updated.
    $this->assertSame($expected_version, $info['requires']['drupal/core-recommended']);
    $this->assertSame($expected_version, $info['requires']['drupal/core-composer-scaffold']);
    $this->assertSame($expected_version, $info['requires']['drupal/core-project-message']);
    // The core-vendor-hardening plugin is only used by the legacy project
    // template.
    if ($info['name'] === 'drupal/legacy-project') {
      $this->assertSame($expected_version, $info['requires']['drupal/core-vendor-hardening']);
    }
    // The production dependencies should not be listed as dev dependencies.
    $this->assertArrayNotHasKey('drupal/core-recommended', $info['devRequires']);
    $this->assertArrayNotHasKey('drupal/core-composer-scaffold', $info['devRequires']);
    $this->assertArrayNotHasKey('drupal/core-project-message', $info['devRequires']);
    $this->assertArrayNotHasKey('drupal/core-vendor-hardening', $info['devRequires']);

    // The drupal/core-dev metapackage should not be a production dependency...
    $this->assertArrayNotHasKey('drupal/core-dev', $info['requires']);
    // ...but it should have been updated in the dev dependencies.
    $this->assertSame($expected_version, $info['devRequires']['drupal/core-dev']);
    // The update form should not have any available updates.
    $this->visit('/admin/reports/updates/update');
    $assert_session = $this->getMink()->assertSession();
    $assert_session->pageTextContains('No update available');
    $assert_session->pageTextNotContains('Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup.');

    // The status page should report that we're running the expected version and
    // the README and default site configuration files should contain the
    // placeholder text written by ::setUpstreamCoreVersion(), even though
    // `sites/default` is write-protected.
    // @see ::createTestProject()
    // @see ::setUpstreamCoreVersion()
    $this->assertCoreVersion($expected_version);
  }

  /**
   * Performs core update till update ready form.
   *
   * @param \Behat\Mink\Element\DocumentElement $page
   *   The page element.
   */
  protected function coreUpdateTillUpdateReady(DocumentElement $page): void {
    $session = $this->getMink()->getSession();
    $this->visit('/admin/modules');
    $assert_session = $this->getMink()->assertSession($session);
    $assert_session->pageTextContains('There is a security update available for your version of Drupal.');
    $page->clickLink('Update');

    // Ensure that the update is prevented if the web root and/or vendor
    // directories are not writable.
    $this->assertReadOnlyFileSystemError(parse_url($session->getCurrentUrl(), PHP_URL_PATH));
    $session->reload();

    $assert_session->pageTextNotContains('There is a security update available for your version of Drupal.');
    // Ensure test failures provide helpful debug output when failing readiness
    // checks prevent updates.
    // @see \Drupal\Tests\WebAssert::buildStatusMessageSelector()
    if ($error_message = $session->getPage()->find('xpath', '//div[@data-drupal-messages]//div[@aria-label="Error message"]')) {
      /** @var \Behat\Mink\Element\NodeElement $error_message */
      $this->assertSame('', $error_message->getText());
    }
    $page->pressButton('Update to 9.8.1');
    $this->waitForBatchJob();
    $assert_session->pageTextContains('Ready to update');
  }

  /**
   * Assert a cron update ran successfully.
   */
  protected function assertCronUpdateSuccessful(): void {
    $mink = $this->getMink();
    $page = $mink->getSession()->getPage();
    $this->visit('/admin/reports/dblog');

    // Ensure that the update occurred.
    $page->selectFieldOption('Severity', 'Info');
    $page->pressButton('Filter');
    // There should be a log entry about the successful update.
    $mink->assertSession()
      ->elementAttributeContains('named', ['link', 'Drupal core has been updated from 9.8.0 to 9.8.1'], 'href', '/admin/reports/dblog/event/');
    $this->assertUpdateSuccessful('9.8.1');
  }

}
