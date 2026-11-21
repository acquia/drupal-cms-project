<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'ui_icon' field type.
 */
#[FieldType(
  id: 'ui_icon',
  label: new TranslatableMarkup('Icon'),
  description: new TranslatableMarkup('Reference an Icon from icon pack definition.'),
  default_widget: 'icon_widget',
  default_formatter: 'icon_formatter',
  list_class: IconFieldItemList::class,
  category:'icon',
)]
class IconType extends FieldItemBase {

  /**
   * Plugin manager for icons pack discovery and definitions.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  private ?IconPackManagerInterface $pluginManagerIconPack = NULL;

  /**
   * Get the Icon pack plugin manager.
   *
   * @return \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   *   Plugin manager for icon pack discovery and definitions.
   */
  private function getIconPackManager(): IconPackManagerInterface {
    if (!isset($this->pluginManagerIconPack)) {
      $this->pluginManagerIconPack = \Drupal::service('plugin.manager.icon_pack');
    }

    return $this->pluginManagerIconPack;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'target_id' => [
          'description' => 'Icon id.',
          'type' => 'varchar_ascii',
          'length' => 128,
          'not null' => TRUE,
        ],
      ],
      'indexes' => [
        'target_id' => ['target_id'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = [];

    $properties['target_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Icon ID'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'allowed_icon_pack' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $options = $this->getIconPackManager()->listIconPackOptions();

    $elements = [
      'allowed_icon_pack' => [
        '#type' => 'checkboxes',
        '#title' => $this->t('Allowed icon packs'),
        '#description' => $this->t('If none are selected, all will be allowed.'),
        '#options' => $options,
        '#default_value' => $this->getSetting('allowed_icon_pack'),
      ],
    ];

    // If no Icon pack enable, inform and do not allow save.
    if (empty($options)) {
      $elements['allowed_icon_pack']['#description'] = $this->t('No Icon pack found, see documentation to add Icon Pack to your website.');
      $elements['allowed_icon_pack']['#required'] = TRUE;
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function fieldSettingsToConfigData(array $settings): array {
    $settings['allowed_icon_pack'] = array_filter($settings['allowed_icon_pack']);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('target_id')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
    $default_value = $field_definition->get('default_value');
    if (isset($default_value[0]['target_id'])) {
      return [
        'target_id' => $default_value[0]['target_id'],
      ];
    }

    // Pick a random icon from allowed if no default.
    $allowed_icon_pack = $field_definition->getSetting('allowed_icon_pack');
    if (empty($allowed_icon_pack)) {
      $allowed_icon_pack = [];
    }
    $icons = \Drupal::service('plugin.manager.icon_pack')->getIcons($allowed_icon_pack);

    if (empty($icons)) {
      return [];
    }

    return [
      'target_id' => array_rand($icons),
    ];
  }

}
