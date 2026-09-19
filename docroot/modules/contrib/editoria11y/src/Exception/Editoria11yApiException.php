<?php

namespace Drupal\editoria11y\Exception;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A simple exception class to mark errors thrown by the editoria11y module.
 */
class Editoria11yApiException extends \Exception {

  use StringTranslationTrait;

  /**
   * Constructs an Editoria11yApiException.
   *
   * @param string $class
   *   The entity parent class.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface|null $loggerFactory
   *   The logger factory service.
   */
  public function __construct($class, ?LoggerChannelFactoryInterface $loggerFactory = NULL) {
    $message = sprintf('%s', $class);
    parent::__construct($message);

    // Log a warning if logger factory is provided.
    if ($loggerFactory) {
      $logger = $loggerFactory->get('editoria11y');
      $logger->warning($this->t('Warning from Editoria11y: @message', ['@message' => $message]));
    }
  }

}
