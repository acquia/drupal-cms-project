<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_dashboard\Form\SetupAiProviderForm;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Add Provider" form for providers that do not require a key.
 *
 * @group ai_dashboard
 */
#[RunTestsInSeparateProcesses]
class SetupAiProviderFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'key',
    'ai',
    'ai_dashboard',
    'ai_dashboard_test',
  ];

  /**
   * The AI setup status service.
   *
   * @var \Drupal\ai_dashboard\AiSetupStatusInterface
   */
  protected $setupStatus;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai_dashboard_test']);
    $this->setupStatus = $this->container->get('ai_dashboard.setup_status');
  }

  /**
   * Tests SetupAiProviderForm::providerRequiresApiKey().
   */
  public function testProviderRequiresApiKey(): void {
    $form = SetupAiProviderForm::create($this->container);
    $this->assertTrue($form->providerRequiresApiKey('ai_dashboard_test_keyed'));
    $this->assertFalse($form->providerRequiresApiKey('ai_dashboard_test_keyless'));
    // Unknown providers default to requiring a key.
    $this->assertTrue($form->providerRequiresApiKey('does_not_exist'));
  }

  /**
   * Tests that a keyless (local) provider can be reported as configured.
   */
  public function testGetProviderSetupStatusForKeylessProvider(): void {
    $this->assertSame('no', $this->setupStatus->getProviderSetupStatus('ai_dashboard_test_keyless'));

    $this->config('ai_dashboard_test.settings')
      ->set('host_name', 'http://localhost:11434')
      ->save();

    $this->assertSame('yes', $this->setupStatus->getProviderSetupStatus('ai_dashboard_test_keyless'));
  }

  /**
   * Tests the API key is not required to add a provider that does not need one.
   */
  public function testFormDoesNotRequireApiKeyForKeylessProvider(): void {
    $form_state = (new FormState())->setValues([
      'provider' => 'ai_dashboard_test_keyless',
      'api_key' => '',
    ]);
    \Drupal::formBuilder()->submitForm(SetupAiProviderForm::class, $form_state);

    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * Tests the API key is still required to add a provider that needs one.
   */
  public function testFormRequiresApiKeyForKeyedProvider(): void {
    $form_state = (new FormState())->setValues([
      'provider' => 'ai_dashboard_test_keyed',
      'api_key' => '',
    ]);
    \Drupal::formBuilder()->submitForm(SetupAiProviderForm::class, $form_state);

    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertArrayHasKey('api_key', $errors);
  }

  /**
   * Tests a whitespace-only API key is rejected for a keyed provider.
   */
  public function testFormRejectsWhitespaceApiKeyForKeyedProvider(): void {
    $form_state = (new FormState())->setValues([
      'provider' => 'ai_dashboard_test_keyed',
      'api_key' => '   ',
    ]);
    \Drupal::formBuilder()->submitForm(SetupAiProviderForm::class, $form_state);

    $this->assertArrayHasKey('api_key', $form_state->getErrors());
  }

  /**
   * Tests the API key is only marked required client-side for keyed providers.
   */
  public function testApiKeyRequiredStatesOnlyForKeyedProviders(): void {
    $form = \Drupal::formBuilder()->getForm(SetupAiProviderForm::class);

    $api_key = $form['add_provider']['api_key'];
    $this->assertEmpty($api_key['#required']);
    $this->assertContains(
      ['select[name="provider"]' => ['value' => 'ai_dashboard_test_keyed']],
      $api_key['#states']['required']
    );
    $this->assertNotContains(
      ['select[name="provider"]' => ['value' => 'ai_dashboard_test_keyless']],
      $api_key['#states']['required']
    );
  }

  /**
   * Tests a keyed provider is configured once its API key is provided.
   */
  public function testFormSubmitsWithApiKeyForKeyedProvider(): void {
    $form_state = (new FormState())->setValues([
      'provider' => 'ai_dashboard_test_keyed',
      'api_key' => 'super-secret-key',
    ]);
    \Drupal::formBuilder()->submitForm(SetupAiProviderForm::class, $form_state);

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame('yes', $this->setupStatus->getProviderSetupStatus('ai_dashboard_test_keyed'));
  }

  /**
   * Tests the "already configured" warning renders for a keyless provider.
   */
  public function testAlreadyConfiguredWarningForKeylessProvider(): void {
    $this->config('ai_dashboard_test.settings')
      ->set('host_name', 'http://localhost:11434')
      ->save();

    $form = \Drupal::formBuilder()->getForm(SetupAiProviderForm::class);

    $this->assertArrayHasKey('warning', $form['add_provider']['provider_wrapper']);
    $this->assertSame(
      [['select[name="provider"]' => ['value' => 'ai_dashboard_test_keyless']]],
      $form['add_provider']['provider_wrapper']['warning']['#states']['visible']
    );
  }

  /**
   * Tests no warning renders when no provider is configured yet.
   */
  public function testNoAlreadyConfiguredWarningByDefault(): void {
    $form = \Drupal::formBuilder()->getForm(SetupAiProviderForm::class);

    $this->assertArrayNotHasKey('warning', $form['add_provider']['provider_wrapper']);
  }

}
