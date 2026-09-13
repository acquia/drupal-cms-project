<?php

namespace Drupal\Tests\eca_file\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_file\Hook\FileHooks;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the file download event and the related access result action.
 */
#[Group('eca')]
#[Group('eca_file')]
#[RunTestsInSeparateProcesses]
class FileDownloadEventTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'eca',
    'eca_access',
    'eca_file',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(static::$modules);
    // Provide a private file path so private:// files can be created.
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');

    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Creates a permanent private file entity.
   *
   * @return \Drupal\file\FileInterface
   *   The saved file entity.
   */
  protected function createPrivateFile(): FileInterface {
    $file = File::create([
      'uid' => 1,
      'filename' => 'eca-download.txt',
      'uri' => 'private://eca-download.txt',
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
   * Invokes hook_file_download() through the ECA File hook implementation.
   *
   * The FileHooks class is instantiated directly with the relevant services so
   * the precise return value (array, int or NULL) can be asserted, which is
   * not possible through ModuleHandler::invokeAll() because it merges results.
   *
   * @param string $uri
   *   The file URI.
   *
   * @return array|int|null
   *   The return value of the hook implementation.
   */
  protected function invokeFileDownload(string $uri): array|int|null {
    $hooks = new FileHooks(
      \Drupal::service('eca.trigger_event'),
      \Drupal::entityTypeManager(),
      \Drupal::service('current_user'),
      \Drupal::service('page_cache_kill_switch'),
    );
    return $hooks->fileDownload($uri);
  }

  /**
   * Tests that an allowed result with YAML headers returns the headers array.
   */
  public function testAllowedWithYamlHeaders(): void {
    $file = $this->createPrivateFile();
    $this->createEcaModel([
      'access_result' => 'allowed',
      'headers' => "Content-Disposition: 'attachment; filename=\"eca.txt\"'\nX-Eca: 'yes'",
      'use_yaml' => TRUE,
      'validate_yaml' => FALSE,
    ]);

    $result = $this->invokeFileDownload($file->getFileUri());
    $this->assertIsArray($result);
    // The ECA-provided custom headers must be present with their values.
    $this->assertSame('attachment; filename="eca.txt"', $result['Content-Disposition']);
    $this->assertSame('yes', $result['X-Eca']);
    // The file's default download headers are merged in as well, so a
    // non-overridden default such as Content-Type remains present.
    $this->assertArrayHasKey('Content-Type', $result);
  }

  /**
   * Tests that an allowed result with plain headers returns the headers array.
   */
  public function testAllowedWithPlainHeaders(): void {
    $file = $this->createPrivateFile();
    $this->createEcaModel([
      'access_result' => 'allowed',
      'headers' => "Content-Disposition: attachment; filename=\"eca.txt\"\nX-Eca: yes",
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
    ]);

    $result = $this->invokeFileDownload($file->getFileUri());
    $this->assertIsArray($result);
    // The ECA-provided custom headers must be present with their values.
    $this->assertSame('attachment; filename="eca.txt"', $result['Content-Disposition']);
    $this->assertSame('yes', $result['X-Eca']);
    // The file's default download headers are merged in as well, so a
    // non-overridden default such as Content-Type remains present.
    $this->assertArrayHasKey('Content-Type', $result);
  }

  /**
   * Tests that a forbidden result returns -1.
   */
  public function testForbidden(): void {
    $file = $this->createPrivateFile();
    $this->createEcaModel([
      'access_result' => 'forbidden',
      'headers' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
    ]);

    $result = $this->invokeFileDownload($file->getFileUri());
    $this->assertSame(-1, $result);
  }

  /**
   * Tests that an allowed result without headers returns the default headers.
   *
   * Core's \Drupal\system\FileDownloadController::download() only serves the
   * file when at least one hook_file_download() implementation returns a
   * non-empty headers array, so an allowed decision must return the file's
   * default download headers rather than NULL.
   */
  public function testAllowedWithoutHeaders(): void {
    $file = $this->createPrivateFile();
    $this->createEcaModel([
      'access_result' => 'allowed',
      'headers' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
    ]);

    $result = $this->invokeFileDownload($file->getFileUri());
    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertNotSame(-1, $result);
    $this->assertArrayHasKey('Content-Type', $result);
    $this->assertSame($file->getDownloadHeaders(), $result);
  }

  /**
   * Tests that a neutral result with stale headers does not return headers.
   */
  public function testNeutralWithStaleHeaders(): void {
    $file = $this->createPrivateFile();
    $this->createEcaModel([
      'access_result' => 'neutral',
      'headers' => "X-Stale: leftover",
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
    ]);

    $result = $this->invokeFileDownload($file->getFileUri());
    $this->assertNull($result);
    $this->assertNotSame(-1, $result);
  }

}
