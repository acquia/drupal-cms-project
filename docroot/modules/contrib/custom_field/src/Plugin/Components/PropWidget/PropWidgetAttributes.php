<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\PropWidget;
use Drupal\custom_field\Plugin\PropWidgetBase;

/**
 * Plugin implementation of the 'attributes' widget.
 */
#[PropWidget(
  id: 'attributes',
  prop_type: 'attributes',
  label: new TranslatableMarkup('Attributes'),
)]
class PropWidgetAttributes extends PropWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(string $property, mixed $value): array {
    $value = array_filter($value);
    $summary = [
      $this->t('@space@property: @value', [
        '@space' => $this->space(),
        '@property' => $property,
        '@value' => empty($value) ? self::EMPTY_VALUE : '',
      ]),
    ];
    foreach ($value as $key => $item) {
      $summary[] = $this->t('@space@key: @value', [
        '@space' => $this->space(4),
        '@key' => $key,
        '@value' => $item,
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value, $required): array {
    $settings = $this->getSettings() + static::defaultSettings();
    if (!\is_array($value)) {
      $value = [];
    }
    $value = $value['value'] ?? [];

    return [
      '#type' => 'details',
      '#title' => $settings['title'],
      '#description' => $settings['description'] ?: $this->t('Attributes provided should be relevant to the component being rendered.'),
      'widget' => [
        '#type' => 'value',
        '#value' => $this->getPluginId(),
      ],
      'value' => [
        '#type' => 'container',
        'title' => [
          '#type' => 'textfield',
          '#title' => $this->t('Title'),
          '#maxlength' => 255,
          '#default_value' => $value['title'] ?? '',
        ],
        'aria-label' => [
          '#type' => 'textfield',
          '#title' => $this->t('ARIA Label'),
          '#maxlength' => 255,
          '#default_value' => $value['aria-label'] ?? '',
        ],
        'target' => [
          '#type' => 'select',
          '#title' => $this->t('Target'),
          '#options' => [
            '_self' => $this->t('Same window (_self)'),
            '_blank' => $this->t('New window (_blank)'),
          ],
          '#empty_value' => '',
          '#default_value' => $value['target'] ?? '',
        ],
        'class' => [
          '#type' => 'textfield',
          '#title' => $this->t('Class'),
          '#description' => $this->t('Enter additional classes, separated by space.'),
          '#default_value' => $value['class'] ?? '',
        ],
        'id' => [
          '#type' => 'textfield',
          '#title' => $this->t('ID'),
          '#maxlength' => 255,
          '#default_value' => $value['id'] ?? '',
        ],
        'name' => [
          '#type' => 'textfield',
          '#title' => $this->t('Name'),
          '#maxlength' => 255,
          '#default_value' => $value['name'] ?? '',
        ],
        'rel' => [
          '#type' => 'textfield',
          '#title' => $this->t('Rel'),
          '#description' => $this->t('Separate multiple rel attributes by a single space'),
          '#default_value' => $value['rel'] ?? '',
        ],
        'accesskey' => [
          '#type' => 'textfield',
          '#title' => $this->t('Access key'),
          '#description' => $this->t('Must be a single alphanumeric character. Each access key on a page should be unique to avoid browser conflicts.'),
          '#size' => 1,
          '#maxlength' => 1,
          '#pattern' => '[a-zA-Z0-9]',
          '#default_value' => $value['accesskey'] ?? '',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function massageValue(array $value): array {
    $value['value'] = array_filter($value['value']);
    return $value;
  }

}
