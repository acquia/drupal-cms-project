<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'telephone' field type.
 */
#[CustomFieldType(
  id: 'telephone',
  label: new TranslatableMarkup('Telephone number'),
  description: new TranslatableMarkup('This field stores a telephone number in the database.'),
  category: new TranslatableMarkup('General'),
  default_widget: 'telephone',
  default_formatter: 'telephone_link',
)]
class TelephoneType extends StringType {

  /**
   * The default max length for telephone fields.
   *
   * @var int
   */
  const MAX_LENGTH = 256;

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'pattern' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();

    $element['pattern'] = [
      '#type' => 'select',
      '#title' => $this->t('Telephone format'),
      '#options' => $this->getTelephoneFormatOptions(),
      '#default_value' => $settings['pattern'],
      '#empty_option' => $this->t('- Select -'),
      '#description' => $this->t('A pattern to enforce  on the input.'),
    ];

    return $element;
  }

  /**
   * Helper function to get telephone formats for various countries.
   *
   * @return array<string, array{label: string, format: string, regex: string, pattern: string}>
   *   An array of common telephone formats.
   */
  public function getTelephoneFormats(): array {
    return [
      'AU' => [
        'label' => 'Australia',
        'format' => 'xx xxxx xxxx',
        'regex' => '/^[0-9]{2} [0-9]{4} [0-9]{4}$/',
        'pattern' => '[0-9]{2} [0-9]{4} [0-9]{4}',
      ],
      'BR' => [
        'label' => 'Brazil',
        'format' => '(xx) xxxx-xxxx',
        'regex' => '/^\([0-9]{2}\) [0-9]{4}-[0-9]{4}$/',
        'pattern' => '\([0-9]{2}\) [0-9]{4}-[0-9]{4}',
      ],
      'CA' => [
        'label' => 'Canada',
        'format' => 'xxx-xxx-xxxx',
        'regex' => '/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/',
        'pattern' => '[0-9]{3}-[0-9]{3}-[0-9]{4}',
      ],
      'CN' => [
        'label' => 'China',
        'format' => '0xx-xxxx-xxxx',
        'regex' => '/^0[0-9]{2}-[0-9]{4}-[0-9]{4}$/',
        'pattern' => '0[0-9]{2}-[0-9]{4}-[0-9]{4}',
      ],
      'FR' => [
        'label' => 'France',
        'format' => '0x xx xx xx xx',
        'regex' => '/^0[0-9] [0-9]{2} [0-9]{2} [0-9]{2} [0-9]{2}$/',
        'pattern' => '0[0-9] [0-9]{2} [0-9]{2} [0-9]{2} [0-9]{2}',
      ],
      'DE' => [
        'label' => 'Germany',
        'format' => '0xxx xxxxxxx',
        'regex' => '/^0[0-9]{3} [0-9]{7}$/',
        'pattern' => '0[0-9]{3} [0-9]{7}',
      ],
      'IN' => [
        'label' => 'India',
        'format' => 'xxxxx-xxxxx',
        'regex' => '/^[0-9]{5}-[0-9]{5}$/',
        'pattern' => '[0-9]{5}-[0-9]{5}',
      ],
      'JP' => [
        'label' => 'Japan',
        'format' => '0xx-xxx-xxxx',
        'regex' => '/^0[0-9]{2}-[0-9]{3}-[0-9]{4}$/',
        'pattern' => '0[0-9]{2}-[0-9]{3}-[0-9]{4}',
      ],
      'MX' => [
        'label' => 'Mexico',
        'format' => '01 (xxx) xxx-xxxx',
        'regex' => '/^01 \([0-9]{3}\) [0-9]{3}-[0-9]{4}$/',
        'pattern' => '01 \([0-9]{3}\) [0-9]{3}-[0-9]{4}',
      ],
      'ZA' => [
        'label' => 'South Africa',
        'format' => '0xx xxx xxxx',
        'regex' => '/^0[0-9]{2} [0-9]{3} [0-9]{4}$/',
        'pattern' => '0[0-9]{2} [0-9]{3} [0-9]{4}',
      ],
      'ES' => [
        'label' => 'Spain',
        'format' => '9xx xx xx xx',
        'regex' => '/^9[0-9]{2} [0-9]{2} [0-9]{2} [0-9]{2}$/',
        'pattern' => '9[0-9]{2} [0-9]{2} [0-9]{2} [0-9]{2}',
      ],
      'GB' => [
        'label' => 'United Kingdom',
        'format' => 'xxxx-xxx-xxxx',
        'regex' => '/^[0-9]{4}-[0-9]{3}-[0-9]{4}$/',
        'pattern' => '[0-9]{4}-[0-9]{3}-[0-9]{4}',
      ],
      'US' => [
        'label' => 'United States',
        'format' => 'xxx-xxx-xxxx',
        'regex' => '/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/',
        'pattern' => '[0-9]{3}-[0-9]{3}-[0-9]{4}',
      ],
    ];
  }

  /**
   * Helper function to return telephone format options.
   *
   * @return array<string, mixed>
   *   An array of telephone format options.
   */
  protected function getTelephoneFormatOptions(): array {
    return array_map(function ($option) {
      return $this->t('@label: @format', [
        '@label' => $option['label'],
        '@format' => $option['format'],
      ]);
    }, $this->getTelephoneFormats());
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): string {
    $area_code = mt_rand(100, 999);
    $prefix = mt_rand(100, 999);
    $line_number = mt_rand(1000, 9999);

    return "$area_code-$prefix-$line_number";
  }

}
