<?php

namespace Drupal\Tests\token_or\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Token Or module.
 *
 * @group token_or
 * @requires module token
 */
class TokenOrTest extends KernelTestBase {

  /**
   * The string replacement value for these tests.
   *
   * @var string
   */
  protected static $stringReplacement = 'baz';

  /**
   * Token service.
   *
   * @var \Drupal\token\Token
   */
  protected $tokenService;

  /**
   * Token replacement methods.
   *
   * @var string[]
   */
  protected $renderMethods = [
    'replace',
    'replacePlain',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'token',
    'token_or',
    'token_or_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tokenService = \Drupal::service('token');
  }

  /**
   * Tests the basic functionality.
   */
  public function testTokenReplacement() {
    $clear_option = ['clear' => TRUE];

    foreach ($this->renderMethods as $method) {
      $value = $this->tokenService->$method('[token_or:test|token_or:test2]');
      $this->assertEquals('test', $value);

      $value = $this->tokenService->$method('[token_or:empty|token_or:empty2]');
      $this->assertEquals('[token_or:empty|token_or:empty2]', $value);

      $value = $this->tokenService->$method('[token_or:empty|token_or:empty2]', [], ['clear' => FALSE]);
      $this->assertEquals('[token_or:empty|token_or:empty2]', $value);

      $value = $this->tokenService->$method('[token_or:empty|token_or:empty2]', [], $clear_option);
      $this->assertEmpty($value);

      $value = $this->tokenService->$method('[token_or:empty|token_or:empty2]/[token_or:test]');
      $this->assertEquals('[token_or:empty|token_or:empty2]/test', $value);

      $value = $this->tokenService->$method('[token_or:empty|token_or:empty2]/[token_or:test]', [], $clear_option);
      $this->assertEquals('/test', $value);

      $value = $this->tokenService->$method('[token_or:empty|token_or:empty2]/[token_or:empty|token_or:test]/[token_or:test2]');
      $this->assertEquals('[token_or:empty|token_or:empty2]/test/test2', $value);

      $value = $this->tokenService->$method('[token_or:empty|token_or:empty2]/[token_or:empty|token_or:test]/[token_or:test2]', [], $clear_option);
      $this->assertEquals('/test/test2', $value);

      $value = $this->tokenService->$method('[token_or:empty|token_or:empty2]/[token_or:empty2|token_or:empty]/[token_or:test2]');
      $this->assertEquals('[token_or:empty|token_or:empty2]/[token_or:empty2|token_or:empty]/test2', $value);

      $value = $this->tokenService->$method('[token_or:empty|token_or:empty2]/[token_or:empty2|token_or:empty]/[token_or:test2]', [], $clear_option);
      $this->assertEquals('//test2', $value);

      $value = $this->tokenService->$method('[token_or:invalid|token_or:test]');
      $this->assertEquals('test', $value);

      $value = $this->tokenService->$method('[token_or:invalid|token_or:test]', [], ['clear' => TRUE]);
      $this->assertEquals('test', $value);
    }
  }

  /**
   * Tests the string replacement functionality.
   */
  public function testStringReplacement() {
    foreach ($this->renderMethods as $method) {
      $value = $this->tokenService->$method('[token_or:empty|"' . self::$stringReplacement . '"]');
      $this->assertEquals(self::$stringReplacement, $value);
    }
  }

  /**
   * Test the validation of the basic functionality.
   */
  public function testTokenValidation() {
    $invalid_tokens = $this->tokenService->getInvalidTokensByContext('[token_or:test|token_or:test2]', ['token_or']);
    $this->assertEmpty($invalid_tokens);
  }

  /**
   * Test the validation of the basic functionality.
   */
  public function testStringValidation() {
    $invalid_tokens = $this->tokenService->getInvalidTokensByContext('[token_or:test|"' . self::$stringReplacement . '"]', ['token_or']);
    $this->assertEmpty($invalid_tokens);
  }

  /**
   * Tests the multiple token functionality.
   */
  public function testMultipleTokens() {
    foreach ($this->renderMethods as $method) {
      $value = $this->tokenService->$method('[token_or:test] [token_or:test|token_or:test2]');
      $this->assertEquals('test test', $value);
    }
  }

  /**
   * Tests the null replacement functionality.
   */
  public function testNullReplacement() {
    // ReplacePlain require a string, so just test replace method.
    $value = $this->tokenService->replace(NULL);
    $this->assertEquals(NULL, $value);
  }

  /**
   * Tests the empty replacement functionality.
   */
  public function testEmptyReplacement() {
    foreach ($this->renderMethods as $method) {
      $value = $this->tokenService->$method('');
      $this->assertEquals('', $value);
    }
  }

  /**
   * Tests that scan() returns strings.
   */
  public function testScanReturnsStrings() {
    $value = $this->tokenService->scan('/[node:field_dummy]/dummy?type:[node:field_test|node:field_dummy]');
    $this->assertEquals([
      'node' => [
        'field_test' => '[node:field_test]',
        'field_dummy' => '[node:field_dummy]',
      ],
    ], $value);
  }

}
