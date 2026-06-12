<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\DataType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\custom_field\Plugin\Field\FieldType\CustomItem;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;

/**
 * The custom_field_link data type.
 */
#[DataType(
  id: 'custom_field_link',
  label: new TranslatableMarkup('Link'),
  definition_class: CustomFieldDataDefinition::class,
)]
class CustomFieldLink extends CustomFieldDataTypeBase {

  /**
   * The link title value.
   *
   * @var string|null
   */
  protected ?string $title = NULL;

  /**
   * The link options value.
   *
   * @var array<string, mixed>
   */
  protected array $options = [];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, ?FieldItemInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $field_type = $definition->getSetting('field_type');
    if ($parent && $field_type === 'link') {
      $this->title = $parent->get($this->getName() . CustomItem::SEPARATOR . 'title')->getValue();
      $this->options = $parent->get($this->getName() . CustomItem::SEPARATOR . 'options')->getValue() ?? [];
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function setValue($value, $notify = TRUE): void {
    // Treat the values as property value of the main property, if no array is
    // given.
    $parent = $this->getParent();
    if ($value && !is_array($value)) {
      $value = [
        'uri' => $value,
      ];
    }
    if (isset($value['title'])) {
      $this->title = $value['title'];
      $parent->set($this->getName() . CustomItem::SEPARATOR . 'title', $value['title']);
    }
    if (isset($value['options']) && is_array($value['options'])) {
      $this->options = $value['options'];
      $parent->set($this->getName() . CustomItem::SEPARATOR . 'options', $value['options']);
    }

    $this->value = $value['uri'] ?? NULL;
  }

  /**
   * Returns the title value.
   *
   * @return string|null
   *   The link title.
   */
  public function getTitle(): ?string {
    return $this->title;
  }

  /**
   * Returns the link options.
   *
   * @return array<string, mixed>
   *   The link options array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getOptions(): array {
    $field_type = $this->getDataDefinition()->getSetting('field_type');
    if ($field_type === 'link') {
      $this->options = $this->getParent()->get($this->getName() . CustomItem::SEPARATOR . 'options')->getValue();
    }
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCastedValue() {
    return $this->value;
  }

}
