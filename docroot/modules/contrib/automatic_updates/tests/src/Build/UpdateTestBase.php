<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Build;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\Tests\package_manager\Build\TemplateProjectTestBase;

/**
 * Base class for tests that perform in-place updates.
 *
 * @internal
 */
abstract class UpdateTestBase extends TemplateProjectTestBase {

  // BEGIN: DELETE FROM CORE MERGE REQUEST

  /**
   * {@inheritdoc}
   */
  protected function setUpstreamCoreVersion(string $version): void {
    require_once static::getDrupalRoot() . '/composer/Composer.php';
    parent::setUpstreamCoreVersion($version);
  }

  // END: DELETE FROM CORE MERGE REQUEST

  /**
   * {@inheritdoc}
   */
  protected function createTestProject(string $template): void {
    parent::createTestProject($template);

    // BEGIN: DELETE FROM CORE MERGE REQUEST
    // List the info files that need to be made compatible with our fake version
    // of Drupal core.
    $info_files = [
      'modules/contrib/automatic_updates/automatic_updates.info.yml',
    ];
    // Install Automatic Updates into the test project and ensure it wasn't
    // symlinked.
    $automatic_updates_dir = dirname(__FILE__, 4);
    if (basename($automatic_updates_dir) === 'automatic_updates') {
      $dir = 'project';
      $this->runComposer("composer config repo.automatic_updates path $automatic_updates_dir", $dir);
      $output = $this->runComposer('composer require --update-with-all-dependencies psr/http-message "drupal/automatic_updates:@dev"', $dir);
      $this->assertStringNotContainsString('Symlinking', $output);
    }
    foreach ($info_files as $path) {
      $path = $this->getWebRoot() . $path;
      $this->assertFileIsWritable($path);
      $info = file_get_contents($path);
      $info = Yaml::decode($info);
      $info['core_version_requirement'] .= ' || ^9.7';
      file_put_contents($path, Yaml::encode($info));
    }
    // END: DELETE FROM CORE MERGE REQUEST

    // @todo Remove in https://www.drupal.org/project/automatic_updates/issues/3284443
    $code = <<<END
\$config['automatic_updates.settings']['unattended']['level'] = 'security';
END;
    $this->writeSettings($code);
    // Install Automatic Updates, and other modules needed for testing.
    $this->installModules([
      'automatic_updates',
      'automatic_updates_test_api',
    ]);

    // Uninstall Automated Cron because this will run cron updates on most
    // requests, making it difficult to test other forms of updating.
    // Also uninstall Big Pipe, since it may cause page elements to be rendered
    // in the background and replaced with JavaScript, which isn't supported in
    // build tests.
    // @see \Drupal\Tests\automatic_updates\Build\CoreUpdateTest::testAutomatedCron
    $page = $this->getMink()->getSession()->getPage();
    $this->visit('/admin/modules/uninstall');
    $page->checkField("uninstall[automated_cron]");
    $page->checkField('uninstall[big_pipe]');
    $page->pressButton('Uninstall');
    $page->pressButton('Uninstall');
  }

  /**
   * Checks for available updates.
   *
   * Assumes that a user with the appropriate access is logged in.
   */
  protected function checkForUpdates(): void {
    $this->visit('/admin/reports/updates');
    $this->getMink()->getSession()->getPage()->clickLink('Check manually');
    $this->waitForBatchJob();
  }

  /**
   * Waits for an active batch job to finish.
   */
  protected function waitForBatchJob(): void {
    $refresh = $this->getMink()
      ->getSession()
      ->getPage()
      ->find('css', 'meta[http-equiv="Refresh"], meta[http-equiv="refresh"]');

    if ($refresh) {
      // Parse the content attribute of the meta tag for the format:
      // "[delay]: URL=[page_to_redirect_to]".
      if (preg_match('/\d+;\s*URL=\'?(?<url>[^\']*)/i', $refresh->getAttribute('content'), $match)) {
        $url = Html::decodeEntities($match['url']);
        $this->visit($url);
        $this->waitForBatchJob();
      }
    }
  }

  /**
   * Asserts the status report does not have any readiness errors or warnings.
   */
  protected function assertStatusReportChecksSuccessful(): void {
    $this->visit('/admin/reports/status');
    $mink = $this->getMink();
    $page = $mink->getSession()->getPage();
    $page->clickLink('Rerun readiness checks');

    $readiness_check_summaries = $page->findAll('css', '*:contains("Update readiness checks")');
    // There should always either be the summary section indicating the site is
    // ready for automatic updates or the error or warning sections.
    $this->assertNotEmpty($readiness_check_summaries);
    $ready_text_found = FALSE;
    $status_checks_text = '';
    foreach ($readiness_check_summaries as $readiness_check_summary) {
      $parent_element = $readiness_check_summary->getParent();
      if (str_contains($parent_element->getText(), 'Your site is ready for automatic updates.')) {
        $ready_text_found = TRUE;
        continue;
      }
      $description_list = $parent_element->find('css', 'ul');
      $this->assertNotEmpty($description_list);
      $status_checks_text .= "\n" . $description_list->getText();
    }
    $this->assertSame('', $status_checks_text);
    $this->assertTrue($ready_text_found);
  }

}
