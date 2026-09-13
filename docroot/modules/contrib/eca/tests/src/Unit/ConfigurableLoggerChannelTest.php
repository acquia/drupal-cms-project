<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\eca\ConfigurableLoggerChannel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the ConfigurableLoggerChannel service.
 *
 * The channel resolves its threshold once, in the constructor, from
 * "eca.settings.log_level". A cast of a missing value would silently produce
 * RfcLogLevel::EMERGENCY and discard every error, critical and alert ECA
 * emits, so absence has to fall back to RfcLogLevel::ERROR instead - the same
 * treatment the other reader of this setting already applies.
 *
 * @see \Drupal\eca\ConfigurableLoggerChannel::__construct()
 * @see \Drupal\eca_base\Plugin\Action\SetEcaLogLevel::create()
 */
#[Group('eca')]
#[Group('eca_core')]
class ConfigurableLoggerChannelTest extends EcaUnitTestBase {

  /**
   * The decorated logger channel receiving whatever passes the threshold.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $innerChannel;

  /**
   * Builds the channel under test for a given stored setting.
   *
   * @param int|null $log_level
   *   The value returned by "eca.settings.log_level", where NULL represents
   *   the setting being absent, exactly as ImmutableConfig::get() reports it.
   *
   * @return \Drupal\eca\ConfigurableLoggerChannel
   *   The channel under test.
   */
  protected function createChannel(?int $log_level): ConfigurableLoggerChannel {
    $config = $this->createStub(ImmutableConfig::class);
    $config->method('get')
      ->with('log_level')
      ->willReturn($log_level);
    $config_factory = $this->createStub(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('eca.settings')
      ->willReturn($config);

    return new ConfigurableLoggerChannel(
      'eca',
      $this->innerChannel,
      $config_factory,
      $this->createStub(ModuleHandlerInterface::class),
    );
  }

  /**
   * Reads the resolved threshold from the channel.
   *
   * @param \Drupal\eca\ConfigurableLoggerChannel $channel
   *   The channel under test.
   *
   * @return int
   *   The resolved maximum log level.
   *
   * @throws \ReflectionException
   */
  protected function resolvedLogLevel(ConfigurableLoggerChannel $channel): int {
    return $this->getPrivateProperty(ConfigurableLoggerChannel::class, 'maximumLogLevel')
      ->getValue($channel);
  }

  /**
   * Tests that an absent setting falls back to the error level.
   *
   * Without the fallback the cast yields 0, which is RfcLogLevel::EMERGENCY,
   * and the channel drops everything less severe than an emergency.
   *
   * @throws \ReflectionException
   */
  public function testAbsentSettingFallsBackToError(): void {
    $this->innerChannel = $this->createStub(LoggerChannelInterface::class);
    $channel = $this->createChannel(NULL);

    $this->assertSame(
      RfcLogLevel::ERROR,
      $this->resolvedLogLevel($channel),
      'An absent "log_level" setting resolves to the error level.'
    );
  }

  /**
   * Tests that an absent setting lets errors through to the decorated channel.
   *
   * This is the behavior the fallback exists to protect: the resolved
   * threshold only matters because log() gates on it.
   */
  public function testAbsentSettingForwardsErrors(): void {
    $inner = $this->createMock(LoggerChannelInterface::class);
    $inner->expects($this->once())
      ->method('log')
      ->with(RfcLogLevel::ERROR, 'Something went wrong.', []);
    $this->innerChannel = $inner;

    $this->createChannel(NULL)->log(RfcLogLevel::ERROR, 'Something went wrong.', []);
  }

  /**
   * Tests that an explicitly configured zero still means emergency.
   *
   * This is the one way the fallback could regress real behavior. A site that
   * deliberately stores "log_level: 0" has asked for emergencies only, and
   * that must keep suppressing errors. ImmutableConfig::get() returns NULL for
   * an absent value and 0 for an explicit one, so "??" tells them apart while
   * a cast would not.
   *
   * @throws \ReflectionException
   */
  public function testExplicitZeroRemainsEmergency(): void {
    $this->innerChannel = $this->createStub(LoggerChannelInterface::class);
    $channel = $this->createChannel(0);

    $this->assertSame(
      RfcLogLevel::EMERGENCY,
      $this->resolvedLogLevel($channel),
      'An explicitly configured zero resolves to the emergency level, not to the fallback.'
    );
  }

  /**
   * Tests that an explicitly configured zero keeps suppressing errors.
   */
  public function testExplicitZeroSuppressesErrors(): void {
    $inner = $this->createMock(LoggerChannelInterface::class);
    $inner->expects($this->never())->method('log');
    $this->innerChannel = $inner;

    $this->createChannel(0)->log(RfcLogLevel::ERROR, 'Something went wrong.', []);
  }

  /**
   * Tests that an explicitly configured zero still forwards emergencies.
   */
  public function testExplicitZeroForwardsEmergencies(): void {
    $inner = $this->createMock(LoggerChannelInterface::class);
    $inner->expects($this->once())
      ->method('log')
      ->with(RfcLogLevel::EMERGENCY, 'The house is on fire.', []);
    $this->innerChannel = $inner;

    $this->createChannel(0)->log(RfcLogLevel::EMERGENCY, 'The house is on fire.', []);
  }

  /**
   * Tests that an explicitly configured level is honored unchanged.
   *
   * @param int $log_level
   *   The stored log level.
   *
   * @throws \ReflectionException
   */
  #[DataProvider('providerExplicitLogLevels')]
  public function testExplicitLevelIsHonored(int $log_level): void {
    $this->innerChannel = $this->createStub(LoggerChannelInterface::class);
    $channel = $this->createChannel($log_level);

    $this->assertSame(
      $log_level,
      $this->resolvedLogLevel($channel),
      'An explicitly configured log level is used unchanged.'
    );
  }

  /**
   * Provides every explicitly configurable non-zero log level.
   *
   * @return array<string, array<int>>
   *   Test cases, keyed by the level name.
   */
  public static function providerExplicitLogLevels(): array {
    return [
      'alert' => [RfcLogLevel::ALERT],
      'critical' => [RfcLogLevel::CRITICAL],
      'error' => [RfcLogLevel::ERROR],
      'warning' => [RfcLogLevel::WARNING],
      'notice' => [RfcLogLevel::NOTICE],
      'info' => [RfcLogLevel::INFO],
      'debug' => [RfcLogLevel::DEBUG],
    ];
  }

}
