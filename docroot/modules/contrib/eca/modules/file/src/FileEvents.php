<?php

namespace Drupal\eca_file;

/**
 * Defines events provided by the ECA File module.
 */
final class FileEvents {

  /**
   * Dispatches when an ECA File download begins.
   *
   * @Event
   *
   * @var string
   */
  public const DOWNLOAD = 'eca_file.download';

}
