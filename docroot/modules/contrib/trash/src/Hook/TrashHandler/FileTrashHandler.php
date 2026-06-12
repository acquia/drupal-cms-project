<?php

declare(strict_types=1);

namespace Drupal\trash\Hook\TrashHandler;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\FileNotExistsException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Drupal\trash\Exception\UnrestorableEntityException;
use Drupal\trash\Handler\DefaultTrashHandler;

/**
 * Provides a trash handler for the 'file' entity type.
 *
 * Files on WRITE_VISIBLE stream wrappers (public://, etc.) are moved to
 * `<scheme>://<original-dir>/.trashed/<hmac>/<basename>` on trash, where
 * `<hmac>` is a truncated HMAC-SHA256 of "<fid>:<original-uri>" keyed by the
 * site hash salt. The original URI is recovered by parsing the trashed URI.
 *
 * Files on non-public wrappers (private://, temporary://) are not moved: the
 * trash query alteration already hides them from loadByUri() lookups.
 */
class FileTrashHandler extends DefaultTrashHandler {

  /**
   * The subdirectory that holds trashed files.
   */
  protected const TRASH_SUBDIR = '.trashed';

  /**
   * Length (in base64 chars) of the HMAC used in trashed subdirectory names.
   *
   * 16 chars = 96 bits, well beyond what URL enumeration can brute-force.
   */
  protected const HMAC_LENGTH = 16;

  /**
   * Matches a trashed target path (scheme-relative).
   *
   * Group 1: original subdirectory (optional). Group 2: original basename.
   */
  protected const TRASHED_TARGET_REGEX = '#^(?:(.+)/)?\.trashed/[a-zA-Z0-9_-]{16}/([^/]+)$#';

  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function preTrashDelete(EntityInterface $entity): void {
    parent::preTrashDelete($entity);

    if (!$entity instanceof FileInterface) {
      return;
    }

    $source_uri = $entity->getFileUri();
    if ($source_uri === NULL || !$this->shouldRelocate($source_uri)) {
      return;
    }

    $target_uri = $this->buildTrashedUri($entity, $source_uri);
    $target_dir = $this->fileSystem->dirname($target_uri);

    if (!$this->fileSystem->prepareDirectory(
      $target_dir,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    )) {
      $this->loggerFactory->get('trash')->error('Could not prepare trash directory @dir for file @uri.', [
        '@dir' => $target_dir,
        '@uri' => $source_uri,
      ]);
      return;
    }

    try {
      $moved_uri = $this->fileSystem->move($source_uri, $target_uri, FileExists::Rename);
    }
    catch (FileException $e) {
      $this->loggerFactory->get('trash')->error('Could not move @uri to @target on trash: @message', [
        '@uri' => $source_uri,
        '@target' => $target_uri,
        '@message' => $e->getMessage(),
      ]);
      return;
    }

    $entity->setFileUri($moved_uri);
  }

  /**
   * {@inheritdoc}
   */
  public function postTrashDelete(EntityInterface $entity): void {
    parent::postTrashDelete($entity);

    if (!$entity instanceof FileInterface) {
      return;
    }

    $current_uri = $entity->getFileUri();
    if ($current_uri === NULL) {
      return;
    }

    $original_uri = $this->parseOriginalUri($current_uri);
    if ($original_uri === NULL) {
      return;
    }

    $this->notifyMove($entity, $original_uri);
  }

