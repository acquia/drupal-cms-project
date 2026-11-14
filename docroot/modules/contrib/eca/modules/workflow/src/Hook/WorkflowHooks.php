<?php

namespace Drupal\eca_workflow\Hook;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eca\EntityOriginalTrait;
use Drupal\eca\Event\TriggerEvent;
use Drupal\eca\Service\ContentEntityTypes;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Implements workflow hooks for the ECA Workflow submodule.
 */
class WorkflowHooks {

  use EntityOriginalTrait;

  /**
   * Constructs a new WorkflowHooks object.
   *
   * @param \Drupal\eca\Event\TriggerEvent $triggerEvent
   *   The trigger event.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation information.
   * @param \Drupal\eca\Service\ContentEntityTypes $contentEntityTypes
   *   The content entity types.
   * @param \Drupal\Core\Action\ActionManager $actionManager
   *   The action plugin manager.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
    protected ModerationInformationInterface $moderationInformation,
    protected ContentEntityTypes $contentEntityTypes,
    #[Autowire(service: 'plugin.manager.action')] protected ActionManager $actionManager,
  ) {}

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      if ($this->moderationInformation->isModeratedEntity($entity) && $entity->hasField('moderation_state')) {
        $original = $this->getOriginal($entity);
        $from_state = $original instanceof ContentEntityInterface ? $original->get('moderation_state')->value : NULL;
        $to_state = $entity->get('moderation_state')->value;
        if ($from_state !== $to_state) {
          $this->triggerEvent->dispatchFromPlugin('workflow:transition', $entity, $from_state, $to_state, $this->contentEntityTypes);
        }
      }
    }
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->entityInsert($entity);
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('workflow_insert')]
  public function onWorkflowInsert(): void {
    $this->actionManager->clearCachedDefinitions();
  }

}
