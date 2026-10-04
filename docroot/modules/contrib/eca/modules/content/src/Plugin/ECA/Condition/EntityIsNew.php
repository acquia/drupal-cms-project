<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;

/**
 * Plugin implementation of the ECA condition for new entity.
 */
#[EcaCondition(
  id: 'eca_entity_is_new',
  label: new TranslatableMarkup('Entity: is new'),
  context_definitions: [
    'entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup('Entity'),
    ),
  ],
  description: new TranslatableMarkup('Evaluates if an entity is new.'),
  version_introduced: '1.0.0',
)]
class EntityIsNew extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    if ($entity instanceof EntityInterface) {
      $result = $entity->isNew();
      return $this->negationCheck($result);
    }
    return FALSE;
  }

}
