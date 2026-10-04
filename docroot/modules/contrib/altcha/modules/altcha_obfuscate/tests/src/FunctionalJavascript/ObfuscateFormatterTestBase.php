<?php

namespace Drupal\Tests\altcha_obfuscate\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Base class for obfuscate formatter tests.
 */
abstract class ObfuscateFormatterTestBase extends WebDriverTestBase {

  /**
   * The tested field type.
   */
  public const FIELD_TYPE = '';

  /**
   * The tested formatter plugin ID.
   */
  public const FORMATTER_ID = '';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'user',
    'system',
    'telephone',
    'altcha',
    'altcha_obfuscate',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser(['view test entity']));
  }

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test the obfuscate widget with default formatter settings.
   */
  abstract public function testDefaultSettings(): void;

  /**
   * Test the obfuscate widget with different text override settings.
   */
  abstract public function testTextOverrideSettings(): void;

  /**
   * Tests that the ALTCHA i18n library is attached for obfuscate widgets.
   */
  public function testAltchaI18nLibraryAttachment(): void {
    $french = ConfigurableLanguage::createFromLangcode('fr');
    $french->save();
    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', ['language-url' => -10])
      ->save();
    $this->config('language.negotiation')
      ->set('url.source', 'path_prefix')
      ->set('url.prefixes', ['en' => '', 'fr' => 'fr'])
      ->save();
    $this->config('system.site')
      ->set('default_langcode', 'fr')
      ->save();
    $this->config('altcha.settings')
      ->set('i18n_method', 'altcha')
      ->save();
    $this->rebuildContainer();

    $field_config = $this->createField(static::FIELD_TYPE, static::FORMATTER_ID);
    $value = match (static::FIELD_TYPE) {
      'email' => 'hello@example.com',
      'telephone' => '+32012345678',
      default => 'top secret',
    };
    $entity = EntityTest::create([$field_config->getName() => $value]);
    $entity->save();

    $this->drupalGet($entity->toUrl());

    $this->assertSession()->elementExists('xpath', '//altcha-widget[@plugins="obfuscation" and @language="fr-fr"]');
    $this->assertSession()->elementExists('xpath', "//head//script[contains(@src, 'assets/vendor/altcha/i18n/all.min.js')]");
  }

  /**
   * Helper function to validate the obfuscate widget on the page.
   */
  protected function validateObfuscateWidgetOnPage(): void {
    $element = $this->xpath('//altcha-widget[@plugins="obfuscation"]');
    $this->assertNotEmpty($element, 'Obfuscation widget should be found.');
  }

  /**
   * Helper function to create a field with specific formatter settings.
   *
   * @return \Drupal\field\Entity\FieldConfig
   *   The configured field instance.
   */
  protected function createField($field_type, $formatter, $formatter_settings = []): FieldConfig {
    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'type' => $field_type,
      'cardinality' => 1,
    ])->save();

    $field_config = FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'bundle' => 'entity_test',
      'settings' => [],
    ]);
    $field_config->save();

    $this->container->get('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test', 'full')
      ->setComponent('field_test', [
        'type' => $formatter,
        'settings' => $formatter_settings,
      ])
      ->save();

    return $field_config;
  }

  /**
   * Helper function to update a field with specific formatter settings.
   */
  protected function updateField($formatter, $formatter_settings = []): void {
    $this->container->get('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test', 'full')
      ->setComponent('field_test', [
        'type' => $formatter,
        'settings' => $formatter_settings,
      ])
      ->save();
  }

}
