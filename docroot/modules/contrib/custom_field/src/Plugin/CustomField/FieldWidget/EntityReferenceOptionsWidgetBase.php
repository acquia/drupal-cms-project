<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomField\FieldType\EntityReference;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base plugin class for entity_reference options field widgets.
 */
class EntityReferenceOptionsWidgetBase extends EntityReferenceWidgetBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'empty_option' => '- Select -',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['empty_option'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty option'),
      '#description' => $this->t('Option to show when field is not required.'),
      '#default_value' => $settings['empty_option'],
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    assert($field instanceof EntityReference);
    $field_settings = $field->getFieldSettings();
    $target_type = $field->getTargetType();
    if (!isset($field_settings['handler'])) {
      $field_settings['handler'] = 'default:' . $target_type;
    }

    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
    $handler = $field->getSelectionHandler($field_settings, $target_type);
    // Check for views handler.
    if ($handler::class === 'Drupal\views\Plugin\EntityReferenceSelection\ViewsSelection') {
      if (property_exists($handler, 'configuration')) {
        $configuration = $handler->configuration;
        // Return early if the view hasn't been selected.
        if (empty($configuration['view']['view_name'])) {
          return $element;
        }
      }
    }

    $settableOptions = $handler->getReferenceableEntities(NULL, 'CONTAINS', 250);
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($target_type);
    $return = [];
    foreach ($settableOptions as $bundle => $entity_ids) {
      // The label does not need sanitizing since it is used as an optgroup
      // which is only supported by select elements and auto-escaped.
      $bundle_label = (string) $bundles[$bundle]['label'];
      $return[$bundle_label] = $entity_ids;
    }
    $options = count($return) == 1 ? reset($return) : $return;

    $element += [
      '#type' => 'select',
      '#options' => $options,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    if (empty($value['target_id'])) {
      return NULL;
    }
    if (is_array($value['target_id'])) {
      $value += $value['target_id'];
      unset($value['target_id']);
    }

    return $value;
  }

}
