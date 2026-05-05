<?php

namespace Drupal\easy_email\Service;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use Drupal\easy_email\Entity\EasyEmailInterface;
use Drupal\easy_email\Event\EasyEmailEvent;
use Drupal\easy_email\Event\EasyEmailEvents;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EmailAttachmentEvaluator implements EmailAttachmentEvaluatorInterface {

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * @var \Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser
   */
  protected $mimeTypeGuesser;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * Constructs the EmailAttachmentEvaluator
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   * @param \Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser $mimeTypeGuesser
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\file\FileRepositoryInterface $fileRepository
   */
  public function __construct(EventDispatcherInterface $eventDispatcher, FileSystemInterface $fileSystem, MimeTypeGuesser $mimeTypeGuesser, LoggerInterface $logger, ConfigFactoryInterface $configFactory, FileRepositoryInterface $fileRepository) {
    $this->fileSystem = $fileSystem;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->eventDispatcher = $eventDispatcher;
    $this->logger = $logger;
    $this->configFactory = $configFactory;
    $this->fileRepository = $fileRepository;
  }

  /**
   * @inheritDoc
   */
  public function evaluateAttachments(EasyEmailInterface $email, $save_attachments_to = FALSE) {
    $this->eventDispatcher->dispatch(new EasyEmailEvent($email), EasyEmailEvents::EMAIL_PREATTACHMENTEVAL);
    $files = $email->getEvaluatedAttachments();

    // If save attachments has been enabled, check for any programmatically added files and save them.
    if (!empty($save_attachments_to) && !empty($files)) {
      foreach ($files as $i => $file) {
        $this->saveAttachment($email, $file->uri, $save_attachments_to);
        unset($files[$i]); // This will get re-added in the direct files below.
      }
    }

    // Files attached directly to email entity
    if ($email->hasField('attachment')) {
      $attachments = $email->getAttachments();
      if (!empty($attachments)) {
        foreach ($attachments as $attachment) {
          $realpath = $this->fileSystem->realpath($attachment->getFileUri());
          if (!file_exists($realpath)) {
            $this->logger->warning('Attachment not found: @attachment', ['@attachment' => $attachment->getFileUri()]);
            continue;
          }
          if (!$this->attachmentInAllowedPath($realpath)) {
            $this->logger->warning('Attachment not in allowed path: @attachment', ['@attachment' => $attachment->getFileUri()]);
            continue;
          }
          if (!$this->isAllowedFileType($attachment->getFilename(), $attachment->getMimeType())) {
            $this->logger->warning('Attachment file type not allowed: @attachment (@mime)', [
              '@attachment' => $attachment->getFileUri(),
              '@mime' => $attachment->getMimeType()
            ]);
            continue;
          }
          if (!$this->isAllowedFileSize($realpath)) {
            $this->logger->warning('Attachment file size too large: @attachment (@size bytes)', [
              '@attachment' => $attachment->getFileUri(),
              '@size' => filesize($realpath)
            ]);
            continue;
          }
          $file = [
            'filepath' => $attachment->getFileUri(),
            'filename' => $attachment->getFilename(),
            'filemime' => $attachment->getMimeType(),
          ];
         $files[] = $file;
        }
      }
    }

    // Dynamic Attachments
    if ($email->hasField('attachment_path')) {
      $attachment_paths = $email->getAttachmentPaths();
      if (!empty($attachment_paths)) {
        foreach ($attachment_paths as $path) {
          // Relative paths that start with '/' get messed up by the realpath call below.
          if (strpos($path, '/') === 0) {
            $path = substr($path, 1);
          }
          $realpath = $this->fileSystem->realpath($path);
          if (!file_exists($realpath)) {
            $this->logger->warning('Attachment not found: @attachment', ['@attachment' => $path]);
            continue;
          }
          if (!$this->attachmentInAllowedPath($realpath)) {
            $this->logger->warning('Attachment not in allowed path: @attachment', ['@attachment' => $path]);
            continue;
          }
          $mime_type = $this->mimeTypeGuesser->guessMimeType($path);
          if (!$this->isAllowedFileType($this->fileSystem->basename($path), $mime_type)) {
            $this->logger->warning('Attachment file type not allowed: @attachment (@mime)', [
              '@attachment' => $path,
              '@mime' => $mime_type
            ]);
            continue;
          }
          if (!$this->isAllowedFileSize($realpath)) {
            $this->logger->warning('Attachment file size too large: @attachment (@size bytes)', [
              '@attachment' => $path,
              '@size' => filesize($realpath)
            ]);
            continue;
          }

          if (!empty($save_attachments_to) && $email->hasField('attachment')) {
            $this->saveAttachment($email, $realpath, $save_attachments_to);
          }

          $file = [
            'filepath' => $path,
            'filename' => $this->fileSystem->basename($path),
            'filemime' => $mime_type,
          ];
          $files[] = $file;
        }
      }
    }

    $email->setEvaluatedAttachments($files);

    $this->eventDispatcher->dispatch(new EasyEmailEvent($email), EasyEmailEvents::EMAIL_ATTACHMENTEVAL);
  }

  /**
   * Evaluate whether an attachment is in the allowed path.
   *
   * @param string $path
   *   Path of the attachment.
   *
   * @return bool
   *   Whether or not the attachment is in the allowed path.
   */
  protected function attachmentInAllowedPath(string $path) {
    $allowed_paths = $this->configFactory->get('easy_email.settings')->get('allowed_attachment_paths');
    if (empty($allowed_paths)) {
      return FALSE;
    }

    // Resolve the full real path to prevent path traversal attacks
    $real_path = $this->fileSystem->realpath($path);
    if ($real_path === FALSE) {
      return FALSE;
    }

    foreach ($allowed_paths as $allowed_path) {
      $allowed_realpath = $this->fileSystem->realpath($allowed_path);
      if ($allowed_realpath === FALSE) {
        continue;
      }

      if ($this->pcreFnmatch($allowed_realpath, $real_path)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Validate whether a file type is allowed for attachments.
   *
   * @param string $filename
   *   The filename to check.
   * @param string $mime_type
   *   The MIME type to check.
   *
   * @return bool
   *   TRUE if the file type is allowed, FALSE otherwise.
   */
  protected function isAllowedFileType(string $filename, string $mime_type): bool {
    $config = $this->configFactory->get('easy_email.settings');
    $allowed_extensions = $config->get('allowed_attachment_extensions') ?: [];
    $blocked_extensions = $config->get('blocked_attachment_extensions') ?: [];
    $allowed_mime_types = $config->get('allowed_attachment_mime_types') ?: [];
    $blocked_mime_types = $config->get('blocked_attachment_mime_types') ?: [];

    // Get file extension
    $extension = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Check blocked extensions first (security priority)
    if (in_array($extension, $blocked_extensions, TRUE)) {
      return FALSE;
    }

    // Check blocked MIME types
    if (in_array($mime_type, $blocked_mime_types, TRUE)) {
      return FALSE;
    }

    // If allowed extensions are configured, file must be in the list
    if (!empty($allowed_extensions) && !in_array($extension, $allowed_extensions, TRUE)) {
      return FALSE;
    }

    // If allowed MIME types are configured, file must be in the list
    if (!empty($allowed_mime_types) && !in_array($mime_type, $allowed_mime_types, TRUE)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validate whether a file size is within allowed limits.
   *
   * @param string $file_path
   *   The file path to check.
   *
   * @return bool
   *   TRUE if the file size is allowed, FALSE otherwise.
   */
  protected function isAllowedFileSize(string $file_path): bool {
    $max_size_mb = $this->configFactory->get('easy_email.settings')->get('max_attachment_size');

    // If no limit is configured, allow any size
    if (empty($max_size_mb)) {
      return TRUE;
    }

    // Convert megabytes to bytes for comparison
    $max_size_bytes = $max_size_mb * 1048576;

    $file_size = filesize($file_path);
    return $file_size !== FALSE && $file_size <= $max_size_bytes;
  }


  /**
   * Helper function to replace fnmatch().
   *
   * @param string $pattern
   *   The pattern to match against.
   * @param string $string
   *   The string to evaluate.
   *
   * @return bool
   *   Whether or not the pattern matches the string.
   */
  protected function pcreFnmatch($pattern, $string) {
    // Period at start must be the same as pattern:
    if (strpos($string, '.') === 0 && strpos($pattern, '.') !== 0) {
      return FALSE;
    }

    $transforms = [
      '\*'   => '[^/]*',
      '\?'   => '.',
      '\[\!' => '[^',
      '\['   => '[',
      '\]'   => ']',
      '\.'   => '\.',
    ];
    $pattern = '#^' . strtr(preg_quote($pattern, '#'), $transforms) . '$#i';

    return (boolean) preg_match($pattern, $string);
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   * @param \Drupal\file\FileInterface $file
   */
  protected function saveAttachment(EasyEmailInterface $email, $source, $dest_directory) {
    $this->fileSystem->prepareDirectory($dest_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $file_entity = $this->fileRepository->writeData(file_get_contents($source), $dest_directory . '/' . $this->fileSystem->basename($source));
    $email->addAttachment($file_entity->id());
  }

}
