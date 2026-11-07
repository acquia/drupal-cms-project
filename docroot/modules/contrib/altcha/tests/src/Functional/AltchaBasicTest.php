<?php

namespace Drupal\Tests\altcha\Functional;

use Drupal\Component\Utility\Html;
use Drupal\altcha\Utility\AltchaI18nUtility;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\user\UserInterface;

/**
 * Test basic functionality for the ALTCHA module.
 *
 * @group altcha
 *
 * @dependencies captcha
 */
class AltchaBasicTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * A normal user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $normalUser;

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * The hmac key.
   *
   * @var string
   */
  protected string $secretKey;

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['altcha', 'captcha', 'language', 'user'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The ALTCHA widget xpath selector.
   *
   * @var string
   */
  protected string $altchaSelector = '//input[@name="captcha_token"]';

  /**
   * The default ALTCHA library, included with the module.
   *
   * @var string
   */
  protected string $defaultLibrary = 'assets/vendor/altcha/altcha.min.js';

  /**
   * The default ALTCHA i18n library, included with the module.
   *
   * @var string
   */
  protected string $defaultI18nLibrary = 'assets/vendor/altcha/i18n/all.min.js';

  /**
   * The ALTCHA i18n custom (Drupal interface translation) library.
   *
   * @var string
   */
  protected string $i18nCustomLibrary = 'altcha/js/custom-translation.behaviors.js';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::moduleHandler()->loadInclude('captcha', 'inc');

    // Create a normal user.
    $this->normalUser = $this->drupalCreateUser();

    // Create an admin user.
    $permissions = [
      'access administration pages',
      'administer site configuration',
      'administer CAPTCHA settings',
      'skip CAPTCHA',
      'administer permissions',
      'administer altcha',
    ];

    $this->adminUser = $this->drupalCreateUser($permissions);
  }

  /**
   * The hmac secret key.
   */
  protected function testInstallation(): void {
    $this->assertNotEmpty($this->secretKey);
    $this->assertEquals(64, strlen($this->secretKey));
  }

  /**
   * Test access to the administration page.
   */
  public function testAdminAccess(): void {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/config/people/captcha/altcha');
    $this->assertSession()->pageTextNotContains($this->t('Access denied'));

    $this->drupalLogout();
  }

  /**
   * Test the ALTCHA settings form.
   */
  public function testSettingsForm(): void {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/config/people/captcha/altcha');

    $this->drupalLogout();
  }

  /**
   * Testing the protection of the user login form.
   */
  public function testLoginForm(): void {
    // Validate login process.
    $this->drupalLogin($this->normalUser);
    $this->drupalLogout();

    $this->drupalGet('user/login');

    // ALTCHA should not be configured yet.
    $this->assertSession()->elementNotExists('xpath', $this->altchaSelector);

    // Enable 'altcha/ALTCHA' on login form.
    captcha_set_form_id_setting('user_login_form', 'altcha/ALTCHA');
    $result = captcha_get_form_id_setting('user_login_form');

    // ALTCHA should be configured.
    $this->assertNotNull($result, 'A configuration has been found for CAPTCHA point: user_login_form');
    $this->assertEquals($result->getCaptchaType(), 'altcha/ALTCHA', 'Altcha type has been configured for CAPTCHA point: user_login_form');

    // Test the sentinel API version.
    $this->config('altcha.settings')
      ->set('integration_type', 'sentinel_api')
      ->save();
    $this->config('altcha.settings')->set('sentinel_api_url', 'https://example.com')->save();
    $this->config('altcha.settings')->set('sentinel_api_key', 'key_hello')->save();
    $this->config('altcha.settings')->set('sentinel_api_secret', 'sec_test')->save();

    $options = [
      'query' => [
        'apiKey' => 'key_hello',
      ],
    ];

    $this->drupalGet('user/login');

    // An ALTCHA should exist with challenge url matching the configuration.
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);
    $this->assertSession()
      ->responseContains(Html::escape(Url::fromUri('https://example.com/v1/challenge', $options)
        ->toString()));

    // Test the API SAAS version.
    $this->config('altcha.settings')
      ->set('integration_type', 'saas_api')
      ->save();
    $this->config('altcha.settings')->set('saas_api_key', 'test')->save();
    $this->config('altcha.settings')->set('saas_api_region', 'eu')->save();
    $this->config('altcha.settings')->set('max_number', 20000)->save();

    $options = [
      'query' => [
        'apiKey' => 'test',
        'maxnumber' => 20000,
      ],
    ];

    $this->drupalGet('user/login');

    // An ALTCHA should exist with challenge url matching the configuration.
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);
    $this->assertSession()
      ->responseContains(Html::escape(Url::fromUri('https://eu.altcha.org/api/v1/challenge', $options)
        ->toString()));

    // Test the Self-hosted version.
    $this->config('altcha.settings')
      ->set('integration_type', 'self_hosted')
      ->save();
    \Drupal::service('altcha.secret_manager')->generateSecretKey();

    $this->drupalGet('user/login');
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);
    $this->assertSession()
      ->responseContains(Html::escape(Url::fromRoute('altcha.challenge')
        ->toString()));

    // Check auto verification attribute.
    $this->config('altcha.settings')
      ->set('auto_verification', 'onsubmit')
      ->save();
    $this->drupalGet('user/login');
    $element = $this->xpath('//altcha-widget[@auto="onsubmit"]');
    $this->assertNotEmpty($element, 'auto verification should be enabled and onsubmit.');

    // Check the maxnumber attribute.
    $this->config('altcha.settings')->set('max_number', 10000)->save();
    $this->drupalGet('user/login');
    $element = $this->xpath('//altcha-widget[@maxnumber="10000"]');
    $this->assertNotEmpty($element, 'maxnumber should be enabled and equal to 10000');

    // Validate that the login attempt fails.
    $edit['name'] = $this->normalUser->getAccountName();
    $edit['pass'] = $this->normalUser->getPassword();

    $this->drupalGet('user/login');
    $this->submitForm($edit, $this->t('Log in'));
    $this->assertSession()
      ->pageTextContains($this->t('The answer you entered for the CAPTCHA was not correct.'));

    // Make sure the user did not start a session.
    $this->assertFalse($this->drupalUserIsLoggedIn($this->normalUser));
  }

  /**
   * Tests if the library override works.
   *
   * By default, the module library should be added to an ALTCHA form.
   * When a library override is configured the override library should be added
   * to the form and not the default library.
   *
   * Test the 4 possible override methods:
   *  - CDN
   *  - Stream wrapper (file uri)
   *  - Path relative to drupal root
   *  - Path relative to server root
   */
  public function testLibraryOverrideUrl() {
    // Enable 'altcha/ALTCHA' on login form.
    captcha_set_form_id_setting('user_login_form', 'altcha/ALTCHA');
    $result = captcha_get_form_id_setting('user_login_form');

    // ALTCHA should be configured.
    $this->assertNotNull($result, 'A configuration has been found for CAPTCHA point: user_login_form');
    $this->assertEquals($result->getCaptchaType(), 'altcha/ALTCHA', 'Altcha type has been configured for CAPTCHA point: user_login_form');

    // Now go to the login page where the ALTCHA form will be rendered.
    $this->drupalGet('user/login');

    // An ALTCHA should exist.
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);

    // The default library should be loaded via script tag.
    $this->assertSession()->elementExists('xpath', "//head//script[contains(@src, '{$this->defaultLibrary}')]");

    // 1. Library override CDN url.
    $this->validateLibraryOverride(
      'https://cdn.example.com/js/altcha.min.js',
      'https://cdn.example.com/js/altcha.min.js',
    );

    // 2. Library override public file uri.
    $this->validateLibraryOverride(
      'public://libraries/altcha/js/altcha-public-fs-library.min.js',
      'files/libraries/altcha/js/altcha-public-fs-library.min.js',
    );

    // 3. Library override url relative to the drupal web root.
    $this->validateLibraryOverride(
      'libraries/js/altcha-relative-path-library.min.js',
      'libraries/js/altcha-relative-path-library.min.js',
    );

    // 4. Library override url absolute to the server root.
    $this->validateLibraryOverride(
      \Drupal::root() . '/libraries/js/altcha-absolute-path-library.min.js',
      'libraries/js/altcha-absolute-path-library.min.js',
    );
  }

  /**
   * Tests if the i18n library override works.
   *
   * By default, when the ALTCHA i18n JS library method is used and the current
   * language is not English, the module's own ALTCHA i18n library should be
   * added to an ALTCHA form. When an i18n library override is configured, the
   * override library should be added to the form and not the default i18n
   * library.
   *
   * Test the 4 possible override methods:
   *  - CDN
   *  - Stream wrapper (file uri)
   *  - Path relative to drupal root
   *  - Path relative to server root
   */
  public function testI18nLibraryOverrideUrl() {
    // Enable 'altcha/ALTCHA' on login form.
    captcha_set_form_id_setting('user_login_form', 'altcha/ALTCHA');
    $result = captcha_get_form_id_setting('user_login_form');

    // ALTCHA should be configured.
    $this->assertNotNull($result, 'A configuration has been found for CAPTCHA point: user_login_form');
    $this->assertEquals($result->getCaptchaType(), 'altcha/ALTCHA', 'Altcha type has been configured for CAPTCHA point: user_login_form');

    // The ALTCHA i18n JS library is only attached when the current language is
    // not English, so configure French with a path prefix and use it.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', ['language-url' => -10])
      ->save();
    $this->config('language.negotiation')
      ->set('url.source', 'path_prefix')
      ->set('url.prefixes', ['en' => '', 'fr' => 'fr'])
      ->save();
    $this->rebuildContainer();

    // Use the ALTCHA i18n JS library method (the default).
    $this->config('altcha.settings')->set('i18n_method', 'altcha')->save();

    // Now go to the login page where the ALTCHA form will be rendered.
    $this->drupalGet('fr/user/login');

    // An ALTCHA should exist.
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);

    // The default i18n library should be loaded via script tag.
    $this->assertSession()->elementExists('xpath', "//head//script[contains(@src, '{$this->defaultI18nLibrary}')]");

    // 1. Library override CDN url.
    $this->validateLibraryOverride(
      'https://cdn.example.com/js/altcha-i18n-all.min.js',
      'https://cdn.example.com/js/altcha-i18n-all.min.js',
      'i18n_library_override',
      $this->defaultI18nLibrary,
      'fr/user/login',
    );

    // 2. Library override public file uri.
    $this->validateLibraryOverride(
      'public://libraries/altcha/js/altcha-i18n-public-fs-library.min.js',
      'files/libraries/altcha/js/altcha-i18n-public-fs-library.min.js',
      'i18n_library_override',
      $this->defaultI18nLibrary,
      'fr/user/login',
    );

    // 3. Library override url relative to the drupal web root.
    $this->validateLibraryOverride(
      'libraries/js/altcha-i18n-relative-path-library.min.js',
      'libraries/js/altcha-i18n-relative-path-library.min.js',
      'i18n_library_override',
      $this->defaultI18nLibrary,
      'fr/user/login',
    );

    // 4. Library override url absolute to the server root.
    $this->validateLibraryOverride(
      \Drupal::root() . '/libraries/js/altcha-i18n-absolute-path-library.min.js',
      'libraries/js/altcha-i18n-absolute-path-library.min.js',
      'i18n_library_override',
      $this->defaultI18nLibrary,
      'fr/user/login',
    );
  }

  /**
   * Tests if the ALTCHA i18n library is attached correctly.
   *
   * Since the default widget text is already in English, the ALTCHA i18n JS
   * library should only be attached when the current language is not English.
   */
  public function testAltchaI18nLibraryAttachment(): void {
    // Enable 'altcha/ALTCHA' on login form.
    captcha_set_form_id_setting('user_login_form', 'altcha/ALTCHA');

    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', ['language-url' => -10])
      ->save();
    $this->config('language.negotiation')
      ->set('url.source', 'path_prefix')
      ->set('url.prefixes', ['en' => '', 'fr' => 'fr'])
      ->save();
    $this->rebuildContainer();

    // Use the ALTCHA i18n JS library method.
    $this->config('altcha.settings')->set('i18n_method', 'altcha')->save();

    // In English, the ALTCHA i18n library should not be attached.
    $this->drupalGet('user/login');
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);
    $this->assertSession()->elementNotExists('xpath', "//head//script[contains(@src, '{$this->defaultI18nLibrary}')]");

    // In French, the ALTCHA i18n library should be attached.
    $this->drupalGet('fr/user/login');
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);
    $this->assertSession()->elementExists('xpath', '//altcha-widget[@language="fr-fr"]');
    $this->assertSession()->elementExists('xpath', "//head//script[contains(@src, '{$this->defaultI18nLibrary}')]");
  }

  /**
   * Tests Drupal to ALTCHA i18n language mapping.
   */
  public function testAltchaI18nLanguageMapping(): void {
    $expected_mappings = [
      'es' => 'es-es',
      'es-es' => 'es-es',
      'es-419' => 'es-419',
      'fr' => 'fr-fr',
      'fr-ca' => 'fr-ca',
      'fr-fr' => 'fr-fr',
      'pt' => 'pt-pt',
      'pt-br' => 'pt-br',
      'pt-pt' => 'pt-pt',
      'zh' => 'zh-cn',
      'zh-cn' => 'zh-cn',
      'zh-hans' => 'zh-cn',
      'zh-hant' => 'zh-tw',
      'zh-tw' => 'zh-tw',
      'nl' => 'nl',
    ];

    foreach ($expected_mappings as $drupal_langcode => $altcha_langcode) {
      $this->assertSame($altcha_langcode, AltchaI18nUtility::getI18nLanguage($drupal_langcode));
    }
  }

  /**
   * Tests if the Drupal i18n custom translation library is attached correctly.
   *
   * The 'altcha/js/custom-translation.behaviors.js' library registers widget
   * text and label overrides from drupalSettings, so it should be attached
   * whenever the Drupal i18n method is selected.
   */
  public function testI18nCustomLibraryAttachment(): void {
    // Enable 'altcha/ALTCHA' on login form.
    captcha_set_form_id_setting('user_login_form', 'altcha/ALTCHA');

    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', ['language-url' => -10])
      ->save();
    $this->config('language.negotiation')
      ->set('url.source', 'path_prefix')
      ->set('url.prefixes', ['en' => '', 'fr' => 'fr'])
      ->save();
    $this->rebuildContainer();

    // Use the Drupal interface translation i18n method.
    $this->config('altcha.settings')
      ->set('i18n_method', 'drupal')
      ->set('label', 'Drupal label from settings')
      ->save();

    // In English, the custom i18n library should be attached.
    $this->drupalGet('user/login');
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);
    $this->assertSession()->elementExists('xpath', '//altcha-widget[@language="en"]');
    $this->assertSession()->elementExists('xpath', "//head//script[contains(@src, '{$this->i18nCustomLibrary}')]");
    $this->assertSession()->responseContains('Drupal label from settings');

    // In French, the custom i18n library should be attached.
    $this->drupalGet('fr/user/login');
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);
    $this->assertSession()->elementExists('xpath', '//altcha-widget[@language="en"]');
    $this->assertSession()->elementExists('xpath', "//head//script[contains(@src, '{$this->i18nCustomLibrary}')]");
    $this->assertSession()->responseContains('Drupal label from settings');
  }

  /**
   * Tests if the fallback library is attached in different scenarios.
   */
  public function testFallbackLibrary() {
    // Enable 'altcha/ALTCHA' on login form.
    captcha_set_form_id_setting('user_login_form', 'altcha/ALTCHA');
    $result = captcha_get_form_id_setting('user_login_form');

    // ALTCHA should be configured.
    $this->assertNotNull($result, 'A configuration has been found for CAPTCHA point: user_login_form');
    $this->assertEquals($result->getCaptchaType(), 'altcha/ALTCHA', 'Altcha type has been configured for CAPTCHA point: user_login_form');

    // Disable JS aggregation for easy validation.
    $system_performance_config = $this->config('system.performance');
    $system_performance_config->set('js.preprocess', FALSE);
    $system_performance_config->save();

    $this->validateFallbackLibraryLoaded(FALSE);

    // Enable sentinel with fallback feature flag enabled.
    $altcha_config = $this->config('altcha.settings');
    $altcha_config->set('integration_type', 'sentinel_api');
    $altcha_config->set('sentinel_api_url', 'https://sentinel.example.com');
    $altcha_config->set('sentinel_api_key', 'key_');
    $altcha_config->set('sentinel_api_secret', 'sec_');
    $altcha_config->set('sentinel_fallback_enabled', TRUE);
    $altcha_config->save();

    $this->validateFallbackLibraryLoaded();

    // Swap back to self-hosted integration. There is no fallback possible here.
    $altcha_config->set('integration_type', 'self_hosted');
    $altcha_config->save();

    $this->validateFallbackLibraryLoaded(FALSE);

    // Explicitly disable fallback the feature flag.
    $altcha_config->set('integration_type', 'sentinel_api');
    $altcha_config->set('sentinel_fallback_enabled', FALSE);
    $altcha_config->save();

    $this->validateFallbackLibraryLoaded(FALSE);
  }

  /**
   * Helper function to validate presence of the fallback library.
   *
   * @param bool $should_be_loaded
   *   Whether the library should be loaded.
   */
  protected function validateFallbackLibraryLoaded(bool $should_be_loaded = TRUE): void {
    // Now go to the login page where the ALTCHA form will be rendered.
    $this->drupalGet('user/login');

    // An ALTCHA widget should exist.
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);

    // The fallback library should be loaded via script tag.
    if ($should_be_loaded) {
      $this->assertSession()->elementExists('xpath', "//head//script[contains(@src, 'altcha/js/altcha-fallback.behaviors.js')]");
    }
    else {
      $this->assertSession()->elementNotExists('xpath', "//head//script[contains(@src, 'altcha/js/altcha-fallback.behaviors.js')]");
    }
  }

  /**
   * Helper function to validate library overrides.
   *
   * @param string $override
   *   The override to be configured in ALTCHA settings.
   * @param string $expectation
   *   The expected script src to be loaded in the html head.
   * @param string $config_key
   *   The ALTCHA settings config key to override.
   * @param string $default_library
   *   The default library path that should no longer be present once the
   *   override is configured.
   * @param string $path
   *   The path to load to render the ALTCHA widget.
   */
  protected function validateLibraryOverride(string $override, string $expectation, string $config_key = 'library_override', string $default_library = '', string $path = 'user/login'): void {
    $default_library = $default_library ?: $this->defaultLibrary;

    $this->config('altcha.settings')->set($config_key, $override)->save();

    // Reload the login page to apply the changes.
    $this->drupalGet($path);
    // An ALTCHA should still exist on the form.
    $this->assertSession()->elementExists('xpath', $this->altchaSelector);

    // Verify that the script tag with the library URL is added to the page.
    // We expect this to be in the <head> section of the page.
    $this->assertSession()->elementExists('xpath', "//head//script[contains(@src, '$expectation')]");
    // The default library should not be available.
    $this->assertSession()->elementNotExists('xpath', "//head//script[contains(@src, '$default_library')]");

    // When the override does not exactly match the expectation, make sure the
    // override is not just included in the page without any manipulation.
    // Example: "//head//script[contains(@src, 'libraries/altcha.js')]" xpath
    // would also match the override "/var/www/html/libraries/altcha.js".
    if ($override !== $expectation) {
      $this->assertSession()->elementNotExists('xpath', "//head//script[contains(@src, '$override')]");
    }
  }

}
