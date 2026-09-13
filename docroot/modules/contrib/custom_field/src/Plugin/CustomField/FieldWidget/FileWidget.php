<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomField\FieldType\FileType;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;
use Drupal\file\Element\ManagedFile;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'file_generic' widget.
 */
#[CustomFieldWidget(
  id: 'file_generic',
  label: new TranslatableMarkup('File'),
  category: new TranslatableMarkup('General'),
  field_types: [
    'file',
  ],
)]
class FileWidget extends CustomFieldWidgetBase {

  /**
   * Collects available render array element types.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected ElementInfoManagerInterface $elementInfo;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->elementInfo = $container->get('element_info');
    $instance->renderer = $container->get('renderer');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'progress_indicator' => 'throbber',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);

    $element['progress_indicator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Progress indicator'),
      '#options' => [
        'throbber' => $this->t('Throbber'),
        'bar' => $this->t('Bar with progress meter'),
      ],
      '#default_value' => $this->getSetting('progress_indicator'),
      '#description' => $this->t('The throbber display does not show the status of uploads but takes up less space. The progress bar is helpful for monitoring progress on large uploads.'),
      '#weight' => 16,
      '#access' => extension_loaded('uploadprogress'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    assert($field instanceof FileType);
    /** @var \Drupal\custom_field\Plugin\Field\FieldType\CustomItem $item */
    $item = $items[$delta];
    $fid = $item->{$field->getName()};
    $field_settings = $field->getFieldSettings();
    // Account for temporary storage settings.
    $current_settings = $form_state->get('current_settings');
    if (!empty($current_settings)) {
      $uri_scheme = $current_settings['columns'][$field->getName()]['uri_scheme'] ?? 'public';
    }
    else {
      $uri_scheme = $field->getSetting('uri_scheme');
    }

    $field_settings['uri_scheme'] = $uri_scheme;

    $defaults = [
      'fids' => [],
    ];
    if (!empty($fid)) {
      if (is_array($fid) && isset($fid['fids'])) {
        $fid = reset($fid['fids']);
      }
      $defaults['fids'][] = $fid;
    }

    // Essentially, we use the managed_file type, extended with some
    // enhancements.
    $element_info = $this->elementInfo->getInfo('managed_file');
    $element += [
      '#type' => 'managed_file',
      '#upload_location' => $field->getUploadLocation($field_settings),
      '#upload_validators' => $field->getUploadValidators(),
      '#value_callback' => [static::class, 'value'],
      '#process' => array_merge($element_info['#process'], [[static::class, 'process']]),
      '#progress_indicator' => $this->getSetting('progress_indicator'),
      '#extended' => TRUE,
      '#field_name' => $item->getFieldDefinition()->getName(),
      '#entity_type' => $items->getEntity()->getEntityTypeId(),
      '#multiple' => FALSE,
      '#cardinality' => 1,
    ];
    $element['#default_value'] = $defaults;

    if (empty($fid)) {
      $file_upload_help = [
        '#theme' => 'file_upload_help',
        '#description' => $element['#description'],
        '#upload_validators' => $element['#upload_validators'],
        '#cardinality' => 1,
      ];
      $element['#description'] = $this->renderer->renderInIsolation($file_upload_help);
    }

    return $element;
  }

  /**
   * Form API callback: Processes a file_generic field element.
   *
   * Expands the file_generic type to include the description and display
   * fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $form
   *   The form.
   *
   * @return array<string, mixed>
   *   The processed element.
   */
  public static function process(array $element, FormStateInterface $form_state, array $form): array {
    return $element;
  }

  /**
   * Form API callback. Retrieves the value for the file_generic field element.
   *
   * This method is assigned as a #value_callback in formElement() method.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param mixed $input
   *   The incoming input to populate the form element. If this is FALSE, the
   *   element's default value should be returned.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The value to return.
   */
  public static function value(array $element, mixed $input, FormStateInterface $form_state): mixed {
    // Account for field config default values form initial state.
    if ($input == "") {
      return $element['#default_value'];
    }
    // We depend on the managed file element to handle uploads.
    $return = ManagedFile::valueCallback($element, $input, $form_state);

    // Ensure that all the required properties are returned even if empty.
    $return += [
      'fids' => [],
    ];

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    $fids = $value['fids'] ?? NULL;
    if (empty($fids)) {
      return NULL;
    }
    $fid = reset($fids);
    $value['target_id'] = $fid;
    unset($value['fids']);

    return $value;
  }

}
