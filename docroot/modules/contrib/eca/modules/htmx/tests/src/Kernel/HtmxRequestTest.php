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
      'HX-Trigger' => 'my-button',
      'HX-Trigger-Name' => 'my_name',
      'HX-Current-URL' => 'https://example.com/page',
      'HX-Boosted' => 'true',
    ]);

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
      'HX-Trigger' => 'my-button',
      'HX-Trigger-Name' => 'my_name',
      'HX-Current-URL' => 'https://example.com/page',
    ]);

    // String request values resolve to their header value.
    $this->assertSame('content', (string) $this->tokenService->replaceClear('[htmx:target]'));
    $this->assertSame('my-button', (string) $this->tokenService->replaceClear('[htmx:trigger]'));
    $this->assertSame('my_name', (string) $this->tokenService->replaceClear('[htmx:trigger_name]'));
    $this->assertSame('https://example.com/page', (string) $this->tokenService->replaceClear('[htmx:current_url]'));

    // Boolean request values resolve to "1" when TRUE. When FALSE the value is
    // "0", which Drupal's token replacement clears to an empty string (the
    // usual falsy behavior); use the dedicated conditions for boolean checks.
    $this->assertSame('1', (string) $this->tokenService->replaceClear('[htmx:is_request]'));
    $this->assertSame('', (string) $this->tokenService->replaceClear('[htmx:boosted]'));
    $this->assertSame('', (string) $this->tokenService->replaceClear('[htmx:history_restore]'));
  }

}
