<?php

namespace Drupal\Tests\ai_provider_amazeeio\Functional;

use Drupal\ai_provider_amazeeio\Form\AmazeeioAiConfigForm;
use Drupal\Tests\BrowserTestBase;

/**
 * Integration tests for amazee.ai AI provider user interfaces.
 */
class AmazeeioProviderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Disable strict config checking.
   *
   * @var bool
   * @todo Config schema of 'ai_vdb_provider_postgres' is broken.
   */
  public $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_provider_amazeeio',
    'ai_provider_amazeeio_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $account = $this->drupalCreateUser([
      'administer ai',
      'administer ai providers',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Provider is in the list of available providers.
   */
  public function testProviderAvailable() {
    $this->drupalGet('/admin/config/ai/providers');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('amazee.ai Authentication');
  }

  /**
   * Fill the email step.
   */
  protected function fillEmail(string $email): void {
    $page = $this->getSession()->getPage();
    $this->drupalGet('/admin/config/ai/providers/amazeeio');
    $page->fillField('Email', $email);
    $page->pressButton('Sign in');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Fill the code step.
   */
  protected function fillCode(string $code): void {
    $page = $this->getSession()->getPage();
    $page->fillField('Code', $code);
    $page->pressButton('Validate');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Select a region.
   */
  protected function selectRegion(string $label): void {
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Region', $label);
    $page->pressButton('Connect');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Email address validation.
   */
  public function testInvalidEmail() {
    $this->fillEmail('john+doe.com');
    $this->assertSession()->pageTextContains('Invalid email address.');
  }

  /**
   * Invalid code error message.
   */
  public function testInvalidCode() {
    $this->fillEmail('john@doe.com');
    $this->fillCode('666');
    $this->assertSession()->pageTextContains('The provided code is incorrect or has expired.');
  }

  /**
   * Helper function to get the options of select field.
   *
   * @todo port of deprecated getOptions function, see https://www.drupal.org/node/3523039 for alternatives
   */
  protected function getFieldOptions($select): array {
    $select = $this->assertSession()->selectExists($select);
    $options = [];

    /** @var \Behat\Mink\Element\NodeElement $option */
    foreach ($select->findAll('xpath', '//option') as $option) {
      $label = $option->getText();
      $value = $option->getAttribute('value') ?: $label;
      $options[$value] = $label;
    }

    return $options;
  }

  /**
   * Inactive regions are not selectable.
   */
  public function testInactiveRegions() {
    $this->fillEmail('john@doe.com');
    $this->fillCode('42');
    $regions = $this->getFieldOptions('Region');
    // "1" is the index of the "Inactive" region.
    // @see MockHttpClient::mockRegions
    $this->assertArrayNotHasKey(1, $regions);
    $this->assertCount(2, $regions);
  }

  /**
   * Error handling when region API breaks.
   */
  public function testBrokenRegion() {
    $this->fillEmail('john@doe.com');
    $this->fillCode('42');
    $this->selectRegion('US 1');
    $this->assertSession()->pageTextContains('An error occurred while generating the private key.');
  }

  /**
   * Complete process and resulting keys.
   */
  public function testKeyGeneration() {
    $this->fillEmail('john@doe.com');
    $this->fillCode('42');
    $this->selectRegion('ch-1');

    $config = \Drupal::configFactory()->get(AmazeeioAiConfigForm::CONFIG_NAME);
    $this->assertEquals('https://amazeeio.llm/ch1', $config->get('host'));

    $this->assertEquals("db_name_ch1", $config->get('postgres_default_database'));
    $this->assertEquals("https://amazeeio.vdb/ch1", $config->get('postgres_host'));
    $this->assertEquals("amazeeio_ai_database", $config->get('postgres_password'));
    $this->assertEquals(5432, $config->get('postgres_port'));
    $this->assertEquals("db_user_ch1", $config->get('postgres_username'));

    $keyStorage = \Drupal::entityTypeManager()->getStorage('key');
    $aiKey = $keyStorage->load(AmazeeioAiConfigForm::API_KEY_NAME);
    $dbKey = $keyStorage->load(AmazeeioAiConfigForm::VDB_PASSWORD_NAME);
    $this->assertEquals('4321', $aiKey->getKeyValue());
    $this->assertEquals('db_pass_ch1', $dbKey->getKeyValue());

    $this->assertSession()->buttonExists('Disconnect');
  }

  /**
   * Test the form in a pre-configured state.
   */
  public function testPreConfigured() {
    $this->testKeyGeneration();
    $this->drupalGet('/admin/config/ai/providers/amazeeio');
    $this->assertSession()->buttonExists('Disconnect');
  }

  /**
   * Verify disconnect works as expected.
   */
  public function testDisconnect() {
    $this->testKeyGeneration();
    $this->getSession()->getPage()->pressButton('Disconnect');
    // We have to press disconnect twice, for the confirmation.
    $this->getSession()->getPage()->pressButton('Disconnect');
    $this->assertSession()->fieldExists('Email');

    $this->drupalGet('/admin/config/ai/providers/amazeeio');

    $config = \Drupal::configFactory()->get(AmazeeioAiConfigForm::CONFIG_NAME);
    $this->assertEquals('', $config->get('host'));
    $this->assertEquals('', $config->get('postgres_default_database'));
    $this->assertEquals('', $config->get('postgres_host'));
    $this->assertEquals('', $config->get('postgres_username'));

    $keyStorage = \Drupal::entityTypeManager()->getStorage('key');
    $aiKey = $keyStorage->load(AmazeeioAiConfigForm::API_KEY_NAME);
    $dbKey = $keyStorage->load(AmazeeioAiConfigForm::VDB_PASSWORD_NAME);
    $this->assertNull($aiKey);
    $this->assertNull($dbKey);
  }

  /**
   * Test re-connecting the same website.
   */
  public function testReconnect() {
    $this->testDisconnect();
    $this->testKeyGeneration();
  }

}
