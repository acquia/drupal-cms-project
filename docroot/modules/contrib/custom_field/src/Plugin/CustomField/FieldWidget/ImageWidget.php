<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\file\Element\ManagedFile;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'image_image' widget.
 */
#[CustomFieldWidget(
  id: 'image_image',
  label: new TranslatableMarkup('Image'),
  category: new TranslatableMarkup('General'),
  field_types: [
    'image',
  ],
)]
class ImageWidget extends FileWidget {

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected ImageFactory $imageFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->imageFactory = $container->get('image.factory');
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'preview_image_style' => ImageStyle::load('thumbnail') ? 'thumbnail' : '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['preview_image_style'] = [
      '#title' => $this->t('Preview image style'),
      '#type' => 'select',
      '#options' => image_style_options(FALSE),
      '#empty_option' => '<' . $this->t('no preview') . '>',
      '#default_value' => $settings['preview_image_style'],
      '#description' => $this->t('The preview image will be shown while editing the content.'),
      '#weight' => 15,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    /** @var \Drupal\custom_field\Plugin\Field\FieldType\CustomItem $item */
    $item = $items[$delta];
    $name = $field->getName();
    $fid = $item->{$name};
    $settings = $this->getSettings() + static::defaultSettings();
    $field_settings = $field->getFieldSettings();
    // Account for temporary storage settings.
    $current_settings = $form_state->get('current_settings');
    if (!empty($current_settings)) {
      $uri_scheme = $current_settings['columns'][$name]['uri_scheme'] ?? 'public';
    }
    else {
      $uri_scheme = $field->getSetting('uri_scheme');
    }

    $field_settings['uri_scheme'] = $uri_scheme;
    $is_config_form = $form_state->getBuildInfo()['base_form_id'] == 'field_config_form';

    // Add image validation.
    $element['#upload_validators']['FileIsImage'] = [];

    // Add upload resolution validation.
    if ($field_settings['max_resolution'] || $field_settings['min_resolution']) {
      $element['#upload_validators']['FileImageDimensions'] = [
        'maxDimensions' => $field_settings['max_resolution'],
        'minDimensions' => $field_settings['min_resolution'],
      ];
    }

    $extensions = $field_settings['file_extensions'];
    $supported_extensions = $this->imageFactory->getSupportedExtensions();

    // If using custom extension validation, ensure that the extensions are
    // supported by the current image toolkit. Otherwise, validate against all
    // toolkit supported extensions.
    $extensions = !empty($extensions) ? array_intersect(explode(' ', $extensions), $supported_extensions) : $supported_extensions;
    $element['#upload_validators']['FileExtension']['extensions'] = implode(' ', $extensions);

    // Add mobile device image capture acceptance.
    $element['#accept'] = 'image/*';

    // Add properties needed by process() method.
    $element['#image_width'] = $item->{$name . '__width'} ?? NULL;
    $element['#image_height'] = $item->{$name . '__height'} ?? NULL;
    $element['#title_field'] = $field_settings['title_field'];
    $element['#title_field_required'] = !$is_config_form && $field_settings['title_field_required'];
    $element['#alt_field'] = $field_settings['alt_field'];
    $element['#alt_field_required'] = !$is_config_form && $field_settings['alt_field_required'];
    $element['#preview_image_style'] = $settings['preview_image_style'];
    $element['#default_value'] = [
      'fids' => [],
      'alt' => $item->{$name . '__alt'} ?? NULL,
      'title' => $item->{$name . '__title'} ?? NULL,
    ];

    if (!empty($fid)) {
      if (is_array($fid) && isset($fid['fids'])) {
        $fid = reset($fid['fids']);
      }
      $element['#default_value']['fids'] = [$fid];
    }