  /**
   * {@inheritdoc}
   */
  public function preTrashRestore(EntityInterface $entity): void {
    parent::preTrashRestore($entity);

    if (!$entity instanceof FileInterface) {
      return;
    }

    $current_uri = $entity->getFileUri();
    if ($current_uri === NULL) {
      return;
    }

    $original_uri = $this->parseOriginalUri($current_uri);
    if ($original_uri === NULL) {
      return;
    }

    $original_dir = $this->fileSystem->dirname($original_uri);
    if (!$this->fileSystem->prepareDirectory($original_dir, FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new UnrestorableEntityException(sprintf(
        'The original directory "%s" is not writable; cannot restore "%s".',
        $original_dir,
        $current_uri,
      ));
    }

    try {
      $restored_uri = $this->fileSystem->move($current_uri, $original_uri, FileExists::Rename);
    }
    catch (FileException $e) {
      // If a previous restoration was interrupted, the trashed file may have
      // already been moved back to its original URI. Adopt it only when the
      // size matches the file entity's metadata; otherwise a look-alike file
      // at that path could silently be claimed as the restored content.
      $can_adopt = $e instanceof FileNotExistsException
        && !file_exists($current_uri)
        && file_exists($original_uri)
        && $entity->getSize() !== NULL
        && (int) $entity->getSize() === @filesize($original_uri);
      if (!$can_adopt) {
        throw new UnrestorableEntityException($e->getMessage(), previous: $e);
      }
      $restored_uri = $original_uri;
      $this->loggerFactory->get('trash')->warning('Adopted existing file at @original_uri (size matches entity metadata) as the restoration target; the trashed file at @trashed_uri no longer exists, likely from a previously interrupted restoration.', [
        '@original_uri' => $original_uri,
        '@trashed_uri' => $current_uri,
      ]);
    }

    $entity->setFileUri($restored_uri);
    $this->removeEmptyTrashedDir($this->fileSystem->dirname($current_uri));
  }

  /**
   * {@inheritdoc}
   */
  public function postTrashRestore(EntityInterface $entity, int|string $deleted_timestamp): void {
    parent::postTrashRestore($entity, $deleted_timestamp);

    if (!$entity instanceof FileInterface) {
      return;
    }

    $current_uri = $entity->getFileUri();
    if ($current_uri === NULL || !$this->shouldRelocate($current_uri)) {
      return;
    }

    // HMAC is deterministic from the file ID and original URI.
    $trashed_uri = $this->buildTrashedUri($entity, $current_uri);
    $this->notifyMove($entity, $trashed_uri);
  }

  /**
   * Cleans up the HMAC subdirectory left behind after a purged trashed file.
   */
  #[Hook('file_delete')]
  public function onFileDelete(FileInterface $file): void {
    $uri = $file->getFileUri();
    if ($uri !== NULL && $this->parseOriginalUri($uri) !== NULL) {
      $this->removeEmptyTrashedDir($this->fileSystem->dirname($uri));
    }
  }

  /**
   * Determines whether a file at the given URI should be moved on trash.
   */
  protected function shouldRelocate(string $uri): bool {
    if ($this->parseOriginalUri($uri) !== NULL) {
      return FALSE;
    }

    $scheme = StreamWrapperManager::getScheme($uri);
    if ($scheme === FALSE) {
      return FALSE;
    }

    $class = $this->streamWrapperManager->getClass($scheme);
    if ($class === FALSE) {
      return FALSE;
    }
    return ($class::getType() & StreamWrapperInterface::WRITE_VISIBLE) === StreamWrapperInterface::WRITE_VISIBLE;
  }

  /**
   * Builds the trashed URI for a file about to be soft-deleted.
   */
  protected function buildTrashedUri(FileInterface $file, string $source_uri): string {
    $scheme = StreamWrapperManager::getScheme($source_uri);
    $target = StreamWrapperManager::getTarget($source_uri);
    $basename = basename($target);
    $subdir = $this->fileSystem->dirname($target);
    $subdir_prefix = ($subdir !== '.' && $subdir !== '') ? $subdir . '/' : '';

    $hmac = substr(
      Crypt::hmacBase64($file->id() . ':' . $source_uri, Settings::getHashSalt()),
      0,
      self::HMAC_LENGTH,
    );

    return $scheme . '://' . $subdir_prefix . self::TRASH_SUBDIR . '/' . $hmac . '/' . $basename;
  }

  /**
   * Recovers the original URI of a trashed file from its current URI.
   *
   * @return string|null
   *   The original URI, or NULL if the URI doesn't match the trashed pattern.
   */
  protected function parseOriginalUri(string $uri): ?string {
    $scheme = StreamWrapperManager::getScheme($uri);
    $target = StreamWrapperManager::getTarget($uri);
    if ($scheme === FALSE || $target === FALSE) {
      return NULL;
    }

    if (!preg_match(self::TRASHED_TARGET_REGEX, $target, $matches)) {
      return NULL;
    }

    $original_subdir = $matches[1] ?? '';
    $original_target = $original_subdir !== ''
      ? $original_subdir . '/' . $matches[2]
      : $matches[2];

    return $scheme . '://' . $original_target;
  }

  /**
   * Removes the HMAC subdirectory (and its .trashed parent) if empty.
   */
  protected function removeEmptyTrashedDir(string $hmac_dir): void {
    $real_hmac_dir = $this->fileSystem->realpath($hmac_dir);
    if ($real_hmac_dir !== FALSE && is_dir($real_hmac_dir)) {
      @rmdir($real_hmac_dir);
    }

    // The parent `.trashed/` is removed only if no other HMAC subdirectories
    // remain; rmdir silently fails otherwise.
    $parent = $this->fileSystem->dirname($hmac_dir);
    if (basename($parent) === self::TRASH_SUBDIR) {
      $real_parent = $this->fileSystem->realpath($parent);
      if ($real_parent !== FALSE && is_dir($real_parent)) {
        @rmdir($real_parent);
      }
    }
  }

  /**
   * Invokes hook_file_move so image style derivatives are flushed.
   */
  protected function notifyMove(FileInterface $file, string $old_uri): void {
    $source = clone $file;
    $source->setFileUri($old_uri);
    $this->moduleHandler->invokeAll('file_move', [$file, $source]);
  }

}
