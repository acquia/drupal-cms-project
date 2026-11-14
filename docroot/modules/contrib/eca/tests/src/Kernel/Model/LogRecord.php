<?php

namespace Drupal\Tests\eca\Kernel\Model;

/**
 * Helper class to compare log records.
 */
class LogRecord {

  /**
   * Log severity.
   *
   * @var int
   */
  private int $severity;

  /**
   * Log channel.
   *
   * @var string
   */
  private string $channel;

  /**
   * Log message.
   *
   * @var string
   */
  private string $message;

  /**
   * Log arguments/context.
   *
   * @var array
   */
  private array $arguments;

  /**
   * Constructs a log record.
   *
   * @param int $severity
   *   The log severity.
   * @param string $channel
   *   The log channel.
   * @param string $message
   *   The log message.
   * @param array $arguments
   *   The list of log message arguments.
   */
  public function __construct(int $severity, string $channel, string $message, array $arguments = []) {
    $this->severity = $severity;
    $this->channel = $channel;
    $this->message = $message;
    $this->arguments = $arguments;
  }

  /**
   * Formats and cleans the log record.
   *
   * @param string $message
   *   The log message.
   * @param array $arguments
   *   The log message arguments.
   *
   * @return string
   *   The formatted message string.
   */
  public static function format(string $message, array $arguments = []): string {
    return strip_tags(strtr($message, $arguments));
  }

  /**
   * Compares the current log record to the given values.
   *
   * @param int $severity
   *   The log severity.
   * @param string $channel
   *   The log channel.
   * @param string $message
   *   The log message.
   * @param array $arguments
   *   The list of log message arguments.
   *
   * @return bool
   *   Returns TRUE, if all components equal the current log record, FALSE
   *   otherwise.
   */
  public function compare(int $severity, string $channel, string $message, array $arguments = []): bool {
    return $this->severity === $severity &&
      $this->channel === $channel &&
      $this->__toString() === self::format($message, $arguments);
  }

  /**
   * Return the formatted version of the current log record.
   *
   * @return string
   *   The formatted message string.
   */
  public function __toString(): string {
    return self::format($this->message, $this->arguments);
  }

}
