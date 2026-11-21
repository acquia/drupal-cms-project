<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Core\Url;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\LinkAttributesManager;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'link' field type.
 */
#[CustomFieldType(
  id: 'link',
  label: new TranslatableMarkup('Link'),
  description: new TranslatableMarkup('Stores a URL string, optional varchar link text, and optional blob of attributes to assemble a link.'),
  category: new TranslatableMarkup('Link'),
  default_widget: 'link_default',
  default_formatter: 'link',
  constraints: [
    "CustomFieldLinkAccess" => [],
    "CustomFieldLinkExternalProtocols" => [],
    "CustomFieldLinkType" => [],
    "CustomFieldLinkNotExistingInternal" => [],
  ]
)]
class LinkType extends UriType {

  public const WIDGET_OPEN_EXPAND_IF_VALUES_SET = 'expandIfValuesSet';
  public const WIDGET_OPEN_COLLAPSED = 'collapsed';
  public const WIDGET_OPEN_EXPANDED = 'expanded';

  /**
   * The link attributes manager.
   *
   * @var \Drupal\custom_field\LinkAttributesManager
   */
  protected LinkAttributesManager $linkAttributesManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->linkAttributesManager = $container->get('plugin.manager.custom_field_link_attributes');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $title = $name . self::SEPARATOR . 'title';
    $options = $name . self::SEPARATOR . 'options';
    $columns[$name] = [
      'description' => 'The URI of the link.',
      'type' => 'varchar',
      'length' => 2048,
    ];
    $columns[$title] = [
      'description' => 'The link text.',
      'type' => 'varchar',
      'length' => 255,
    ];
    $columns[$options] = [
      'description' => 'Serialized array of options for the link.',
      'type' => 'blob',
      'size' => 'big',
      'serialize' => TRUE,
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name] = $settings;

    $title = $name . self::SEPARATOR . 'title';
    $options = $name . self::SEPARATOR . 'options';

    $properties[$name] = CustomFieldDataDefinition::create('custom_field_link')
      ->setLabel(new TranslatableMarkup('@label URI', ['@label' => $name]))
      ->setRequired(FALSE)
      ->setSetting('field_type', 'link');

    $properties[$title] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('@label link text', ['@label' => $name]))
      ->setInternal(TRUE);

    $properties[$options] = MapDataDefinition::create()
      ->setLabel(new TranslatableMarkup('@label options', ['@label' => $name]))
      ->setInternal(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'enabled_attributes' => [
        'id' => FALSE,
        'name' => FALSE,
        'target' => TRUE,
        'rel' => TRUE,
        'class' => TRUE,
        'accesskey' => FALSE,
      ],
      'widget_default_open' => self::WIDGET_OPEN_EXPAND_IF_VALUES_SET,
      'title' => DRUPAL_OPTIONAL,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();
    $plugin_definitions = $this->linkAttributesManager->getDefinitions();
    $options = array_map(function ($plugin_definition) {
      return $plugin_definition['title'];
    }, $plugin_definitions);
    $selected = array_keys(array_filter($settings['enabled_attributes']));

    // Add description help text to clarify behavior.
    $element['description_display']['#description'] = $this->t('This setting applies to the help text for the URL field.');
    // Append additional clarification to description help text.
    $element['description']['#description'] = [
      '#theme' => 'item_list',
      '#items' => [
        $element['description']['#description'],
        $this->t('Appears as fieldset help text when title or attributes are enabled.'),
      ],
    ];

    $element['title'] = [
      '#type' => 'radios',
      '#title' => $this->t('Allow link text'),
      '#default_value' => $settings['title'],
      '#options' => [
        DRUPAL_DISABLED => $this->t('Disabled'),
        DRUPAL_OPTIONAL => $this->t('Optional'),
        DRUPAL_REQUIRED => $this->t('Required'),
      ],
    ];
    $element['enabled_attributes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enable attributes'),
      '#options' => $options,
      '#default_value' => array_combine($selected, $selected),
      '#description' => $this->t('Select the attributes to allow the user to edit.<br />Single value attributes (e.g. "ID") will replace corresponding attribute set in formatter settings.<br />Multi-value attributes (e.g. "Class") will be merged with corresponding attribute set in formatter settings.'),
      '#description_display' => 'before',
    ];
    $element['widget_default_open'] = [
      '#type' => 'select',
      '#title' => $this->t('Attributes default open behavior'),
      '#options' => [
        self::WIDGET_OPEN_EXPAND_IF_VALUES_SET => $this->t('Expand if values set (Default)'),
        self::WIDGET_OPEN_EXPANDED => $this->t('Expand'),
        self::WIDGET_OPEN_COLLAPSED => $this->t('Collapse'),
      ],
      '#default_value' => $settings['widget_default_open'] ?? self::WIDGET_OPEN_EXPAND_IF_VALUES_SET,
      '#description' => $this->t('Set the widget default open behavior.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): array {
    $random = new Random();
    $field_settings = $field->getFieldSettings();
    $link_type = $field_settings['link_type'] ?? NULL;
    if ($link_type & self::LINK_EXTERNAL) {
      $tlds = ['com', 'net', 'gov', 'org', 'edu', 'biz', 'info'];
      $domain_length = mt_rand(7, 15);

      switch ($field_settings['title']) {
        case DRUPAL_DISABLED:
          $value['title'] = '';
          break;

        case DRUPAL_REQUIRED:
          $value['title'] = $random->sentences(4);
          break;

        case DRUPAL_OPTIONAL:
          // In case of optional title, randomize its generation.
          $value['title'] = mt_rand(0, 1) ? $random->sentences(4) : '';
          break;
      }
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
    return Url::fromUri($item->{$this->name}, (array) $item->{$this->name . self::SEPARATOR . 'options'});
  }

}
