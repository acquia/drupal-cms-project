<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;
use Drupal\file\Entity\File;

/**
 * Plugin implementation of the 'image' field type.
 */
#[CustomFieldType(
  id: 'image',
  label: new TranslatableMarkup('Image'),
  description: [
    new TranslatableMarkup("For uploading images"),
    new TranslatableMarkup("Allows a user to upload an image with configurable extensions, image dimensions, upload size"),
    new TranslatableMarkup(
      "Can be configured with options such as allowed file extensions, maximum upload size and image dimensions minimums/maximums"
    ),
  ],
  category: new TranslatableMarkup('File upload'),
  default_widget: 'image_image',
  default_formatter: 'image',
  constraints: [
    'ReferenceAccess' => [],
    'FileValidation' => [],
  ],
)]
class ImageType extends FileType {

  /**
   * The default file extensions for generateSampleValue().
   *
   * @var string
   */
  const DEFAULT_FILE_EXTENSIONS = 'png gif jpg jpeg';

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $height = $name . self::SEPARATOR . 'height';
    $width = $name . self::SEPARATOR . 'width';
    $alt = $name . self::SEPARATOR . 'alt';
    $title = $name . self::SEPARATOR . 'title';

    $columns[$name] = [
      'description' => 'The ID of the file entity.',
      'type' => 'int',
      'unsigned' => TRUE,
    ];
    $columns[$width] = [
      'description' => 'The width of the image in pixels.',
      'type' => 'int',
      'unsigned' => TRUE,
    ];
    $columns[$height] = [
      'description' => 'The height of the image in pixels.',
      'type' => 'int',
      'unsigned' => TRUE,
    ];
    $columns[$alt] = [
      'description' => "Alternative image text, for the image's 'alt' attribute.",
      'type' => 'varchar',
      'length' => 512,
    ];
    $columns[$title] = [
      'description' => "Image title text, for the image's 'title' attribute.",
      'type' => 'varchar',
      'length' => 1024,
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name, 'target_type' => $target_type] = $settings;
    $target_type_info = \Drupal::entityTypeManager()->getDefinition($target_type);

    $height = $name . self::SEPARATOR . 'height';
    $width = $name . self::SEPARATOR . 'width';
    $alt = $name . self::SEPARATOR . 'alt';
    $title = $name . self::SEPARATOR . 'title';

    $properties[$name] = CustomFieldDataDefinition::create('custom_field_image')
      ->setLabel(new TranslatableMarkup('@label ID', ['@label' => $name]))
      ->setSetting('unsigned', TRUE)
      ->setSetting('target_type', $target_type)
      ->setRequired(FALSE);

