<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Get the value of an entity field.
 */
#[Action(
  id: 'eca_get_default_field_value',
  label: new TranslatableMarkup('Entity: get default field value'),
  type: 'entity',
)]
#[EcaAction(
  description: new TranslatableMarkup('Get the default value of any field in an entity and store it as a token.'),
  version_introduced: '2.0.0',
)]
class GetDefaultFieldValue extends GetFieldValue {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof EntityInterface) {
      $object = $this->prepareEntity($object);
    }
    return parent::access($object, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    parent::execute($this->prepareEntity($entity));
  }

  /**
   * Prepares the given entity for extracting the default value.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The prepared entity.
   *
   * @throws \InvalidArgumentException
   *   When the given entity is not fieldable, or the configured field name
   *   does not exist for the entity.
   */
  protected function prepareEntity(EntityInterface $entity): FieldableEntityInterface {
    if (!($entity instanceof FieldableEntityInterface)) {
      throw new \InvalidArgumentException("The given entity is not fieldable.");
    }
    $field_name = strstr($this->normalizePropertyPath($this->getFieldName() . '.'), '.', TRUE);
    if (!$entity->hasField($field_name)) {
      throw new \InvalidArgumentException(sprintf("The field %s does not exist.", $field_name));
    }

    $cloned = clone $entity;
    $field_items = $cloned->get($field_name);
    $field_items->setValue($field_items->getFieldDefinition()->getDefaultValue($cloned));

    return $cloned;
  }

}
