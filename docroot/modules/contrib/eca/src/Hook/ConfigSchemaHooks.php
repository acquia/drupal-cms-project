<?php

namespace Drupal\eca\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eca\Plugin\Action\ActionInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\eca\PluginManager\Action;
use Drupal\eca\PluginManager\Condition;
use Drupal\eca\PluginManager\Event;

/**
 * Provides hooks related to config schemas.
 */
class ConfigSchemaHooks {

  use ConfigSchemaHooksTrait;

  /**
   * Constructs the config schema hook object.
   */
  public function __construct(
    protected Action $actionManager,
    protected Condition $conditionManager,
    protected Event $eventManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    // Work from the plugin definitions. This hook runs inside the typed config
    // build, before the definitions are cached. Instantiating every plugin
    // here would run their create() methods in that window, and those read
    // config and pull in services like the serializer and Twig. Anything on
    // that path that asks typed config for a definition starts a nested full
    // build that runs this hook again.
    // The decorated manager is the unfiltered one: the ECA decorator hides the
    // ECA-only actions from the rest of the site, but their schemas are
    // exactly the ones that need altering.
    $actionDefinitions = $this->actionManager->getDecoratedActionManager()->getDefinitions();
    foreach ($actionDefinitions as $plugin_id => $definition) {
      if (!empty($definition['confirm_form_route_name'])) {
        // ECA does not offer actions that redirect to a confirmation form, so
        // their schemas are left alone, as Actions::actions() leaves the
        // plugins alone. Core's confirm-form actions declare no mapping
        // section, so this only matters for a contrib action that does.
        // Actions::actions() also skips entity:save_action, but that needs no
        // mirror here: core declares entity action schemas only as the
        // wildcard action.configuration.entity:*:*, which the exact-key lookup
        // below never matches.
        continue;
      }
      $key = 'action.configuration.' . $plugin_id;
      if (isset($definitions[$key])) {
        $this->alterSchemaFieldType($definitions, $key);
        $class = $definition['class'] ?? '';
        if (!is_a($class, ActionInterface::class, TRUE) && is_a($class, ConfigurableInterface::class, TRUE)) {
          $definitions[$key]['mapping']['replace_tokens'] = [
            'type' => 'boolean',
            'label' => 'Replace tokens',
            'requiredKey' => FALSE,
          ];
          $actionType = $definition['type'] ?? '';
          if ($actionType === 'entity' || $this->entityTypeManager->getDefinition($actionType, FALSE)) {
            $definitions[$key]['mapping']['object'] = [
              'type' => 'string',
              'label' => 'Token name holding the entity',
              'requiredKey' => FALSE,
            ];
          }
        }
      }
    }
    foreach (array_keys($this->conditionManager->getDefinitions()) as $plugin_id) {
      $key = 'eca.condition.plugin.' . $plugin_id;
      if (isset($definitions[$key])) {
        $this->alterSchemaFieldType($definitions, $key);
      }
    }
    foreach (array_keys($this->eventManager->getDefinitions()) as $plugin_id) {
      $key = 'eca.event.plugin.' . $plugin_id;
      if (isset($definitions[$key])) {
        $this->alterSchemaFieldType($definitions, $key);
      }
    }
  }

}
