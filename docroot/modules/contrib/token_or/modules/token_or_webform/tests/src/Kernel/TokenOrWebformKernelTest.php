<?php

namespace Drupal\Tests\token_or_webform\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Token Or module.
 *
 * @group token_or_webform
 * @requires module token
 */
class TokenOrWebformKernelTest extends KernelTestBase {

  /**
   * Token service.
   *
   * @var \Drupal\token\Token
   */
  protected $tokenService;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'webform',
    'token',
    'user',
    'token_or',
    'token_or_webform',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tokenService = \Drupal::service('webform.token_manager');
  }

  /**
   * Test that current user token does fallback.
   */
  public function testToken() {
    $value = $this->tokenService->replace('[current-user:display-name|"foobar"]');
    $this->assertEquals('foobar', $value);
  }

  /**
   * Test that current user token array does fallback.
   */
  public function testArrayToken() {
    $value = $this->tokenService->replace(
      ['[current-user:email|"foobar"]', '[current-user:email|"foobar"]']
    );
    $this->assertEquals(['foobar', 'foobar'], $value);
  }

  /**
   * Test that pipes inside fallback strings survive webform's forced clear.
   */
  public function testPipesInStrings() {
    $value = $this->tokenService->replace('[current-user:display-name|"a | b"]');
    $this->assertEquals('a | b', $value);

    $value = $this->tokenService->replace('[foo:bar|"a | b"]');
    $this->assertEquals('a | b', $value);
  }

  /**
   * Test that MSO conditional comments survive webform's forced clear.
   */
  public function testMsoComments() {
    $text = '<!--[if mso | IE]><table><tr><td><![endif]-->test<!--[if mso | IE]></td></tr></table><![endif]-->';
    $value = $this->tokenService->replace($text);
    $this->assertEquals($text, $value);
  }

}
