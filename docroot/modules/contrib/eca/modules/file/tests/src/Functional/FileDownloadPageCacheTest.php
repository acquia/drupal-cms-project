<?php

declare(strict_types=1);

namespace Drupal\Tests\eca_file\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that ECA-controlled private file downloads are not page-cached.
 *
 * Without the fix, core's Internal Page Cache stores the anonymous 403
 * response produced when an ECA file:download model forbids access. The 403 is
 * cached by URL with no ECA cache tag, so flipping the model to allowed keeps
 * serving the stale 403 until a full cache clear. The eca_file FileHooks
 * implementation triggers core's page cache kill switch whenever an ECA
 * file:download event participates, which makes the response policy deny
 * storage in the Internal Page Cache and prevents this.
 */
#[Group('eca')]
#[Group('eca_file')]
#[RunTestsInSeparateProcesses]
class FileDownloadPageCacheTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'page_cache',
    'eca',
    'eca_access',
    'eca_file',
    'modeler_api',
  ];

  /**
   * The relative download path for the test file.
   *
   * @var string
   */
  protected string $downloadPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable and make the Internal Page Cache store 4xx responses, which is the
    // default behavior and what triggers the reported bug for anonymous users.
    $this->config('system.performance')
      ->set('cache.page.max_age', 3600)
      ->save();

    $file = $this->createPrivateFile();
    $this->downloadPath = '/system/files/' . $file->getFilename();
  }

  /**
   * Creates a permanent private file entity with real contents.
   *
   * @return \Drupal\file\FileInterface
   *   The saved file entity.
   */
  protected function createPrivateFile(): FileInterface {
    $uri = 'private://eca-download.txt';
    file_put_contents($uri, 'eca-private-contents');
    $file = File::create([
      'uid' => 1,
      'filename' => 'eca-download.txt',
      'uri' => $uri,
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Builds and saves an ECA model reacting on file:download.
   *
   * @param array $action_configuration
   *   The configuration for the set file download access result action.
   */
  protected function createEcaModel(array $action_configuration): void {
    Eca::load('file_download')?->delete();
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'file_download',
      'label' => 'ECA file download',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'file_download' => [
          'plugin' => 'file:download',
          'label' => 'File download',
          'configuration' => [],
          'successors' => [
            ['id' => 'set_result', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_result' => [
          'plugin' => 'eca_access_set_file_download_result',
          'label' => 'Set file download access result',
          'configuration' => $action_configuration,
          'successors' => [],
        ],
      ],
    ];
    Eca::create($eca_config_values)->save();
  }

  /**
   * Tests that the ECA-controlled 403 is never served from the page cache.
   */
  public function testForbiddenDownloadIsNotPageCached(): void {
    // Forbid access through ECA.
    $this->createEcaModel([
      'access_result' => 'forbidden',
      'headers' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
    ]);

    $assert = $this->assertSession();

    // First anonymous request: access is forbidden. The kill switch makes the
    // response uncacheable, so the page cache must not store it.
    $this->drupalGet($this->downloadPath);
    $assert->statusCodeEquals(403);
    $this->assertResponseNotPageCacheable();

    // Second anonymous request must run the hook again (not a cache HIT).
    $this->drupalGet($this->downloadPath);
    $assert->statusCodeEquals(403);
    $this->assertResponseNotPageCacheable();

    // Flip the model to allowed. Without the fix, a cached 403 would still be
    // served. With the fix, the live decision now allows the download.
    $this->createEcaModel([
      'access_result' => 'allowed',
      'headers' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
    ]);

    $this->drupalGet($this->downloadPath);
    $assert->statusCodeEquals(200);
    $assert->responseContains('eca-private-contents');
  }

  /**
   * Asserts that the current response was not served from the page cache.
   *
   * The kill switch makes the response policy deny storage, so the Internal
   * Page Cache never stores the ECA-controlled response. Consequently the
   * X-Drupal-Cache header must never report a HIT for these requests.
   */
  protected function assertResponseNotPageCacheable(): void {
    $cache_header = $this->getSession()->getResponseHeader('X-Drupal-Cache');
    if ($cache_header !== NULL) {
      $this->assertNotSame('HIT', $cache_header, 'The ECA-controlled file download response must never be served from the Internal Page Cache.');
    }
  }

}
