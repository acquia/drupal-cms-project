<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\PropWidget;
use Drupal\custom_field\Plugin\PropWidgetBase;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'image' widget.
 */
#[PropWidget(
  id: 'image',
  prop_type: 'object',
  label: new TranslatableMarkup('Image'),
)]
class PropWidgetImage extends PropWidgetBase {

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
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected ImageFactory $imageFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->elementInfo = $container->get('element_info');
    $instance->renderer = $container->get('renderer');
    $instance->imageFactory = $container->get('image.factory');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'properties' => [
        'src' => [
          'title' => 'Image URL',
          'description' => 'Relative or absolute image URL, local (internal) or remote (external), or even a stream wrapper image URI.',
          'type' => 'string',
          'format' => 'uri-reference',
          'contentMediaType' => 'image/*',
        ],
        'alt' => [
          'title' => 'Alternative text',
          'type' => 'string',
        ],
        'width' => [
          'title' => 'Image width',
          'type' => 'integer',
        ],
        'height' => [
          'title' => 'Image height',
          'type' => 'integer',
        ],
        'loading' => [
          'title' => 'Loading behavior',
          'type' => 'string',
          'default' => 'lazy',
          'enum' => ['lazy', 'eager'],
        ],
      ],
      'required' => ['src'],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(string $property, mixed $value): array {
    if (!\is_array($value)) {
      return parent::settingsSummary($property, $value);
    }

    $summary = [
      $this->t('@space@property:', [
        '@space' => $this->space(),
        '@property' => $property,
      ]),
    ];
    foreach ($value as $key => $item) {
      if (in_array($key, ['src', 'alt', 'width', 'height'])) {
        $summary[] = $this->t('@space@key: @value', [
          '@space' => $this->space(4),
          '@key' => $key,
          '@value' => $item,
        ]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value, $required): array {
    $element = parent::widget($form, $form_state, $value, $required);
    $element_info = $this->elementInfo->getInfo('managed_file');
    $supported_extensions = $this->imageFactory->getSupportedExtensions();
    $max_filesize = Bytes::toNumber(Environment::getUploadMaxSize());
    $value = $value['value'] ?? $value;
    if (!\is_array($value)) {
      $value = [];
    }
    $element['value'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Image'),
      '#theme' => 'image_widget',
      '#upload_location' => 'public://custom_field_sdc/',
      // Add image validation.
      '#upload_validators' => [
        'FileIsImage' => [],
        'FileExtension' => [
          'extensions' => implode(' ', $supported_extensions),
        ],
        'FileSizeLimit' => [
          'fileLimit' => $max_filesize,
        ],
      ],
      '#value_callback' => [static::class, 'value'],
      '#extended' => TRUE,
      '#multiple' => FALSE,
      '#cardinality' => 1,
      '#process' => array_merge($element_info['#process'], [[static::class, 'process']]),
      '#accept' => 'image/*',
      '#image_width' => $value['width'] ?? NULL,
      '#image_height' => $value['height'] ?? NULL,
      '#default_value' => [
        'fids' => $value['fids'] ?? [],
        'src' => $value['src'] ?? '',
        'alt' => $value['alt'] ?? '',
        'width' => 0,
        'height' => 0,
      ],
    ];

    $file_upload_help = [
      '#theme' => 'file_upload_help',
      '#upload_validators' => $element['value']['#upload_validators'],
      '#cardinality' => 1,
    ];
    $element['value']['#description'] = $this->renderer->renderInIsolation($file_upload_help);

    return self::process($element, $form_state, $form);
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
    $item = $element['#value'] ?? [];
    if (!empty($element['#files'])) {
      $file = reset($element['#files']);
      $variables = [
        'style_name' => 'thumbnail',
        'uri' => $file->getFileUri(),
      ];
      $dimension_key = $variables['uri'] . '.image_preview_dimensions';
      if (!empty($element['#image_width']) && !empty($element['#image_height'])) {
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
      $element['src'] = [
        '#type' => 'value',
        '#value' => $variables['uri'],
      ];
      $element['height'] = [
        '#type' => 'value',
        '#value' => $variables['height'] ?? 0,
      ];
      $element['width'] = [
        '#type' => 'value',
        '#value' => $variables['width'] ?? 0,
      ];
      $element['#description'] = NULL;
    }
    $element['alt'] = [
      '#title' => new TranslatableMarkup('Alternative text'),
      '#type' => 'textfield',
      '#default_value' => $item['alt'] ?? '',
      '#description' => new TranslatableMarkup('Short description of the image used by screen readers and displayed when the image is not loaded. This is important for accessibility.'),
      // @see https://www.drupal.org/node/465106#alt-text
      '#maxlength' => 512,
      '#weight' => -12,
      '#access' => !empty($element['#files']),
    ];

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
  public function massageValue(array $value): array {
    $value = [
      'value' => $value['value'] ?? [],
      'widget' => $this->getPluginId(),
    ];
    $fids = $value['value']['fids'] ?? NULL;
    if (empty($fids)) {
      $value['value'] = NULL;
      return $value;
    }
    if (is_string($fids)) {
      $fids = [(int) $fids];
    }
    $fid = reset($fids);
    try {
      $file = File::load($fid);
    }
    catch (\Exception $e) {
      $value['value'] = NULL;
      return $value;
    }
    if ($file->isTemporary()) {
      $file->setPermanent();
      $file->save();
    }

    $value['value'] = [
      'fids' => $fids,
      'src' => $file->createFileUrl(),
      'alt' => $value['value']['alt'] ?? '',
      'width' => $value['value']['width'] ?? 0,
      'height' => $value['value']['height'] ?? 0,
    ];

    return $value;
  }

}
