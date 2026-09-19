<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Kernel;

use Drupal\ai\Plugin\ConfigAction\SetupAiProvider;
use Drupal\ai_provider_amazeeio\Plugin\AiProvider\AmazeeioAiProvider;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;

/**
 * Ensures the module is compatible with the setupAiProvider config action.
 *
 * @see https://www.drupal.org/project/ai_provider_amazeeio/issues/3566598
 *
 * @internal
 */
final class SetupAiProviderCompatibilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'key',
    'ai_provider_amazeeio',
  ];

  /**
   * The setupAiProvider action plugin.
   */
  protected SetupAiProvider $action;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['ai_provider_amazeeio']);

    $this->action = \Drupal::service('plugin.manager.config_action')->createInstance('setupAiProvider');
  }

  /**
   * Verifies setupAiProvider creates a credentials Key and stores the value.
   */
  public function testSetupAiProviderCreatesKeyAndStoresValue(): void {
    // The setupAiProvider cannot configure the bundled amazeeio_ai Key entity
    // inside the module.
    $key_id = 'bar';
    self::assertNull(
      Key::load($key_id),
      'The credentials Key entity must not exist before setupAiProvider runs.'
    );

    $this->action->apply('ai_provider_amazeeio.settings', [
      'key_value' => 'foo',
      'key_name' => $key_id,
      'key_label' => 'baz',
      'provider' => AmazeeioAiProvider::PROVIDER_ID,
    ]);

    $key = Key::load($key_id);
    self::assertNotNull(
      $key,
      'The credentials Key entity must be created by setupAiProvider.'
    );
    self::assertSame(
      'foo',
      (string) $key->getKeyValue(TRUE),
      'The credentials Key entity must store the key_value provided to setupAiProvider.'
    );

    self::assertSame($this->container->get('config.factory')->get('ai_provider_amazeeio.settings')->get('api_key'), $key_id, 'The credentials Key entity id is set as API key in ai_provider_amazeeio.settings config.');
  }

}
