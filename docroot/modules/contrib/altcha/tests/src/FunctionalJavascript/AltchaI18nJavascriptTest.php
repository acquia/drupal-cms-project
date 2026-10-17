<?php

declare(strict_types=1);

namespace Drupal\Tests\altcha\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests ALTCHA internationalization javascript functionalities.
 *
 * @group altcha
 *
 * @dependencies captcha
 */
class AltchaI18nJavascriptTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['altcha', 'captcha', 'config_translation', 'language'];

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

    captcha_set_form_id_setting('user_login_form', 'altcha/ALTCHA');
    $this->config('altcha.settings')
      ->set('delay', 1000)
      ->save();

    // Add French, negotiated via a path prefix, so pages can be requested in
    // French by visiting "fr/...".
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', ['language-url' => -10])
      ->save();
    $this->config('language.negotiation')
      ->set('url.source', 'path_prefix')
      ->set('url.prefixes', ['en' => '', 'fr' => 'fr'])
      ->save();
    $this->rebuildContainer();
  }

  /**
   * Gets the rendered text of the widget label.
   *
   * @return string
   *   The rendered text of the widget label.
   */
  protected function getAltchaLabelText(): string {
    $this->assertSession()->waitForElementVisible('css', '.altcha-label');
    $this->assertTrue($this->getSession()->wait(5000, "document.querySelector('.altcha-label')?.innerText.trim().length > 0"));

    return $this->getSession()->evaluateScript("document.querySelector('.altcha-label').innerText.trim()");
  }

  /**
   * Clicks the captcha checkbox and gets the rendered verifying text.
   *
   * @return string
   *   The rendered text of the widget verifying state.
   */
  protected function clickAltchaAndGetVerifyingText(): string {
    $this->getSession()
      ->getPage()
      ->find('css', '.altcha-checkbox input')
      ->click();

    $this->assertTrue($this->getSession()->wait(5000, "document.querySelector('.altcha[data-state=\"verifying\"]')?.innerText.trim().length > 0"));

    return $this->getSession()->evaluateScript("document.querySelector('.altcha[data-state=\"verifying\"]').innerText.trim()");
  }

  /**
   * Tests that a label override takes precedence over ALTCHA i18n.
   *
   * When a label override is configured it should always be used, taking
   * precedence over any translation provided by the ALTCHA i18n JS library.
   */
  public function testLabelOverrideTakesPrecedenceOverAltchaI18n(): void {
    $this->config('altcha.settings')
      ->set('i18n_method', 'altcha')
      ->set('label', 'Overridden label text FR')
      ->set('verifying', 'Overridden verifying text FR')
      ->save();

    $this->drupalGet('fr/user/login');

    $this->assertStringContainsString('Overridden label text FR', $this->getAltchaLabelText());
    $this->assertStringContainsString('Overridden verifying text FR', $this->clickAltchaAndGetVerifyingText());
  }

  /**
   * Tests that the ALTCHA i18n JS library French translation is used.
   *
   * When no label override is configured, and the ALTCHA i18n JS library
   * method is used, the widget should fall back to the French translation
   * bundled in the ALTCHA i18n JS library asset.
   */
  public function testAltchaI18nFrenchTranslationUsedWithoutOverride(): void {
    $this->config('altcha.settings')
      ->set('i18n_method', 'altcha')
      ->save();

    $this->drupalGet('fr/user/login');

    $this->assertStringContainsString('Pas un robot', $this->getAltchaLabelText());
    $this->assertStringContainsString('Vérification en cours', $this->clickAltchaAndGetVerifyingText());
  }

  /**
   * Tests that the config translation is used in Drupal i18n method.
   *
   * When the Drupal i18n method is used, translated label override
   * configuration should be passed to the widget for the current language.
   */
  public function testDrupalI18nMethodUsesConfigTranslation(): void {
    $this->config('altcha.settings')
      ->set('i18n_method', 'drupal')
      ->set('label', 'Configured label text')
      ->set('verifying', 'Configured verifying text')
      ->save();

    $this->addConfigTranslation('fr', [
      'label' => 'Config translated label text FR',
      'verifying' => 'Config translated verifying text FR',
    ]);

    $this->drupalGet('fr/user/login');

    $this->assertStringContainsString('Config translated label text FR', $this->getAltchaLabelText());
    $this->assertStringContainsString('Config translated verifying text FR', $this->clickAltchaAndGetVerifyingText());
  }

  /**
   * Tests that untranslated config is used when no translation exists.
   *
   * When the Drupal i18n method is used and a config override has not been
   * translated for the current language, the default override should be used.
   */
  public function testDrupalI18nMethodUsesDefaultConfigWithoutTranslation(): void {
    $this->config('altcha.settings')
      ->set('i18n_method', 'drupal')
      ->set('label', 'Configured label fallback text')
      ->set('verifying', 'Configured verifying fallback text')
      ->save();

    $this->drupalGet('fr/user/login');

    $this->assertStringContainsString('Configured label fallback text', $this->getAltchaLabelText());
    $this->assertStringContainsString('Configured verifying fallback text', $this->clickAltchaAndGetVerifyingText());
  }

  /**
   * Tests a config_translation translated label override.
   *
   * Label override configuration values can be separately translated per
   * language using the core config_translation module (the "Translate
   * ALTCHA" tab). When a French translation of the "label" override is
   * added this way, it should be used when viewing the widget in French,
   * taking precedence over the default (untranslated) override value.
   */
  public function testConfigTranslationOverrideLabel(): void {
    $this->config('altcha.settings')
      ->set('i18n_method', 'altcha')
      ->set('label', 'Overridden label text')
      ->set('verifying', 'Overridden verifying text')
      ->save();

    $admin_user = $this->drupalCreateUser([
      'administer altcha',
      'administer CAPTCHA settings',
      'access administration pages',
      'translate configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/people/captcha/altcha/translate/fr/add');
    $this->submitForm([
      'translation[config_names][altcha.settings][label]' => 'Overridden label text FR',
      'translation[config_names][altcha.settings][verifying]' => 'Overridden verifying text FR',
    ], 'Save translation');

    $this->drupalLogout();

    $this->drupalGet('fr/user/login');

    $this->assertStringContainsString('Overridden label text FR', $this->getAltchaLabelText());
    $this->assertStringContainsString('Overridden verifying text FR', $this->clickAltchaAndGetVerifyingText());
  }

  /**
   * Programmatically adds an ALTCHA settings config translation.
   *
   * @param string $langcode
   *   The language code to add the translation for.
   * @param array $values
   *   The translated configuration values.
   */
  protected function addConfigTranslation(string $langcode, array $values): void {
    $language_manager = $this->container->get('language_manager');
    assert($language_manager instanceof ConfigurableLanguageManagerInterface);
    $config_translation = $language_manager->getLanguageConfigOverride($langcode, 'altcha.settings');
    foreach ($values as $key => $value) {
      $config_translation->set($key, $value);
    }

    $config_translation->save();
  }

}
