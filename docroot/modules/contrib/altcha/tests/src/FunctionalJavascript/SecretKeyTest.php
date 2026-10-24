<?php

declare(strict_types=1);

namespace Drupal\Tests\altcha\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\UserInterface;

/**
 * Tests for ajax secret key generation.
 *
 * @group altcha
 *
 * @dependencies captcha
 */
class SecretKeyTest extends WebDriverTestBase {

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * The ALTCHA secret hmac key.
   *
   * @var string
   */
  protected string $secretKey;

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['altcha', 'captcha'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user.
    $permissions = [
      'administer CAPTCHA settings',
      'administer altcha',
    ];

    $this->adminUser = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->adminUser);

    $this->secretKey = \Drupal::service('altcha.secret_manager')->getSecretKey();
  }

  /**
   * Test ALTCHA hmac key generation.
   */
  public function testSecretKeyGeneration(): void {
    $this->drupalGet('admin/config/people/captcha/altcha');
    $this->click('#edit-secret-key-regenerate');
    $this->assertSession()->assertExpectedAjaxRequest(1);
    $this->assertSession()
      ->pageTextContains('Secret key was successfully updated!');

    $this->assertNotEquals(\Drupal::service('altcha.secret_manager')
      ->getSecretKey(), $this->secretKey, 'The hmac key did not update.');
  }

}
