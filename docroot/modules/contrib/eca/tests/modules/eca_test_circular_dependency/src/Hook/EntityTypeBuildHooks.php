<?php

namespace Drupal\eca_test_circular_dependency\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\eca\PluginManager\Action;
use Drupal\eca\Service\Actions;
use Drupal\eca\Token\TokenServices;

/**
 * Depends on ECA services while entity types are built.
 *
 * Stands in for a module whose hook_entity_type_build() implementation needs
 * an ECA service. No ECA service may build entity types while it is
 * constructed, because this class is then requested from inside its own
 * construction, which is a circular reference.
 *
 * The action manager and the action service reach the entity type manager
 * directly. The token services are the widest guard: they pull
 * eca.service.token, whose service_collector tag constructs every
 * eca.token_data_provider, among them
 * \Drupal\eca\Token\CurrentUserDataProvider, so a single injection covers all
 * of those providers as well.
 *
 * @see \Drupal\eca\PluginManager\Action
 * @see \Drupal\eca\Token\TokenServices
 * @see \Drupal\eca\Service\Actions
 */
class EntityTypeBuildHooks {

  /**
   * The key value collection that records the hook invocation.
   */
  public const string COLLECTION = 'eca_test_circular_dependency';

  /**
   * Constructs the entity type build hook object.
   */
  public function __construct(
    protected Action $actionManager,
    protected TokenServices $tokenServices,
    protected Actions $actionService,
    protected KeyValueFactoryInterface $keyValueFactory,
  ) {}

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    $collection = $this->keyValueFactory->get(self::COLLECTION);
    $collection->set('action_manager', $this->actionManager::class);
    $collection->set('token_services', $this->tokenServices::class);
    $collection->set('action_service', $this->actionService::class);
  }

}
