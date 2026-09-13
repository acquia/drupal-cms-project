<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Trait\DateTimeAdvancedFormatterTrait;

/**
 * Plugin implementation of the 'datetime_custom' formatter.
 */
#[FieldFormatter(
  id: 'datetime_advanced',
  label: new TranslatableMarkup('Advanced'),
  field_types: [
    'datetime',
  ],
)]
class DateTimeAdvancedFormatter extends DateTimeFormatterBase {

  use DateTimeAdvancedFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'date_format_parts' => [
        'month' => [
          'format' => 'F',
          'suffix' => ' ',
        ],
        'day' => [
          'format' => 'jS',
          'suffix' => ', ',
        ],
        'year' => [
          'format' => 'Y',
          'suffix' => '',
        ],
      ],
      'time_format_parts' => [
        'hour' => [
          'format' => 'g',
          'suffix' => ':',
        ],
        'minute' => [
          'format' => 'i',
          'suffix' => '',
        ],
        'second' => [
          'format' => '',
          'suffix' => '',
        ],
        'am_pm' => [
          'format' => 'a',
          'suffix' => '',
        ],
      ],
      'date_first' => 'date',
      'date_time_separator' => ' ',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $field_name = $this->customFieldDefinition->getName();
    $datetime_type = $this->customFieldDefinition->getDatetimeType();
    $parents = $form['#field_parents'];
    $date_part_elements = $this->getDatePartElements();
    $settings = $this->getSettings() + static::defaultSettings();
    $date_parts = $settings['date_format_parts'];
    $time_parts = $settings['time_format_parts'];
    $time = new DrupalDateTime();

    $elements['date_format'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Date format'),
      '#description' => $this->t('Select the format and order for the relevant date parts to display.<br />See <a href=":url" target="_blank">the documentation for PHP date formats</a>. <p><strong>Displayed as:</strong> <span class="js-hide" data-drupal-date-format-preview><em>%date_format</em></span></p>', [
        ':url' => 'https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters',
        '%date_format' => '',
      ]),
      '#description_display' => 'before',
    ];
    $elements['date_format']['date_format_parts'] = [
      '#type' => 'table',
      '#header' => [
        '',
        $this->t('Format'),
        $this->t('Suffix'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $field_name . '-date-format-parts-order',
        ],
      ],
      '#parents' => [...$parents, 'date_format_parts'],
      '#attributes' => [
        'data-drupal-date-format-table' => TRUE,
      ],
      '#element_validate' => [[static::class, 'validateDateFormatParts']],
    ];

    $weight = 0;
    foreach ($date_parts as $key => $part) {
      $part_element = $date_part_elements[$key];
      $formats = [];
      foreach ($part_element['options'] as $option) {
        $formats[$option] = $this->t('@label (@format)', [
          '@label' => $option,
          '@format' => $time->format($option),
        ]);
      }
      $label = $part_element['label'];
      $elements['date_format']['date_format_parts'][$key] = [
        'label' => [
          '#markup' => $label,
        ],
        'format' => [
          '#type' => 'select',
          '#title' => $this->t('Format'),
          '#title_display' => 'invisible',
          '#options' => $formats,
          '#empty_option' => $this->t('- None -'),
          '#default_value' => $part['format'],
          '#attributes' => [
            'data-drupal-date-format-source' => 'format',
          ],
        ],
        'suffix' => [
          '#type' => 'textfield',
          '#title' => $this->t('Suffix'),
          '#title_display' => 'invisible',
          '#maxlength' => 10,
          '#size' => 5,
          '#default_value' => $part['suffix'],
          '#attributes' => [
            'data-drupal-date-format-source' => 'suffix',
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $label]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#attributes' => ['class' => [$field_name . '-date-format-parts-order']],
        ],
        '#weight' => $weight,
        '#attributes' => [
          'class' => ['draggable'],
        ],
      ];
      $weight++;
    }

