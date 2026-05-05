<?php

declare(strict_types=1);

namespace Drupal\custom_field_ai\Plugin\FieldTextExtractor;

use Drupal\ai_translate\Attribute\FieldTextExtractor;
use Drupal\ai_translate\Plugin\FieldTextExtractor\FieldExtractorBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A field text extractor plugin for custom_fields.
 */
#[FieldTextExtractor(
  id: "custom_field",
  label: new TranslatableMarkup('Custom field'),
  field_types: [
    'custom',
  ],
)]
class CustomFieldTextExtractor extends FieldExtractorBase implements ContainerFactoryPluginInterface {

  /**
   * The custom field type manager.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   */
  protected CustomFieldTypeManagerInterface $customFieldTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->customFieldTypeManager = $container->get('plugin.manager.custom_field_type');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface $entity, string $fieldName): array {
    if ($entity->get($fieldName)->isEmpty()) {
      return [];
    }
    $textMeta = $translatableSubFields = [];

    // Figure out which subfields are translatable.
    $field_definition = $entity->get($fieldName)->getFieldDefinition();
    $custom_items = $this->customFieldTypeManager->getCustomFieldItems($field_definition->getSettings());
    foreach ($custom_items as $name => $custom_item) {
      $is_subfield_translatable = $custom_item->getFieldSetting('translatable') ?? FALSE;

      if ($is_subfield_translatable) {
        $data_type = $custom_item->getDataType();
        $translatable_data_types = ['string', 'string_long', 'link', 'image'];
        if (!in_array($data_type, $translatable_data_types)) {
          continue;
        }
        if ($data_type === 'link') {
          $translatableSubFields[] = $name . '__title';
        }
        elseif ($data_type === 'image') {
          $translatableSubFields[] = $name . '__title';
          $translatableSubFields[] = $name . '__alt';
        }
        else {
          $translatableSubFields[] = $name;
        }
      }
    }

    foreach ($entity->get($fieldName) as $delta => $fieldItem) {
      // Filter property data based on translatable subfields.
      $propertyData = array_filter(
        $fieldItem->getValue(),
        static fn($fieldItemKey) => in_array($fieldItemKey, $translatableSubFields, TRUE),
        ARRAY_FILTER_USE_KEY
      );

      // Filter out empty data or non-string values.
      $propertyData = array_filter($propertyData, static fn($item) => !empty($item) && is_string($item));
      $textMeta[] = ['delta' => $delta, '_columns' => array_keys($propertyData)] + $propertyData;
    }
    return $textMeta;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(ContentEntityInterface $entity, string $fieldName, array $textMeta) : void {
    // Start from the original language value array to make sure
    // non-translatable properties do not get cleared.
    $newValue = $entity->get($fieldName)->getValue();
    foreach ($textMeta as $delta => $singleValue) {
      unset($singleValue['field_name'], $singleValue['field_type']);
      // Merge the original language value array with the AI-translated data.
      // Properties that are not translatable should not be in the
      // AI-translated results, so would remain untouched.
      $newValue[$delta] = isset($newValue[$delta]) ? array_merge($newValue[$delta], $singleValue) : $singleValue;
      $field_definition = $entity->getFieldDefinition($fieldName);

      if ($field_definition instanceof FieldDefinitionInterface) {
        $custom_items = $this->customFieldTypeManager->getCustomFieldItems($field_definition->getSettings());
        foreach ($newValue[$delta] as $name => $value) {
          if (isset($custom_items[$name])) {
            // Trim result if the custom field has a length limit.
            $max_length = $custom_items[$name]->getSetting('length') ?? NULL;
            if ($max_length !== NULL && is_string($value)) {
              $newValue[$delta][$name] = mb_strimwidth($value, 0, $max_length, '...');
            }
          }
        }
      }
    }
    $entity->set($fieldName, $newValue);
  }

}
