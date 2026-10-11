<?php

namespace Drupal\eca_file\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccessEventInterface;
use Drupal\file\FileInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a private file is downloaded.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_file\Event
 */
class FileDownload extends Event implements AccessEventInterface {

  /**
   * The file entity being downloaded.
   *
   * @var \Drupal\file\FileInterface
   */
  protected FileInterface $file;

  /**
   * The account that asks for access.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The access result.
   *
   * @var \Drupal\Core\Access\AccessResultInterface|null
   */
  protected ?AccessResultInterface $accessResult = NULL;

  /**
   * The response headers for the file download.
   *
   * @var string[]
   */
  protected ?array $headers;

  /**
   * Constructs a new FileDownload object.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity being downloaded.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account that asks for access.
   * @param string[] $headers
   *   The response headers for the file download.
   */
  public function __construct(FileInterface $file, AccountInterface $account, ?array $headers = NULL) {
    $this->file = $file;
    $this->account = $account;
    $this->headers = $headers;
  }

  /**
   * Get the file entity.
   *
   * @return \Drupal\file\FileInterface
   *   The file entity.
   */
  public function getFile(): FileInterface {
    return $this->file;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessResult(): ?AccessResultInterface {
    return $this->accessResult;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccessResult(AccessResultInterface $result): FileDownload {
    if ($this->accessResult === NULL) {
      $this->accessResult = $result;
    }
    else {
      $this->accessResult = $this->accessResult->orIf($result);
    }

    return $this;
  }

  /**
   * Gets the response headers for the file download.
   *
   * @return array<string, string>|null
   *   An associative array of HTTP headers where the key is the header name
   *   and the value is the corresponding header value, or NULL if none are set.
   */
  public function getHeaders(): ?array {
    return $this->headers;
  }

  /**
   * Sets the response headers for the file download.
   *
   * @param array<string, string> $headers
   *   An associative array of HTTP headers where each key is the header name
   *   and each value is the header value.
   *
   * @return $this
   */
  public function setHeaders(array $headers): FileDownload {
    $this->headers = $headers;
    return $this;
  }

}
