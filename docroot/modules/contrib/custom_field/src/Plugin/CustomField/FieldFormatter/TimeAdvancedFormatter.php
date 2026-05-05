<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Drupal\custom_field\Time;
use Drupal\custom_field\Trait\DateTimeAdvancedFormatterTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'time_range_default' formatter.
 */
#[FieldFormatter(
  id: 'time_advanced',
  label: new TranslatableMarkup('Advanced'),
  field_types: [
    'time',
  ],
)]
class TimeAdvancedFormatter extends CustomFieldFormatterBase {

  use DateTimeAdvancedFormatterTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dateFormatter = $container->get('date.formatter');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
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
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $field_name = $this->customFieldDefinition->getName();
    $parents = $form['#field_parents'];
    $settings = $this->getSettings() + static::defaultSettings();
    $time_parts = $settings['time_format_parts'];
    $time_part_elements = $this->getTimePartElements();
    $time = new DrupalDateTime();

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
      '#element_validate' => [[static::class, 'validateTimeFormatParts']],
    ];
    $weight = 0;
    foreach ($time_parts as $key => $part) {
      $part_element = $time_part_elements[$key];
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
          '#default_value' => $weight,
          '#attributes' => ['class' => [$field_name . '-time-format-parts-order']],
        ],
        '#weight' => $weight,
        '#attributes' => [
          'class' => ['draggable'],
        ],
      ];
      $weight++;
    }

    // Attach JavaScript and date format samples.
    $elements['#attached']['drupalSettings']['dateFormats'] = $this->dateFormatter->getSampleDateFormats();
    $elements['#attached']['library'][] = 'custom_field/date_format_preview';

    return $elements;
  }

  /**
   * Validate time formats.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $form
   *   The form.
   */
  public static function validateTimeFormatParts(array $element, FormStateInterface &$form_state, array $form): void {
    $value = $element['#value'];
    if (!is_array($value)) {
      return;
    }
    $all_empty = TRUE;
    foreach ($value as $key => $part) {
      if (!empty($part['format'])) {
        $all_empty = FALSE;
      }
      unset($value[$key]['weight']);
    }
    if ($all_empty) {
      $form_state->setError($element, t('The time format is required.'));
    }

    $form_state->setValueForElement($element, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): array {
    $time = Time::createFromTimestamp($value);
    return $this->buildTimeWithIsoAttribute($time);
  }

  /**
   * Creates a render array from a time object with ISO date attribute.
   *
   * @param \Drupal\custom_field\Time $time
   *   A time object.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  protected function buildTimeWithIsoAttribute(Time $time): array {
    // Create the ISO time in Universal Time.
    $iso_time = $time->format("H:i");

    return [
      '#theme' => 'time',
      '#text' => $this->formatTime($time),
      '#attributes' => [
        'datetime' => $iso_time,
      ],
    ];
  }

  /**
   * Creates a formatted time value as string.
   *
   * @param \Drupal\custom_field\Time $time
   *   The time object.
   *
   * @return string
   *   The formatted time.
   */
  protected function formatTime(Time $time): string {
    $time_format_parts = $this->getFilteredTimeParts();
    $time_format = '';
    foreach ($time_format_parts as $value) {
      $time_format .= $value['format'];
      if ($value['suffix'] != '') {
        $time_format .= PlainTextOutput::renderFromHtml((string) $value['suffix']);
      }
    }

    return $time->format($time_format);
  }

}
