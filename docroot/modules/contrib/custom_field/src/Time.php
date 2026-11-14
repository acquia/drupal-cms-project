<?php

declare(strict_types=1);

namespace Drupal\custom_field;

/**
 * Time class representing the time of day.
 *
 * A modified copy of
 * https://git.drupalcode.org/project/time_field/-/blob/2.x/src/Time.php.
 */
final class Time {

  /**
   * Value that serves as internal 'empty' value indicator.
   *
   * @var int
   */
  public const EMPTY_VALUE = 86401;

  /**
   * Constructor.
   *
   * @param int $hour
   *   Time hour.
   * @param int $minute
   *   Time minute.
   * @param int $second
   *   Time seconds.
   */
  public function __construct(
    private readonly int $hour = 0,
    private readonly int $minute = 0,
    private readonly int $second = 0,
  ) {
    self::assertInRange($hour, 0, 23);
    self::assertInRange($minute, 0, 59);
    self::assertInRange($second, 0, 59);
  }

  /**
   * Format Time.
   *
   * @param string $format
   *   Format string.
   *
   * @return string
   *   Formatted time eg `12:30 AM`
   */
  public function format(string $format = 'h:i a'): string {
    $time = self::baseDateTime();
    $time->setTimestamp($time->getTimestamp() + $this->getTimestamp());
    return $time->format($format);
  }

  /**
   * Format for widget.
   *
   * @param bool $show_seconds
   *   (Optional) Whether to include the seconds in the output regardless of the
   *   current time value. Defaults to TRUE.
   *   This is to ensure the option to adjust seconds is not shown in the widget
   *   when we don't want it to.
   *
   * @return string
   *   Formatted time eg `23:12:00`
   */
  public function formatForWidget(bool $show_seconds = TRUE): string {
    $time = self::baseDateTime();
    $time->setTimestamp($time->getTimestamp() + $this->getTimestamp());
    // If we're showing seconds, include the seconds in the output.
    if ($show_seconds) {
      return $time->format('H:i:s');
    }
    // Otherwise, exclude the seconds in the output.
    return $time->format('H:i');
  }

  /**
   * Number of hours.
   *
   * @return int
   *   Number of hours
   */
  public function getHour(): int {
    return $this->hour;
  }

  /**
   * Number of minutes.
   *
   * @return int
   *   Number of minutes
   */
  public function getMinute(): int {
    return $this->minute;
  }

  /**
   * Number of seconds.
   *
   * @return int
   *   Number of seconds
   */
  public function getSecond(): int {
    return $this->second;
  }

  /**
   * Number of seconds passed through midnight.
   *
   * @return int
   *   Number of seconds passed through midnight
   */
  public function getTimestamp(): int {
    $value = $this->hour * 60 * 60;
    $value += $this->minute * 60;
    $value += $this->second;
    return $value;
  }

  /**
   * DateTime with attached time to it.
   *
   * @param \DateTime $dateTime
   *   Datetime to attach time to it.
   *
   * @return \DateTime
   *   Datetime with attached time
   */
  public function on(\DateTime $dateTime): \DateTime {
    $instance = new \DateTime();
    $instance->setTimestamp($dateTime->getTimestamp());
    $instance->setTime($this->getHour(), $this->getMinute(), $this->getSecond());
    return $instance;
  }

  /**
   * Creates Time object from a DateTime object.
   *
   * @param \DateTime $date_time
   *   The DateTime object to create the time for.
   *
   * @return \Drupal\custom_field\Time
   *   Time object created based upon the date time.
   */
  public static function createFromDateTime(\DateTime $date_time): Time {
    return new Time(
      (int) $date_time->format('H'),
      (int) $date_time->format('i'),
      (int) $date_time->format('s')
    );
  }

  /**
   * Create Time object based on html5 formatted string.
   *
   * @param string $string
   *   Time string eg `12:30:20` or `12:30`.
   *
   * @return \Drupal\custom_field\Time
   *   Time object created html5 formatted string
   */
  public static function createFromHtml5Format(string $string): Time {

    if ($string === '') {
      return new Time();
    }

    $inputs = explode(':', $string);
    if (count($inputs) === 2) {
      $inputs[] = 0;
    }

    [$hour, $minute, $seconds] = $inputs;
    return new Time((int) $hour, (int) $minute, (int) $seconds);
  }

  /**
   * Creates Time object from timestamp.
   *
   * @param int|string|null $timestamp
   *   Number of seconds passed through midnight must be between 0 and 86400.
   *
   * @return \Drupal\custom_field\Time|null
   *   Time object created based on timestamp
   */
  public static function createFromTimestamp(int|string|null $timestamp): ?Time {

    if (self::isEmpty($timestamp)) {
      return NULL;
    }

    $timestamp = (int) $timestamp;
    self::assertInRange($timestamp, 0, 86400);
    $time = self::baseDateTime();
    $time->setTimestamp($time->getTimestamp() + $timestamp);
    return self::createFromDateTime($time);
  }

  /**
   * Check if given value is to be considered 'empty'.
   *
   * @params int|string|null $value
   *   The value to check.
   *
   * @return bool
   *   TRUE if the value is 'empty'.
   */
  public static function isEmpty(int|string|null $value): bool {

    if (is_null($value) || $value === '') {
      return TRUE;
    }

    return (int) $value === self::EMPTY_VALUE;
  }

  /**
   * Asserts that given value is between certain range.
   *
   * @param int $value
   *   Value to check.
   * @param int $from
   *   Lower bound of the assertion.
   * @param int $to
   *   Higher bound of the assertion.
   */
  private static function assertInRange(int $value, int $from, int $to): void {
    if ($value < $from || $value > $to) {
      throw new \InvalidArgumentException('Provided value is out of range.');
    }
  }

  /**
   * Base datetime object time functions on it.
   *
   * @return \DateTime
   *   Base datetime object to use time on it
   */
  private static function baseDateTime(): \DateTime {
    return new \DateTime('2012-01-01 00:00:00');
  }

}
