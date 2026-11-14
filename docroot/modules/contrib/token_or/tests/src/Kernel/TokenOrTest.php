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
   * Tokens Pre Alter Service.
   *
   * @var \Drupal\token_or\TokenOrTokensPreAlter
   */
  protected $tokensPreAlterService;

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
    $this->tokensPreAlterService = \Drupal::service('token_or.tokens_pre_alter');
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
   * Tests that pipes inside quoted strings are not treated as separators.
   */
  public function testPipesInStrings() {
    foreach ($this->renderMethods as $method) {
      $value = $this->tokenService->$method('[token_or:empty|"My Title | My Company"]');
      $this->assertEquals('My Title | My Company', $value);

      $value = $this->tokenService->$method('[token_or:empty|"a | b"|token_or:test]');
      $this->assertEquals('a | b', $value);

      $value = $this->tokenService->$method('[token_or:test|"a | b"]');
      $this->assertEquals('test', $value);

      // Multibyte characters are not split apart.
      $value = $this->tokenService->$method('[token_or:empty|"Café | Zürich 東京"]');
      $this->assertEquals('Café | Zürich 東京', $value);
    }

    $invalid_tokens = $this->tokenService->getInvalidTokensByContext('[token_or:test|"a | b"]', ['token_or']);
    $this->assertEmpty($invalid_tokens);

    // Long input with many pipes and quotes must not exhaust PCRE limits.
    $text = '[' . str_repeat('a|"', 50000) . ']';
    $this->assertIsString($this->tokenService->replace($text));
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
   * Tests that every OR token in a string is validated, not just the first.
   */
  public function testMultipleOrTokenValidation() {
    $invalid_tokens = $this->tokenService->getInvalidTokensByContext('[token_or:test|"' . self::$stringReplacement . '"]/[token_or:invalid|token_or:test2]', ['token_or']);
    $this->assertEquals(['[token_or:invalid]'], $invalid_tokens);
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

  /**
   * Tests that scan() returns sub-tokens from every OR token.
   */
  public function testScanMultipleOrTokens() {
    $value = $this->tokenService->scan('[node:title|"x"]/[node:field_a|node:field_b]');
    $this->assertEquals([
      'node' => [
        'title' => '[node:title]',
        'field_a' => '[node:field_a]',
        'field_b' => '[node:field_b]',
      ],
    ], $value);
  }

  /**
   * Tests that scan() handles NULL and empty text.
   */
  public function testScanEmpty() {
    // PHPUnit only reports deprecations, so fail on them here.
    set_error_handler(function ($errno, $errstr) {
      throw new \ErrorException($errstr, 0, $errno);
    }, E_DEPRECATED);
    try {
      $this->assertSame([], $this->tokenService->scan(NULL));
      $this->assertSame([], $this->tokenService->scan(''));
    }
    finally {
      restore_error_handler();
    }
  }

  /**
   * Test that Microsoft Office Comments are not removed by token_or.
   */
  public function testMsoComments() {
    $original = '<tr>
    <td>
      <!--[if mso | IE]><table><tr><td><![endif]-->
      <div>
        <table>
          <tbody>
            <tr>
              <td>test</td>
            </tr>
          </tbody>
        </table>
      </div>
      <!--[if mso | IE]></td></tr></table><![endif]--></td>
    </tr>';

    $value = $original;
    $this->tokensPreAlterService->tokensPreAlter($value, [], ['clear' => TRUE]);
    $this->assertEquals($original, $value);
  }

  /**
   * Tests that a token whose pipes are all inside quotes is left alone.
   */
  public function testPipesOnlyInsideQuotes() {
    foreach ($this->renderMethods as $method) {
      foreach (['[token_or:test attr="a|b"]', '[widget:x attr="a|b"]', '[token_or:test "a|b]'] as $text) {
        $this->assertEquals($text, $this->tokenService->$method($text));
        $this->assertEquals($text, $this->tokenService->$method($text, [], ['clear' => TRUE]));
      }
      // An OR part with quoted pipes is not resolved again as an OR token.
      $value = $this->tokenService->$method('[token_or:empty|widget:x attr="a|b"|"' . self::$stringReplacement . '"]');
      $this->assertEquals(self::$stringReplacement, $value);
    }
  }

  /**
   * Tests that bracketed text with pipes that is not an OR token is kept.
   */
  public function testNonTokenPipesKept() {
    foreach ($this->renderMethods as $method) {
      foreach (['[if !mso | IE]', '[if lt mso 16 | IE]', '[a|b]'] as $text) {
        $value = $this->tokenService->$method($text, [], ['clear' => TRUE]);
        $this->assertEquals($text, $value);
      }
      // One real token is enough to treat it as an OR token.
      $value = $this->tokenService->$method('[token_or:test|not a token]', [], ['clear' => TRUE]);
      $this->assertEquals('test', $value);
      $value = $this->tokenService->$method('[if mso | IE] [token_or:empty|"' . self::$stringReplacement . '"]', [], ['clear' => TRUE]);
      $this->assertEquals('[if mso | IE] ' . self::$stringReplacement, $value);
    }
  }

}