    return parent::process($element, $form_state, $form);
  }

  /**
   * Form API callback: Processes an image_image field element.
   *
   * Expands the image_image type to include the alt and title fields.
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
  public static function process($element, FormStateInterface $form_state, $form): array {
    $item = $element['#value'];

    $element['#theme'] = 'image_widget';

    // Add the image preview.
    if (!empty($element['#files']) && $element['#preview_image_style']) {
      $file = reset($element['#files']);
      $variables = [
        'style_name' => $element['#preview_image_style'],
        'uri' => $file->getFileUri(),
      ];

      $dimension_key = $variables['uri'] . '.image_preview_dimensions';
      // Determine image dimensions.
      if (isset($element['#image_width']) && isset($element['#image_height'])) {
        $variables['width'] = $element['#image_width'];
        $variables['height'] = $element['#image_height'];
      }
      elseif ($form_state->has($dimension_key)) {
        $variables += $form_state->get($dimension_key);
      }
      else {
        $image = \Drupal::service('image.factory')->get($file->getFileUri());
        if ($image->isValid()) {
          $variables['width'] = $image->getWidth();
          $variables['height'] = $image->getHeight();
        }
        else {
          $variables['width'] = $variables['height'] = NULL;
        }
      }

      $element['preview'] = [
        '#weight' => -10,
        '#theme' => 'image_style',
        '#width' => $variables['width'],
        '#height' => $variables['height'],
        '#style_name' => $variables['style_name'],
        '#uri' => $variables['uri'],
      ];

      // Store the dimensions in the form so the file doesn't have to be
      // accessed again. This is important for remote files.
      $form_state->set($dimension_key, [
        'width' => $variables['width'],
        'height' => $variables['height'],
      ]);
    }

    // Add the additional alt and title fields.
    $element['alt'] = [
      '#title' => new TranslatableMarkup('Alternative text'),
      '#type' => 'textfield',
      '#default_value' => $item['alt'] ?? '',
      '#description' => new TranslatableMarkup('Short description of the image used by screen readers and displayed when the image is not loaded. This is important for accessibility.'),
      // @see https://www.drupal.org/node/465106#alt-text
      '#maxlength' => 512,
      '#weight' => -12,
      '#access' => (bool) $element['#files'] && $element['#alt_field'],
      '#required' => $element['#alt_field_required'],
      '#element_validate' => $element['#alt_field_required'] == 1 ? [[static::class, 'validateRequiredFields']] : [],
    ];
    $element['title'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Title'),
      '#default_value' => $item['title'] ?? '',
      '#description' => new TranslatableMarkup('The title is used as a tool tip when the user hovers the mouse over the image.'),
      '#maxlength' => 1024,
      '#weight' => -11,
      '#access' => (bool) $element['#files'] && $element['#title_field'],
      '#required' => $element['#title_field_required'],
      '#element_validate' => $element['#title_field_required'] == 1 ? [[static::class, 'validateRequiredFields']] : [],
    ];

    return $element;
  }

  /**
   * Validate callback for alt and title field, if the user wants them required.
   *
   * This is separated in a validate function instead of a #required flag to
   * avoid being validated on the process callback.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateRequiredFields(array $element, FormStateInterface $form_state): void {
    // Only do validation if the function is triggered from other places than
    // the image process form.
    $triggering_element = $form_state->getTriggeringElement();
    if (!empty($triggering_element['#submit'])) {
      $needle = [ManagedFile::class, 'submit'];
      if (version_compare(\Drupal::VERSION, '11.3', '<')) {
        $needle = 'file_managed_file_submit';
      }
      if (in_array($needle, $triggering_element['#submit'], TRUE)) {
        $form_state->setLimitValidationErrors([]);
      }
    }
  }

  /**
   * Form API callback. Retrieves the value for the file_generic field element.
   *
   * This method is assigned as a #value_callback in formElement() method.
   */
  public static function value($element, $input, FormStateInterface $form_state): mixed {
    // Account for field config default values form initial state.
    if ($input == "") {
      return $element['#default_value'];
    }
    // We depend on the managed file element to handle uploads.
    return ManagedFile::valueCallback($element, $input, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateWidgetDependencies(): array {
    $dependencies = parent::calculateWidgetDependencies();
    $settings = $this->getSettings();
    $style_id = $settings['preview_image_style'] ?? NULL;
    if ($style_id && $style = ImageStyle::load($style_id)) {
      // If this widget uses a valid image style to display the preview of the
      // uploaded image, add that image style configuration entity as dependency
      // of this widget.
      $dependencies[$style->getConfigDependencyKey()][] = $style->getConfigDependencyName();
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onWidgetDependencyRemoval(array $dependencies): array {
    $settings = $this->getSettings();
    $changed = FALSE;
    $changed_settings = [];
    $style_id = $settings['preview_image_style'];
    if ($style_id && $style = ImageStyle::load($style_id)) {
      if (!empty($dependencies[$style->getConfigDependencyKey()][$style->getConfigDependencyName()])) {
        /** @var \Drupal\image\ImageStyleStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage($style->getEntityTypeId());
        $replacement_id = $storage->getReplacementId($style_id);
        // If a valid replacement has been provided in the storage, replace the
        // preview image style with the replacement.
        if ($replacement_id && ImageStyle::load($replacement_id)) {
          $settings['preview_image_style'] = $replacement_id;
        }
        else {
          $settings['preview_image_style'] = '';
        }
        $changed = TRUE;
      }
    }
    if ($changed) {
      $changed_settings = $settings;
    }

    return $changed_settings;
  }

}
