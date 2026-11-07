<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_test_array\Event\ArrayEvents;
use Drupal\eca_test_array\Plugin\Action\ArrayWrite;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the `eca.subscribed` index follows the stored ECA configuration.
 *
 * Covers issue #3590483. The index used to be rebuilt only when an ECA model
 * was saved or deleted through the entity API. Raw configuration writes left
 * it stale, so disabled models kept running and enabled ones never ran.
 *
 * @see \Drupal\eca\EventSubscriber\EcaConfigSubscriber
 * @see \Drupal\eca\Hook\CacheHooks::rebuild()
 * @see \Drupal\eca\Processor::execute()
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class SubscribedEventsIndexTest extends KernelTestBase {

  /**
   * The ID of the ECA model under test.
   */
  private const string MODEL_ID = 'subscribed_index';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_test_array',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    ArrayWrite::$array = [];
  }

  /**
   * Saves the ECA model, which writes "result" when "trigger" is written.
   *
   * @param bool $status
   *   Whether the model is enabled.
   */
  private function saveModel(bool $status): void {
    Eca::create([
      'langcode' => 'en',
      'status' => $status,
      'id' => self::MODEL_ID,
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event',
          'configuration' => [
            'key' => 'trigger',
            'value' => 'go',
          ],
          'successors' => [
            ['id' => 'write_array', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'write_array' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write into array',
          'configuration' => [
            'key' => 'result',
            'value' => 'executed',
          ],
          'successors' => [],
        ],
      ],
    ])->save();
  }

  /**
   * Loads the model through the entity storage to warm its static cache.
   */
  private function warmEntityCache(): void {
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('eca')->load(self::MODEL_ID));
  }

  /**
   * Writes the key the model reacts on and reports whether the model ran.
   *
   * @return bool
   *   TRUE if the model executed its action, FALSE otherwise.
   */
  private function modelExecutes(): bool {
    unset(ArrayWrite::$array['result']);
    \Drupal::service('plugin.manager.action')
      ->createInstance('eca_test_array_write', [
        'key' => 'trigger',
        'value' => 'go',
      ])
      ->execute();
    return (ArrayWrite::$array['result'] ?? NULL) === 'executed';
  }

  /**
   * Determines whether the index lists the model for the array write event.
   *
   * @return bool
   *   TRUE if `eca.subscribed` lists the model, FALSE otherwise.
   */
  private function modelIsListed(): bool {
    $subscribed = \Drupal::state()->get('eca.subscribed', []);
    foreach ($subscribed[ArrayEvents::WRITE] ?? [] as $models) {
      if (isset($models[self::MODEL_ID])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Disabling a model by a raw config write stops it from running.
   */
  public function testRawDisableRemovesModel(): void {
    $this->saveModel(TRUE);
    $this->assertTrue($this->modelIsListed());
    $this->assertTrue($this->modelExecutes());

    $this->warmEntityCache();
    \Drupal::configFactory()->getEditable('eca.eca.' . self::MODEL_ID)
      ->set('status', FALSE)
      ->save();

    $this->assertFalse($this->modelIsListed(), 'The disabled model must no longer be listed in eca.subscribed.');
    $this->assertFalse($this->modelExecutes(), 'The disabled model must not be executed.');
  }

  /**
   * Enabling a model by a raw config write makes it run.
   */
  public function testRawEnableAddsModel(): void {
    $this->saveModel(FALSE);
    $this->assertFalse($this->modelIsListed());
    $this->assertFalse($this->modelExecutes());

    $this->warmEntityCache();
    \Drupal::configFactory()->getEditable('eca.eca.' . self::MODEL_ID)
      ->set('status', TRUE)
      ->save();

    $this->assertTrue($this->modelIsListed(), 'The enabled model must be listed in eca.subscribed.');
    $this->assertTrue($this->modelExecutes(), 'The enabled model must be executed.');
  }

  /**
   * Deleting a model by a raw config write removes it from the index.
   */
  public function testRawDeleteRemovesModel(): void {
    $this->saveModel(TRUE);
    $this->assertTrue($this->modelIsListed());

    $this->warmEntityCache();
    \Drupal::configFactory()->getEditable('eca.eca.' . self::MODEL_ID)->delete();

    $this->assertFalse($this->modelIsListed(), 'The deleted model must no longer be listed in eca.subscribed.');
    $this->assertFalse($this->modelExecutes(), 'The deleted model must not be executed.');
  }

  /**
   * Flushing all caches heals a stale index.
   */
  public function testCacheRebuildHealsIndex(): void {
    $this->saveModel(TRUE);
    \Drupal::state()->set('eca.subscribed', []);
    $this->assertFalse($this->modelIsListed());

    drupal_flush_all_caches();

    $this->assertTrue($this->modelIsListed(), 'The cache rebuild must list the enabled model in eca.subscribed again.');
    $this->assertTrue($this->modelExecutes(), 'The enabled model must be executed after the cache rebuild.');
  }

  /**
   * A disabled model that is still listed in the index does not run.
   */
  public function testProcessorSkipsDisabledModel(): void {
    $this->saveModel(TRUE);
    $stale = \Drupal::state()->get('eca.subscribed');

    /** @var \Drupal\eca\Entity\Eca $eca */
    $eca = \Drupal::entityTypeManager()->getStorage('eca')->load(self::MODEL_ID);
    $eca->disable()->save();
    $this->assertFalse($this->modelIsListed());

    // Bring back the index as it was while the model was enabled, and let the
    // event dispatcher pick up its subscriptions.
    \Drupal::state()->set('eca.subscribed', $stale);
    $this->assertTrue($this->modelIsListed());
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $dynamic_subscriber = \Drupal::service('eca.dynamic_subscriber');
    $event_dispatcher->removeSubscriber($dynamic_subscriber);
    $event_dispatcher->addSubscriber($dynamic_subscriber);

    $this->assertFalse($this->modelExecutes(), 'A disabled model must not be executed, even when eca.subscribed still lists it.');
  }

}
