<?php

declare(strict_types=1);

namespace Drupal\Tests\eca_htmx\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\ECA\Condition\ConditionInterface;
use Drupal\eca\Token\TokenInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Kernel tests for the ECA HTMX request conditions and tokens.
 */
#[Group('eca')]
#[Group('eca_htmx')]
#[RunTestsInSeparateProcesses]
class HtmxRequestTest extends KernelTestBase {

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Condition|null
   */
  protected $conditionManager;

  /**
   * Token services.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_render',
    'eca_misc',
    'eca_endpoint',
    'eca_htmx',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(static::$modules);
    $this->conditionManager = \Drupal::service('plugin.manager.eca.condition');
    $this->tokenService = \Drupal::service('eca.token_services');
  }

  /**
   * Pushes a request with the given headers onto the request stack.
   *
   * @param array<string, string> $headers
   *   The headers to set on the request.
   */
  protected function pushRequest(array $headers): void {
    $request = Request::create('/some/path');
    $request->setSession(new Session());
    foreach ($headers as $name => $value) {
      $request->headers->set($name, $value);
    }
    /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
    $stack = $this->container->get('request_stack');
    $stack->pop();
    $stack->push($request);
  }

  /**
   * Determines whether the running core reads the htmx 4 request headers.
   *
   * Core 12 shipped htmx 4, which renamed the request header identifying the
   * triggering element from 'HX-Trigger' to 'HX-Source' and derives the
   * trigger name from that same header instead of 'HX-Trigger-Name'.
   * HtmxRequestInfo::trigger() keys off the same version comparison, so the
   * fixtures stay in sync with the header the production code actually reads.
   *
   * @return bool
   *   TRUE when the running core reads 'HX-Source'.
   */
  protected function isHtmxFour(): bool {
    return version_compare(\Drupal::VERSION, '12.0-dev', '>=');
  }

  /**
   * Returns the request headers that identify the triggering element.
   *
   * The trigger name resolves to 'my_name' on both core majors, so it stays
   * assertable everywhere. The trigger identifier itself does not: core 11
   * reports the element id 'my-button' while core 12 reports the CSS selector
   * 'button[name="my_name"]'.
   *
   * @return array<string, string>
   *   The headers to set on the request.
   */
  protected function triggerHeaders(): array {
    if ($this->isHtmxFour()) {
      return ['HX-Source' => 'button[name="my_name"]'];
    }
    return [
      'HX-Trigger' => 'my-button',
      'HX-Trigger-Name' => 'my_name',
    ];
  }

  /**
   * Creates an instance of the given condition plugin.
   *
   * @param string $id
   *   The condition plugin ID.
   * @param array $configuration
   *   The configuration.
   *
   * @return \Drupal\eca\Plugin\ECA\Condition\ConditionInterface
   *   The condition instance.
   */
  protected function condition(string $id, array $configuration): ConditionInterface {
    /** @var \Drupal\eca\Plugin\ECA\Condition\ConditionInterface $condition */
    $condition = $this->conditionManager->createInstance($id, $configuration);
    return $condition;
  }

  /**
   * Tests the "eca_htmx_is_request" condition.
   */
  public function testIsRequestCondition(): void {
    // No HTMX header: condition is FALSE.
    $this->pushRequest([]);
    $condition = $this->condition('eca_htmx_is_request', []);
    $this->assertFalse($condition->evaluate());

    // Negated without HTMX header: TRUE.
    $condition = $this->condition('eca_htmx_is_request', ['negate' => TRUE]);
    $this->assertTrue($condition->evaluate());

    // With the HX-Request header: condition is TRUE.
    $this->pushRequest(['HX-Request' => 'true']);
    $condition = $this->condition('eca_htmx_is_request', []);
    $this->assertTrue($condition->evaluate());
  }

