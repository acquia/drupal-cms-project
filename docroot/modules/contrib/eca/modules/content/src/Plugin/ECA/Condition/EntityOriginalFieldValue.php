<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\EntityOriginalTrait;

/**
 * Class for the original field value.
 *
 * Plugin implementation of the ECA condition for an entity original
 * field value.
 */
#[EcaCondition(
  id: 'eca_entity_original_field_value',
  label: new TranslatableMarkup('Entity: original has field value'),
  context_definitions: [
    'entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup('Entity'),
    ),
  ],
  description: new TranslatableMarkup('Compares a field value of an entities <em>original</em>  by specific properties.'),
  version_introduced: '1.0.0',
)]
class EntityOriginalFieldValue extends EntityFieldValue {

  use EntityOriginalTrait;

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ?EntityInterface {
    if (!($entity = parent::getEntity())) {
      return NULL;
    }
    return $this->getOriginal($entity);
  }

}
