<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;

/**
 * Plugin implementation of the 'uri' field type.
 */
#[CustomFieldType(
  id: 'uri',
  label: new TranslatableMarkup('URI'),
  description: new TranslatableMarkup('A field containing a URI.'),
  category: new TranslatableMarkup('Link'),
  default_widget: 'url',
  default_formatter: 'uri_link',
  constraints: [
    "CustomFieldLinkAccess" => [],
    "CustomFieldLinkExternalProtocols" => [],
    "CustomFieldLinkType" => [],
    "CustomFieldLinkNotExistingInternal" => [],
  ]
)]
class UriType extends CustomFieldTypeBase implements LinkTypeInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'varchar',
      'length' => 2048,
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name] = $settings;

    $properties[$name] = CustomFieldDataDefinition::create('custom_field_link')
      ->setLabel(new TranslatableMarkup('@label', ['@label' => $name]))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'link_type' => self::LINK_GENERIC,
      'field_prefix' => 'default',
      'field_prefix_custom' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $field_name = $this->getName();
    $settings = $this->getFieldSettings();

    $element['link_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Allowed link type'),
      '#default_value' => $settings['link_type'],
      '#options' => [
        self::LINK_INTERNAL => $this->t('Internal links only'),
        self::LINK_EXTERNAL => $this->t('External links only'),
        self::LINK_GENERIC => $this->t('Both internal and external links'),
      ],
    ];
    $element['field_prefix'] = [
      '#type' => 'radios',
      '#title' => $this->t('Field prefix'),
      '#description' => $this->t('Controls the field prefix for internal links.'),
      '#options' => [
        'default' => $this->t('Default'),
        'custom' => $this->t('Custom'),
      ],
      '#default_value' => $settings['field_prefix'],
      '#states' => [
        'visible' => [
          'input[name="settings[field_settings][' . $field_name . '][link_type]"]' => ['value' => self::LINK_INTERNAL],
        ],
      ],
    ];
    $element['field_prefix_custom'] = [
      '#type' => 'url',
      '#title' => $this->t('Custom field prefix'),
      '#description' => $this->t('Leave empty to not show a prefix.'),
      '#default_value' => $settings['field_prefix_custom'],
      '#attributes' => ['placeholder' => 'https://www.mycustomdomain.com'],
      '#states' => [
        'visible' => [
          ':input[name="settings[field_settings][' . $field_name . '][link_type]"]' => ['value' => self::LINK_INTERNAL],
          0 => 'AND',
          ':input[name="settings[field_settings][' . $field_name . '][field_prefix]"]' => ['value' => 'custom'],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): array {
    $random = new Random();
    $link_type = $field->getFieldSetting('link_type') ?? NULL;
    if ($link_type & self::LINK_EXTERNAL) {
      $tlds = ['com', 'net', 'gov', 'org', 'edu', 'biz', 'info'];
      $domain_length = mt_rand(7, 15);

      $value['uri'] = 'https://www.' . $random->word($domain_length) . '.' . $tlds[mt_rand(0, (count($tlds) - 1))];
    }
    else {
      $value['uri'] = 'base:' . $random->name(mt_rand(1, 64));
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    /** @var array<string, mixed> $definition */
    $definition = $this->pluginDefinition;
    return $definition['constraints'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(FieldItemInterface $item): Url {
    return Url::fromUri($item->{$this->name});
  }

  /**
   * {@inheritdoc}
   */
  public function isExternal(FieldItemInterface $item): bool {
    return $this->getUrl($item)->isExternal();
  }

}