  /**
   * Tests the "eca_htmx_header" condition.
   */
  public function testHeaderCondition(): void {
    $this->pushRequest([
      'HX-Request' => 'true',
      'HX-Target' => 'content',
      'HX-Current-URL' => 'https://example.com/page',
      'HX-Boosted' => 'true',
    ] + $this->triggerHeaders());

    // Target equals expected.
    $condition = $this->condition('eca_htmx_header', [
      'value' => 'target',
      'expected' => 'content',
    ]);
    $this->assertTrue($condition->evaluate());

    // Target does not equal a different expected value.
    $condition = $this->condition('eca_htmx_header', [
      'value' => 'target',
      'expected' => 'other',
    ]);
    $this->assertFalse($condition->evaluate());

    // Trigger name matches.
    $condition = $this->condition('eca_htmx_header', [
      'value' => 'trigger_name',
      'expected' => 'my_name',
    ]);
    $this->assertTrue($condition->evaluate());

    // Current URL contains a substring.
    $condition = $this->condition('eca_htmx_header', [
      'value' => 'current_url',
      'expected' => 'example.com',
      'operator' => 'contains',
    ]);
    $this->assertTrue($condition->evaluate());

    // Boosted boolean compares to "1".
    $condition = $this->condition('eca_htmx_header', [
      'value' => 'boosted',
      'expected' => '1',
    ]);
    $this->assertTrue($condition->evaluate());

    // is_request boolean compares to "1".
    $condition = $this->condition('eca_htmx_header', [
      'value' => 'is_request',
      'expected' => '1',
    ]);
    $this->assertTrue($condition->evaluate());
  }

  /**
   * Tests the [htmx:*] tokens.
   */
  public function testTokens(): void {
    $this->pushRequest([
      'HX-Request' => 'true',
      'HX-Target' => 'content',
      'HX-Current-URL' => 'https://example.com/page',
    ] + $this->triggerHeaders());

    // String request values resolve to their header value. The [htmx:trigger]
    // token is asserted in testTriggerToken(), because its value format is
    // core-version specific.
    $this->assertSame('content', (string) $this->tokenService->replaceClear('[htmx:target]'));
    $this->assertSame('my_name', (string) $this->tokenService->replaceClear('[htmx:trigger_name]'));
    $this->assertSame('https://example.com/page', (string) $this->tokenService->replaceClear('[htmx:current_url]'));

    // Boolean request values resolve to "1" when TRUE. When FALSE the value is
    // "0", which Drupal's token replacement clears to an empty string (the
    // usual falsy behavior); use the dedicated conditions for boolean checks.
    $this->assertSame('1', (string) $this->tokenService->replaceClear('[htmx:is_request]'));
    $this->assertSame('', (string) $this->tokenService->replaceClear('[htmx:boosted]'));
    $this->assertSame('', (string) $this->tokenService->replaceClear('[htmx:history_restore]'));
  }

  /**
   * Tests the [htmx:trigger] token on both core majors.
   *
   * This lives in its own test method because the value format is
   * core-version specific, which would otherwise force every other assertion
   * in testTokens() to be skipped alongside it.
   *
   * Issue #3590437 decided to let the value differ rather than parse the id
   * back out of the core 12 selector. Drupal builds that selector from the
   * first available of name, data-drupal-selector and id, so an element with
   * both a name and an id never exposes its id at all, and Drupal form buttons
   * almost always have a name. Both formats are asserted here so the decision
   * is pinned rather than merely documented.
   *
   * @see \Drupal\eca_htmx\HtmxRequestInfo::trigger()
   */
  public function testTriggerToken(): void {
    if ($this->isHtmxFour()) {
      $this->pushRequest([
        'HX-Request' => 'true',
        'HX-Source' => 'button[name="my_name"]',
      ]);
      $this->assertSame(
        'button[name="my_name"]',
        (string) $this->tokenService->replaceClear('[htmx:trigger]'),
        'On core 12 the trigger token carries the CSS selector that htmx 4 sends.',
      );
      return;
    }

    $this->pushRequest([
      'HX-Request' => 'true',
    ] + $this->triggerHeaders());

    $this->assertSame(
      'my-button',
      (string) $this->tokenService->replaceClear('[htmx:trigger]'),
      'On core 11 the trigger token carries the element id.',
    );
  }

