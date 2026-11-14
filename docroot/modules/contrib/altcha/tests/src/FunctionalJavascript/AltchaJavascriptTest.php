<?php

declare(strict_types=1);

namespace Drupal\Tests\altcha\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests ALTCHA javascript functionalities.
 *
 * @group altcha
 *
 * @dependencies captcha
 */
class AltchaJavascriptTest extends WebDriverTestBase {

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

    \Drupal::moduleHandler()->loadInclude('captcha', 'inc');
  }

  /**
   * Click the captcha checkbox element and wait for it to be validated.
   */
  public function testClickAltcha(): void {
    captcha_set_form_id_setting('user_login_form', 'altcha/ALTCHA');
    $this->drupalGet('user/login');

    $this->assertSession()
      ->elementExists('xpath', '//div[@data-state="unverified"]');

    $this->getSession()
      ->getPage()
      ->find('css', '.altcha-checkbox input')
      ->click();

    // Verify that the state of the element switched to verifying after click.
    $this->assertSession()
      ->elementExists('xpath', '//div[@data-state="verifying"]');
  }

}
