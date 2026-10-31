<?php

namespace Drupal\eca_access\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\TriggerEvent;

/**
 * Implements access hooks for the ECA Access submodule.
 */
class AccessHooks {

  /**
   * Constructs a new RenderHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
    protected RendererInterface $renderer,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    $render_context = new RenderContext();
    $triggerEvent = $this->triggerEvent;

    /** @var \Drupal\eca_access\Event\EntityAccess|null $event */
    $event = $this->renderer->executeInRenderContext($render_context, static function () use ($entity, $operation, $account, $triggerEvent) {
      // ECA may use parts of the rendering system to evaluate access, such as
      // token replacement. Cacheability metadata coming from there need to be
      // collected, by wrapping the event dispatching with a render context.
      return $triggerEvent->dispatchFromPlugin('access:entity', $entity, $operation, $account);
    });

    if ($event && ($result = $event->getAccessResult())) {
      if ($result instanceof RefinableCacheableDependencyInterface) {
        // If available, add the cacheability metadata from the render context.
        if (!$render_context->isEmpty()) {
          $result->addCacheableDependency($render_context->pop());
        }
        // Disable caching on dynamically determined access.
        $result->mergeCacheMaxAge(0);
      }
      return $result;
    }
    return AccessResult::neutral();
  }

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    // Need the field item list to retrieve the according entity.
    if ($items) {
      $render_context = new RenderContext();
      $triggerEvent = $this->triggerEvent;

      /** @var \Drupal\eca_access\Event\EntityAccess|null $event */
      $event = $this->renderer->executeInRenderContext($render_context, static function () use ($items, $operation, $account, $field_definition, $triggerEvent) {
        // ECA may use parts of the rendering system to evaluate access, such as
        // token replacement. Cacheability metadata coming from there need to be
        // collected, by wrapping the event dispatching with a render context.
        $entity = $items->getEntity();
        $field_name = $field_definition->getName();
        return $triggerEvent->dispatchFromPlugin('access:field', $entity, $operation, $account, $field_name);
      });
      if ($event && ($result = $event->getAccessResult())) {
        if ($result instanceof RefinableCacheableDependencyInterface) {
          // If available, add the cacheability metadata from the render
          // context.
          if (!$render_context->isEmpty()) {
            $result->addCacheableDependency($render_context->pop());
          }
          // Disable caching on dynamically determined access.
          $result->mergeCacheMaxAge(0);
        }
        return $result;
      }
    }

    return AccessResult::neutral();
  }

  /**
   * Implements hook_entity_create_access().
   */
  #[Hook('entity_create_access')]
  public function entityCreateAccess(AccountInterface $account, array $context, ?string $entity_bundle = NULL): AccessResultInterface {
    if (!isset($entity_bundle)) {
      // Entities without bundles usually use the entity type ID, e.g. users.
      $entity_bundle = $context['entity_type_id'];
    }

    $render_context = new RenderContext();
    $triggerEvent = $this->triggerEvent;

    /** @var \Drupal\eca_access\Event\CreateAccess|null $event */
    $event = $this->renderer->executeInRenderContext($render_context, static function () use ($context, $entity_bundle, $account, $triggerEvent) {
      // ECA may use parts of the rendering system to evaluate access, such as
      // token replacement. Cacheability metadata coming from there need to be
      // collected, by wrapping the event dispatching with a render context.
      return $triggerEvent->dispatchFromPlugin('access:create', $context, $entity_bundle, $account);
    });
    if ($event && ($result = $event->getAccessResult())) {
      if ($result instanceof RefinableCacheableDependencyInterface) {
        // If available, add the cacheability metadata from the render context.
        if (!$render_context->isEmpty()) {
          $result->addCacheableDependency($render_context->pop());
        }
        // Disable caching on dynamically determined access.
        $result->mergeCacheMaxAge(0);
      }
      return $result;
    }
    return AccessResult::neutral();
  }

}
