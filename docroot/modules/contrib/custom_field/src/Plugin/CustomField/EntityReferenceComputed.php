<?php

namespace Drupal\custom_field\Plugin\CustomField;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * A computed property for the entity reference "entity".
 *
 * Required settings (below the definition's 'settings' key) are:
 *  - target_id: The property name of the field to retrieve raw value.
 *  - target_type: The entity type to load.
 */
class EntityReferenceComputed extends TypedData implements DataReferenceInterface {

  /**
   * The entity object or null.
   *
   * @var \Drupal\Core\TypedData\TypedDataInterface|null
   */
  protected ?TypedDataInterface $entity = NULL;

  /**
   * The entity type.
   *
   * @var string
   */
  protected mixed $targetType;

  /**
   * The field property name containing the value.
   *
   * @var string
   */
  protected mixed $targetId;

  /**
   * The data value.
   *
   * @var mixed
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, ?TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);

    $settings = $definition->getSettings();
    if (!isset($settings['target_id']) || !isset($settings['target_type'])) {
      throw new \InvalidArgumentException("The definition's 'target_id' key has to specify the name of the field property.");
    }
    $this->targetType = $settings['target_type'];
    $this->targetId = $settings['target_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if ($target = $this->getTarget()) {
      return $target->getValue();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTarget(): TypedDataInterface|ComplexDataInterface|null {
    if (!isset($this->entity) && $id = $this->parent->{$this->targetId}) {
      try {
        $entity = \Drupal::entityTypeManager()
          ->getStorage($this->targetType)
          ->load($id);
        $this->entity = $entity?->getTypedData();
      }
      catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
        // Can't index an entity type that doesn't exist.
      }
    }
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetIdentifier(): int|string|null {
    return $this->parent->{$this->targetId};
  }

}
