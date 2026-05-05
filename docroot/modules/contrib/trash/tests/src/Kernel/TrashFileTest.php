<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\trash\Exception\UnrestorableEntityException;
use Drupal\user\Entity\User;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Tests Trash integration with File entities.
 *
 * @group trash
 */
class TrashFileTest extends TrashKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'path',
    'path_alias',
  ];

  /**
   * The file system.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $this->installEntitySchema('path_alias');

    $user = User::create(['uid' => 1, 'name' => 'test_user']);
    $user->enforceIsNew();
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->enableEntityTypesForTrash(['file', 'path_alias']);

    $this->fileSystem = $this->container->get('file_system');
  }

  /**
   * Tests the full public-file lifecycle: trash, restore, retrash, purge.
   */
  public function testPublicFileLifecycle(): void {
    // Trash: file moves to .trashed/<hmac>/<basename>.
    $file = $this->createPublicFile('public://documents/report.pdf', 'REPORT');
    $original_uri = $file->getFileUri();

    $file->delete();

    $trashed = $this->loadTrashedEntity('file', $file->id());
    assert($trashed instanceof FileInterface);
    $this->assertMatchesRegularExpression(
      '#^public://documents/\.trashed/[a-zA-Z0-9_-]{16}/report\.pdf$#',
      $trashed->getFileUri(),
    );
    $this->assertFileDoesNotExist($original_uri);
    $this->assertFileExists($trashed->getFileUri());
    $this->assertStringEqualsFile($trashed->getFileUri(), 'REPORT');

    // Restore: file returns to the original URI and the HMAC dir is cleaned.
    $this->restoreEntity('file', $file->id());
    $restored = File::load($file->id());
    $this->assertEquals($original_uri, $restored->getFileUri());
    $this->assertFileExists($original_uri);
    $this->assertDirectoryDoesNotExist('public://documents/.trashed');

    // Retrash: same HMAC, same trashed URI.
    $restored->delete();
    $re_trashed = $this->loadTrashedEntity('file', $file->id());
    assert($re_trashed instanceof FileInterface);
    $this->assertEquals($trashed->getFileUri(), $re_trashed->getFileUri());
    $this->assertFileExists($re_trashed->getFileUri());

    // Purge: file and the empty directories are removed.
    $this->purgeEntity('file', $file->id());
    $this->assertFileDoesNotExist($re_trashed->getFileUri());
    $this->assertDirectoryDoesNotExist('public://documents/.trashed');
    $this->assertEmpty(File::load($file->id()));

    // Stream-root file: verify the trashed URI has no leading subdir segment.
    $root_file = $this->createPublicFile('public://top.txt', 'TOP');
    $root_file->delete();
    $trashed_root = $this->loadTrashedEntity('file', $root_file->id());
    assert($trashed_root instanceof FileInterface);
    $this->assertMatchesRegularExpression(
      '#^public://\.trashed/[a-zA-Z0-9_-]{16}/top\.txt$#',
      $trashed_root->getFileUri(),
    );

    $this->restoreEntity('file', $root_file->id());
    $this->assertEquals('public://top.txt', File::load($root_file->id())->getFileUri());
    $this->assertDirectoryDoesNotExist('public://.trashed');
  }

  /**
   * Tests the trash restoration with unexpected file states.
   */
  public function testUnexpectedTrashFileStates(): void {
    $logger = new BufferingLogger();
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->expects($this->atLeastOnce())
      ->method('get')
      ->with('trash')
      ->willReturn($logger);
    $this->container->set('logger.factory', $logger_factory);
    $file = $this->createPublicFile('public://report.txt', 'REPORT');
    $this->assertDirectoryDoesNotExist('public://.trashed');
    $file->delete();
    $this->assertDirectoryExists('public://.trashed');

    $trashed_file = $this->loadTrashedEntity('file', $file->id());
    assert($trashed_file instanceof FileInterface);

    // Create a stray file within the .trashed directory.
    file_put_contents('public://.trashed/extra-file.txt', 'extra');
    // Simulate a previously half-finished restoration by moving the trashed
    // file back to where it originally should be.
    $trashed_uri = $trashed_file->getFileUri();
    $this->fileSystem->move($trashed_uri, 'public://report.txt');

    // Restoration should still be possible, but with a warning in the logs.
    $this->restoreEntity('file', $file->id());
    $logs = $logger->cleanLogs();
    $this->assertCount(1, $logs);
    $this->assertEquals('warning', $logs[0][0]);
    $this->assertEquals('Adopted existing file at @original_uri (size matches entity metadata) as the restoration target; the trashed file at @trashed_uri no longer exists, likely from a previously interrupted restoration.', $logs[0][1]);
    $this->assertEquals([
      '@original_uri' => 'public://report.txt',
      '@trashed_uri' => $trashed_uri,
    ], $logs[0][2]);
    // Ensure the .trashed folder is only deleted if it's truly empty.
    $this->assertFileExists('public://.trashed/extra-file.txt');

    // Re-trash the restored file, then remove the trashed file too.
    $restored = $this->loadTrashedEntity('file', $file->id());
    assert($restored instanceof FileInterface);
    $restored->delete();
    $re_trashed = $this->loadTrashedEntity('file', $file->id());
    assert($re_trashed instanceof FileInterface);
    $this->fileSystem->delete($re_trashed->getFileUri());
    // With neither the trashed file nor the original URI present, the
    // restoration has nothing to adopt and must fail.
    $this->expectException(UnrestorableEntityException::class);
    $this->restoreEntity('file', $file->id());
  }

  /**
   * Tests that files on non-WRITE_VISIBLE wrappers are not relocated.
   */
  public function testNonPublicFileStaysInPlace(): void {
    $uri = 'temporary://transient.txt';
    file_put_contents($uri, 'TRANSIENT');
    $file = File::create(['uri' => $uri, 'uid' => 1]);
    $file->save();

    $file->delete();

    $trashed = $this->loadTrashedEntity('file', $file->id());
    assert($trashed instanceof FileInterface);
    $this->assertEquals($uri, $trashed->getFileUri());
    $this->assertFileExists($uri);
  }

  /**
   * Creates a file in the public stream with given contents.
   */
  protected function createPublicFile(string $uri, string $contents): FileInterface {
    $dir = $this->fileSystem->dirname($uri);
    $this->fileSystem->prepareDirectory(
      $dir,
      FileSystemInterface::CREATE_DIRECTORY,
    );
    file_put_contents($uri, $contents);
    $file = File::create(['uri' => $uri, 'uid' => 1]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

}
