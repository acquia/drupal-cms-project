<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\DataType;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\custom_field\Plugin\Field\FieldType\CustomItem;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;

/**
 * The "custom_field_image" data type.
 *
 * The "custom_field_image" data type provides a way to process entity and
 * additional metadata as part of values.
 */
#[DataType(
  id: 'custom_field_image',
  label: new TranslatableMarkup('Image'),
  definition_class: CustomFieldDataDefinition::class,
)]
class CustomFieldImage extends CustomFieldEntityReferenceBase {

  /**
   * The image alt text value.
   *
   * @var string
   */
  protected mixed $alt;

  /**
   * The image title value.
   *
   * @var string
   */
  protected mixed $title;

  /**
   * The image width value.
   *
   * @var int
   */
  protected mixed $width;

  /**
   * The image height value.
   *
   * @var int
   */
  protected mixed $height;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, ?FieldItemInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->alt = $parent->get($this->getName() . '__alt')->getValue();
    $this->title = $parent->get($this->getName() . '__title')->getValue();
    $this->width = $parent->get($this->getName() . '__width')->getValue();
    $this->height = $parent->get($this->getName() . '__height')->getValue();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function setValue($value, $notify = TRUE): void {
    $entity = $value['entity'] ?? NULL;
    // Drupal core Default Content API Importer will call this method with an
    // array of exported entity reference data. If this is the case, try loading
    // the referenced entity here, before assigning as data value.
    if (!$entity && is_string($value) && Uuid::isValid($value)) {
      $entity = $this->getEntityByUuid($value);
    }
    if (!is_array($value)) {
      $value = ['target_id' => $value];
    }
    if (isset($value['alt'])) {
      $this->setAlt($value['alt']);
    }
    if (isset($value['title'])) {
      $this->setTitle($value['title']);
    }
    if ($entity instanceof EntityInterface) {
      if ($entity->isNew()) {
        try {
          $entity->save();
        }
        catch (EntityStorageException $exception) {
          $entity = NULL;
        }
      }
      $this->entity = $entity;
      $value['target_id'] = $entity->id();
    }

    $this->value = $value['target_id'];
  }

  /**
   * Sets the alt text value.
   *
   * @param string $alt
   *   The alt text value to set.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function setAlt(string $alt): void {
    $this->getParent()->set($this->getName() . CustomItem::SEPARATOR . 'alt', $alt);
    $this->alt = $alt;
  }

  /**
   * Sets the title text value.
   *
   * @param string $title
   *   The title text value to set.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function setTitle(string $title): void {
    $this->getParent()->set($this->getName() . CustomItem::SEPARATOR . 'title', $title);
    $this->title = $title;
  }

  /**
   * Returns the alt value.
   *
   * @return string|null
   *   The image alt text.
   */
  public function getAlt(): ?string {
    return $this->alt;
  }

  /**
   * Returns the title value.
   *
   * @return string|null
   *   The image title.
   */
  public function getTitle(): ?string {
    return $this->title;
  }

  /**
   * Returns the width value.
   *
   * @return int|null
   *   The image width.
   */
  public function getWidth(): ?int {
    return $this->width ? (int) $this->width : NULL;
  }

  /**
   * Returns the height value.
   *
   * @return int|null
   *   The image height.
   */
  public function getHeight(): ?int {
    return $this->height ? (int) $this->height : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCastedValue() {
    $value = $this->getValue();
    return (int) $value;
  }

}