    $properties[$width] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('@label width', ['@label' => $name]))
      ->setDescription(new TranslatableMarkup('The width of the image in pixels.'))
      ->setInternal(TRUE);

    $properties[$height] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('@label height', ['@label' => $name]))
      ->setDescription(new TranslatableMarkup('The height of the image in pixels.'))
      ->setInternal(TRUE);

    $properties[$alt] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('@label alternative text', ['@label' => $name]))
      ->setDescription(new TranslatableMarkup("Alternative image text, for the image's 'alt' attribute."))
      ->setInternal(TRUE);

    $properties[$title] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('@label title', ['@label' => $name]))
      ->setDescription(new TranslatableMarkup("Image title text, for the image's 'title' attribute."))
      ->setInternal(TRUE);

    $properties[$name . self::SEPARATOR . 'entity'] = DataReferenceDefinition::create('entity')
      ->setLabel($target_type_info->getLabel())
      ->setDescription(new TranslatableMarkup('The referenced entity'))
      ->setComputed(TRUE)
      ->setSettings(['target_id' => $name, 'target_type' => $target_type])
      ->setClass('\Drupal\custom_field\Plugin\CustomField\EntityReferenceComputed')
      ->setReadOnly(FALSE)
      ->setTargetDefinition(EntityDataDefinition::create($target_type))
      ->addConstraint('EntityType', $target_type);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'file_extensions' => 'png gif jpg jpeg webp',
      'alt_field' => 1,
      'alt_field_required' => 1,
      'title_field' => 0,
      'title_field_required' => 0,
      'max_resolution' => '',
      'min_resolution' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings() + static::defaultFieldSettings();

    // Add maximum and minimum resolution settings.
    $max_resolution = explode('x', $settings['max_resolution']) + ['', ''];
    $element['max_resolution'] = [
      '#type' => 'item',
      '#title' => $this->t('Maximum image resolution'),
      '#element_validate' => [[static::class, 'validateResolution']],
      '#weight' => 4.1,
      '#description' => $this->t('The maximum allowed image size expressed as WIDTH×HEIGHT (e.g. 640×480). Leave blank for no restriction. If a larger image is uploaded, it will be resized to reflect the given width and height. Resizing images on upload will cause the loss of <a href="http://wikipedia.org/wiki/Exchangeable_image_file_format">EXIF data</a> in the image.'),
    ];
    $element['max_resolution']['x'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum width'),
      '#title_display' => 'invisible',
      '#default_value' => $max_resolution[0],
      '#min' => 1,
      '#field_suffix' => ' × ',
      '#prefix' => '<div class="form--inline clearfix">',
    ];
    $element['max_resolution']['y'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum height'),
      '#title_display' => 'invisible',
      '#default_value' => $max_resolution[1],
      '#min' => 1,
      '#field_suffix' => ' ' . $this->t('pixels'),
      '#suffix' => '</div>',
    ];

    $min_resolution = explode('x', $settings['min_resolution']) + ['', ''];
    $element['min_resolution'] = [
      '#type' => 'item',
      '#title' => $this->t('Minimum image resolution'),
      '#element_validate' => [[static::class, 'validateResolution']],
      '#weight' => 4.2,
      '#description' => $this->t('The minimum allowed image size expressed as WIDTH×HEIGHT (e.g. 640×480). Leave blank for no restriction. If a smaller image is uploaded, it will be rejected.'),
    ];
    $element['min_resolution']['x'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum width'),
      '#title_display' => 'invisible',
      '#default_value' => $min_resolution[0],
      '#min' => 1,
      '#field_suffix' => ' × ',
      '#prefix' => '<div class="form--inline clearfix">',
    ];
    $element['min_resolution']['y'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum height'),
      '#title_display' => 'invisible',
      '#default_value' => $min_resolution[1],
      '#min' => 1,
      '#field_suffix' => ' ' . $this->t('pixels'),
      '#suffix' => '</div>',
    ];
    // Add title and alt configuration options.
    $element['alt_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable <em>Alt</em> field'),
      '#default_value' => $settings['alt_field'],
      '#description' => $this->t('Short description of the image used by screen readers and displayed when the image is not loaded. Enabling this field is recommended.'),
      '#weight' => 9,
    ];
    $element['alt_field_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>Alt</em> field required'),
      '#default_value' => $settings['alt_field_required'],
      '#description' => $this->t('Making this field required is recommended.'),
      '#weight' => 10,
      '#states' => [
        'visible' => [
          ':input[name="settings[field_settings][' . $this->getName() . '][alt_field]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['title_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable <em>Title</em> field'),
      '#default_value' => $settings['title_field'],
      '#description' => $this->t('The title attribute is used as a tooltip when the mouse hovers over the image. Enabling this field is not recommended as it can cause problems with screen readers.'),
      '#weight' => 11,
    ];
    $element['title_field_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>Title</em> field required'),
      '#default_value' => $settings['title_field_required'],
      '#weight' => 12,
      '#states' => [
        'visible' => [
          ':input[name="settings[field_settings][' . $this->getName() . '][title_field]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $element;
  }

  /**
   * Element validate function for resolution fields.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateResolution(array $element, FormStateInterface $form_state): void {
    if (!empty($element['x']['#value']) || !empty($element['y']['#value'])) {
      foreach (['x', 'y'] as $dimension) {
        if (!$element[$dimension]['#value']) {
          // We expect the field name placeholder value to be wrapped in
          // $this->t() here, so it won't be escaped again as it's already
          // marked safe.
          $form_state->setError($element[$dimension], new TranslatableMarkup('Both a height and width value must be specified in the @name field.', ['@name' => $element['#title']]));
          return;
        }
      }
      $form_state->setValueForElement($element, $element['x']['#value'] . 'x' . $element['y']['#value']);
    }
    else {
      $form_state->setValueForElement($element, '');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): mixed {
    $random = new Random();
    $settings = $field->getFieldSettings();
    $file_extensions = $settings['file_extensions'] ?? self::DEFAULT_FILE_EXTENSIONS;
    static $images = [];

    $settings['uri_scheme'] = $field->getSetting('uri_scheme');
    $min_resolution = empty($settings['min_resolution']) ? '100x100' : $settings['min_resolution'];
    $max_resolution = empty($settings['max_resolution']) ? '600x600' : $settings['max_resolution'];
    $extensions = array_intersect(explode(' ', $file_extensions), ['png', 'gif', 'jpg', 'jpeg']);
    $extension = array_rand(array_combine($extensions, $extensions));

    $min = explode('x', $min_resolution);
    $max = explode('x', $max_resolution);
    if (intval($min[0]) > intval($max[0])) {
      $max[0] = $min[0];
    }
    if (intval($min[1]) > intval($max[1])) {
      $max[1] = $min[1];
    }
    $max_resolution = "$max[0]x$max[1]";

    // Generate a max of 5 different images.
    if (!isset($images[$extension][$min_resolution][$max_resolution]) || count($images[$extension][$min_resolution][$max_resolution]) <= 5) {
      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');
      $tmp_file = (string) $file_system->tempnam('temporary://', 'generateImage_');
      $destination = $tmp_file . '.' . $extension;
      try {
        $file_system->move($tmp_file, $destination);
      }
      catch (FileException $e) {
        // Ignore failed move.
      }
      if ($path = $random->image((string) $file_system->realpath($destination), $min_resolution, $max_resolution)) {
        $image = File::create();
        $image->setFileUri($path);
        $image->setOwnerId(\Drupal::currentUser()->id());
        $guesser = \Drupal::service('file.mime_type.guesser');
        $image->setMimeType($guesser->guessMimeType($path));
        $image->setFileName(\basename($path));
        $destination_dir = static::doGetUploadLocation($settings);
        $file_system->prepareDirectory($destination_dir, FileSystemInterface::CREATE_DIRECTORY);
        // Ensure directory ends with a slash.
        $destination_dir .= str_ends_with($destination_dir, '/') ? '' : '/';
        $destination = $destination_dir . \basename($path);
        $file = \Drupal::service('file.repository')->move($image, $destination);
        $images[$extension][$min_resolution][$max_resolution][$file->id()] = $file;
      }
      else {
        return [];
      }
    }
    else {
      // Select one of the images we've already generated for this field.
      $image_index = array_rand($images[$extension][$min_resolution][$max_resolution]);
      $file = $images[$extension][$min_resolution][$max_resolution][$image_index];
    }

    /** @var int[] $image_info */
    $image_info = getimagesize($file->getFileUri());
    [$width, $height] = $image_info;

    $value = [
      'target_id' => $file->id(),
      'alt' => $random->sentences(4),
      'title' => $random->sentences(4),
      'width' => $width,
      'height' => $height,
    ];

    return $value;
  }

}
