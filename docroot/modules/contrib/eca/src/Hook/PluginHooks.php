<?php

namespace Drupal\eca\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\PluginManager\Action;

/**
 * Implements plugin hooks for the ECA module.
 */
class PluginHooks {

  /**
   * Constructs a new ActionHooks object.
   *
   * @param \Drupal\eca\PluginManager\Action $actionPluginManager
   *   The ECA action plugin manager.
   */
  public function __construct(
    protected Action $actionPluginManager,
  ) {}

  /**
   * Implements hook_action_info_alter().
   */
  #[Hook('action_info_alter')]
  public function actionInfoAlter(array &$definitions): void {
    foreach ($definitions as &$definition) {
      try {
        $reflection = new \ReflectionClass($definition['class']);
        foreach ($reflection->getAttributes(EcaAction::class) as $attribute) {
          /** @var \Drupal\eca\Attribute\EcaAction $instance */
          $instance = $attribute->newInstance();
          foreach ($instance->properties() as $key => $value) {
            $definition[$key] = $value;
          }
        }
      }
      catch (\ReflectionException) {
        continue;
      }

    }
  }

}