    if ($datetime_type === DateTimeType::DATETIME_TYPE_DATETIME) {
      $elements['time_format'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Time format'),
        '#description' => $this->t('Select the format and order for the relevant time parts to display.<br />See <a href=":url" target="_blank">the documentation for PHP date formats</a>. <p><strong>Displayed as:</strong> <span class="js-hide" data-drupal-date-format-preview><em>%date_format</em></span></p>', [
          ':url' => 'https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters',
          '%date_format' => '',
        ]),
        '#description_display' => 'before',
      ];
      $elements['time_format']['time_format_parts'] = [
        '#type' => 'table',
        '#header' => [
          '',
          $this->t('Format'),
          $this->t('Suffix'),
          $this->t('Weight'),
        ],
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => $field_name . '-time-format-parts-order',
          ],
        ],
        '#parents' => [...$parents, 'time_format_parts'],
        '#attributes' => [
          'data-drupal-date-format-table' => TRUE,
        ],
        '#element_validate' => [[static::class, 'validateDateFormatParts']],
      ];

      $time_weight = 0;
      foreach ($time_parts as $key => $part) {
        $part_element = $date_part_elements[$key];
        $formats = [];
        foreach ($part_element['options'] as $option) {
          $formats[$option] = $this->t('@label (@format)', [
            '@label' => $option,
            '@format' => $time->format($option),
          ]);
        }
        $label = $part_element['label'];
        $elements['time_format']['time_format_parts'][$key] = [
          'label' => [
            '#markup' => $label,
          ],
          'format' => [
            '#type' => 'select',
            '#title' => $this->t('Format'),
            '#title_display' => 'invisible',
            '#options' => $formats,
            '#empty_option' => $this->t('- None -'),
            '#default_value' => $part['format'],
            '#attributes' => [
              'data-drupal-date-format-source' => 'format',
            ],
          ],
          'suffix' => [
            '#type' => 'textfield',
            '#title' => $this->t('Suffix'),
            '#title_display' => 'invisible',
            '#maxlength' => 10,
            '#size' => 5,
            '#default_value' => $part['suffix'],
            '#attributes' => [
              'data-drupal-date-format-source' => 'suffix',
            ],
          ],
          'weight' => [
            '#type' => 'weight',
            '#title' => $this->t('Weight for @title', ['@title' => $label]),
            '#title_display' => 'invisible',
            '#default_value' => $time_weight,
            '#attributes' => ['class' => [$field_name . '-time-format-parts-order']],
          ],
          '#weight' => $time_weight,
          '#attributes' => [
            'class' => ['draggable'],
          ],
        ];
        $time_weight++;
      }
      $elements['time_format']['date_first'] = [
        '#type' => 'select',
        '#title' => $this->t('First part shown'),
        '#description' => $this->t('Specify whether the date or time should be shown first.'),
        '#options' => [
          'date' => $this->t('Date'),
          'time' => $this->t('Time'),
        ],
        '#default_value' => $settings['date_first'],
        '#parents' => [...$parents, 'date_first'],
      ];
      $elements['time_format']['date_time_separator'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Date/time separator'),
        '#description' => $this->t('The string to separate dates and their associated times.'),
        '#default_value' => $settings['date_time_separator'],
        '#parents' => [...$parents, 'date_time_separator'],
      ];
    }

    // Attach JavaScript and date format samples.
    $elements['#attached']['drupalSettings']['dateFormats'] = $this->dateFormatter->getSampleDateFormats();
    $elements['#attached']['library'][] = 'custom_field/date_format_preview';

    return $elements;
  }

  /**
   * Validate date formats.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $form
   *   The form.
   */
  public static function validateDateFormatParts(array $element, FormStateInterface &$form_state, array $form): void {
    $value = $element['#value'];
    $name = end($element['#parents']);
    $all_empty = TRUE;
    if (!is_array($value)) {
      return;
    }
    foreach ($value as $key => $part) {
      if (!empty($part['format'])) {
        $all_empty = FALSE;
      }
      unset($value[$key]['weight']);
    }
    if ($name === 'date_format_parts' && $all_empty) {
      $form_state->setError($element, t('The date format is required.'));
    }

    $form_state->setValueForElement($element, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {

    /** @var \Drupal\Core\Datetime\DrupalDateTime $date */
    $date = $value['date'];

    if ($date === NULL) {
      return NULL;
    }

    $timezone = $this->getSetting('timezone_stored') ? $value['timezone'] : NULL;
    if ($this->getSetting('timezone_override')) {
      $timezone = $this->getSetting('timezone_override');
    }
    if ($this->getSetting('user_timezone') && !empty($timezone)) {
      return [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          $this->buildDate($date, $timezone),
          $this->buildDate($date),
        ],
      ];
    }

    return $this->buildDate($date, $timezone);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatDate(object $date, ?string $timezone): string {
    $datetime_type = $this->customFieldDefinition->getDatetimeType();
    $date_parts = $this->getFilteredDateParts();
    $time_parts = $datetime_type === DateTimeType::DATETIME_TYPE_DATETIME ? $this->getFilteredTimeParts() : [];
    $date_format = '';
    $time_format = '';
    $first_part = $this->getSetting('date_first');
    foreach ($date_parts as $date_part) {
      $date_format .= $date_part['format'];
      if ($date_part['suffix'] != '') {
        $date_format .= $date_part['suffix'];
      }
    }
    if (!empty($time_parts)) {
      foreach ($time_parts as $time_part) {
        $time_format .= $time_part['format'];
        if ($time_part['suffix'] != '') {
          $time_format .= $time_part['suffix'];
        }
      }
    }
    $format = $date_format;
    if (!empty($time_format)) {
      $date_time_separator = $this->getSetting('date_time_separator');
      if (!empty($date_time_separator)) {
        // Account for possible html entities.
        $date_time_separator = PlainTextOutput::renderFromHtml($date_time_separator);
      }
      if ($first_part === 'time') {
        $format = $time_format . $date_time_separator . $date_format;
      }
      else {
        $format = $date_format . $date_time_separator . $time_format;
      }
    }
    if ($this->getSetting('timezone_override')) {
      $timezone = $this->getSetting('timezone_override');
    }
    if (empty($timezone)) {
      $timezone = $date->getTimezone()->getName();
    }
    return $this->dateFormatter->format($date->getTimestamp(), 'custom', $format, $timezone != '' ? $timezone : NULL);
  }

}
