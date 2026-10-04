<?php

namespace Drupal\Tests\eca_file\Kernel;

use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_file\Hook\FileHooks;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests that an ECA file:download triggers the page cache kill switch.
 *
 * Core's \Drupal\page_cache\StackMiddleware\PageCache::storeResponse() ignores
 * the no-cache/no-store Cache-Control directives and only refuses to store a
 * response when the response policy returns DENY. The KillSwitch policy returns
 * DENY once triggered, which is the supported way to keep an ECA-controlled
 * file download response (e.g. an anonymous 403) out of the Internal Page
 * Cache. This test asserts FileHooks triggers that kill switch when, and only
 * when, an ECA file:download event participates.
 */
#[Group('eca')]
#[Group('eca_file')]
#[RunTestsInSeparateProcesses]
class FileDownloadKillSwitchTest extends KernelTestBase {

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
   * The FileHooks class is instantiated directly with the relevant services,
   * mirroring FileDownloadEventTest, so the side effect on the kill switch can
   * be observed in isolation.
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
   * Whether the page cache kill switch has been triggered.
   *
   * KillSwitch exposes no public getter; its effect is observable only through
   * the ResponsePolicyInterface::check() contract, which returns
   * KillSwitch::DENY once triggered and NULL otherwise.
   *
   * @return bool
   *   TRUE if the kill switch denies page caching, FALSE otherwise.
   */
  protected function killSwitchTriggered(): bool {
    $kill_switch = $this->container->get('page_cache_kill_switch');
    $verdict = $kill_switch->check(new Response(), Request::create('/system/files/eca-download.txt'));
    return $verdict === KillSwitch::DENY;
  }

  /**
   * Tests that a participating ECA file:download triggers the kill switch.
   */
  public function testKillSwitchTriggeredWhenEventParticipates(): void {
    $file = $this->createPrivateFile();
    $this->createEcaModel([
      'access_result' => 'forbidden',
      'headers' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
    ]);

    // The kill switch starts untriggered (page caching is allowed).
    $this->assertFalse($this->killSwitchTriggered(), 'The page cache kill switch is not triggered before the hook runs.');

    $result = $this->invokeFileDownload($file->getFileUri());

    $this->assertSame(-1, $result, 'A forbidden ECA file:download denies access.');
    $this->assertTrue($this->killSwitchTriggered(), 'The page cache kill switch is triggered when an ECA file:download event participates.');
  }

  /**
   * Tests that the kill switch is not triggered without a matching file.
   */
  public function testKillSwitchNotTriggeredWithoutMatchingFile(): void {
    // A model exists, but the URI does not match any file entity, so the
    // file:download event never participates.
    $this->createEcaModel([
      'access_result' => 'forbidden',
      'headers' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
    ]);

    $result = $this->invokeFileDownload('private://does-not-exist.txt');

    $this->assertNull($result, 'No file entity matches the URI, so the hook defers.');
    $this->assertFalse($this->killSwitchTriggered(), 'The page cache kill switch is not triggered when no ECA file:download event participates.');
  }

}