  /**
   * Tests the [htmx:source] token.
   *
   * On core 12 the token exposes the raw selector. On core 11 the header is
   * deliberately set anyway and the token must still resolve to an empty
   * string: that proves the version guard actively suppresses the value rather
   * than the assertion passing only because no header happened to be present.
   *
   * @see \Drupal\eca_htmx\HtmxRequestInfo::source()
   */
  public function testSourceToken(): void {
    $this->pushRequest([
      'HX-Request' => 'true',
      'HX-Source' => 'button[name="my_name"]',
    ]);

    if ($this->isHtmxFour()) {
      $this->assertSame(
        'button[name="my_name"]',
        (string) $this->tokenService->replaceClear('[htmx:source]'),
        'On core 12 the source token carries the HX-Source selector.',
      );
      return;
    }

    $this->assertSame(
      '',
      (string) $this->tokenService->replaceClear('[htmx:source]'),
      'On core 11 the source token must stay empty even when an HX-Source header is present, because htmx 3 never sends one and a proxy-supplied value must not be trusted as if it did.',
    );
  }

  /**
   * Tests the [htmx:request_type] token.
   *
   * Guarded the same way as testSourceToken(): the header is set on both
   * majors, so the core 11 assertion is meaningful rather than vacuous.
   *
   * @see \Drupal\eca_htmx\HtmxRequestInfo::requestType()
   */
  public function testRequestTypeToken(): void {
    $this->pushRequest([
      'HX-Request' => 'true',
      'HX-Request-Type' => 'partial',
    ]);

    if ($this->isHtmxFour()) {
      $this->assertSame(
        'partial',
        (string) $this->tokenService->replaceClear('[htmx:request_type]'),
        'On core 12 the request type token carries the HX-Request-Type value.',
      );

      $this->pushRequest([
        'HX-Request' => 'true',
        'HX-Request-Type' => 'full',
      ]);
      $this->assertSame(
        'full',
        (string) $this->tokenService->replaceClear('[htmx:request_type]'),
        'Both documented values must pass through unchanged.',
      );
      return;
    }

    $this->assertSame(
      '',
      (string) $this->tokenService->replaceClear('[htmx:request_type]'),
      'On core 11 the request type token must stay empty even when the header is present.',
    );
  }

  /**
   * Tests the "source" and "request_type" values of the header condition.
   *
   * Mirrors the token tests: on core 11 the headers are set but the condition
   * must compare against an empty string, so a regression that dropped the
   * version guard would fail here rather than pass silently.
   */
  public function testSourceAndRequestTypeCondition(): void {
    $this->pushRequest([
      'HX-Request' => 'true',
      'HX-Source' => 'button[name="my_name"]',
      'HX-Request-Type' => 'partial',
    ]);

    $expectedSource = $this->isHtmxFour() ? 'button[name="my_name"]' : '';
    $expectedType = $this->isHtmxFour() ? 'partial' : '';

    $condition = $this->condition('eca_htmx_header', [
      'value' => 'source',
      'expected' => $expectedSource,
    ]);
    $this->assertTrue($condition->evaluate(), 'The source value must compare equal to what the running core exposes.');

    $condition = $this->condition('eca_htmx_header', [
      'value' => 'request_type',
      'expected' => $expectedType,
    ]);
    $this->assertTrue($condition->evaluate(), 'The request type value must compare equal to what the running core exposes.');

    // A value the running core cannot produce must not match either way.
    $condition = $this->condition('eca_htmx_header', [
      'value' => 'source',
      'expected' => 'button#never-sent',
    ]);
    $this->assertFalse($condition->evaluate(), 'A selector that was never sent must not match.');
  }

  /**
   * Tests that the trigger name is stable across both core majors.
   *
   * This is the value the change record recommends models migrate to, so it
   * gets an assertion of its own rather than only appearing incidentally in
   * testTokens().
   */
  public function testTriggerNameIsStableAcrossMajors(): void {
    $this->pushRequest([
      'HX-Request' => 'true',
    ] + $this->triggerHeaders());

    $this->assertSame(
      'my_name',
      (string) $this->tokenService->replaceClear('[htmx:trigger_name]'),
      'The trigger name resolves to the same value on both core majors.',
    );

    $condition = $this->condition('eca_htmx_header', [
      'value' => 'trigger_name',
      'expected' => 'my_name',
    ]);
    $this->assertTrue($condition->evaluate());
  }

}
