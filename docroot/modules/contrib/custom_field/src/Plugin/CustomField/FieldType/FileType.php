<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;

/**
 * Plugin implementation of the 'file' field type.
 */
#[CustomFieldType(
  id: 'file',
  label: new TranslatableMarkup('File'),
  description: [
    new TranslatableMarkup('For uploading files'),
    new TranslatableMarkup('Can be configured with options such as allowed file extensions and maximum upload size'),
  ],
  category: new TranslatableMarkup('File upload'),
  default_widget: 'file_generic',
  default_formatter: 'file_default',
  constraints: [
    'ReferenceAccess' => [],
    'FileValidation' => [],
  ],
)]
class FileType extends CustomFieldTypeBase {

  /**
   * The default file directory for generateSampleValue().
   *
   * @var string
   */
  const DEFAULT_FILE_DIRECTORY = '[date:custom:Y]-[date:custom:m]';

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'description' => 'The ID of the file entity.',
      'type' => 'int',
      'unsigned' => TRUE,
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name, 'target_type' => $target_type] = $settings;
    $target_type_info = \Drupal::entityTypeManager()->getDefinition($target_type);

    $properties[$name] = CustomFieldDataDefinition::create('custom_field_entity_reference')
      ->setLabel(new TranslatableMarkup('@label ID', ['@label' => $name]))
      ->setSetting('unsigned', TRUE)
      ->setSetting('target_type', $target_type)
      ->setRequired(FALSE);

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
   * Determines the URI for a file field.
   *
   * @param array<string, mixed> $settings
   *   An array of settings to pass to the function.
   * @param array<string, mixed> $data
   *   An array of token objects to pass to Token::replace().
   *
   * @return string
   *   An unsanitized file directory URI with tokens replaced. The result of
   *   the token replacement is then converted to plain text and returned.
   *
   * @see \Drupal\Core\Utility\Token::replace()
   */
  public function getUploadLocation(array $settings, array $data = []): string {
    return static::doGetUploadLocation($settings, $data);
  }

  /**
   * Retrieves the upload validators for a file field.
   *
   * @return array<string, mixed>
   *   An array suitable for passing to file_save_upload() or the file field
   *   element's '#upload_validators' property.
   */
  public function getUploadValidators(): array {
    $settings = $this->getFieldSettings() + static::defaultFieldSettings();
    $validators = [];

    // Cap the upload size according to the PHP limit.
    $max_filesize = Bytes::toNumber(Environment::getUploadMaxSize());
    if (!empty($settings['max_filesize'])) {
      $max_filesize = min($max_filesize, Bytes::toNumber($settings['max_filesize']));
    }

    // There is always a file size limit due to the PHP server limit.
    $validators['FileSizeLimit'] = ['fileLimit' => $max_filesize];

    // Add the extension check if necessary.
    if (!empty($settings['file_extensions'])) {
      $validators['FileExtension'] = ['extensions' => $settings['file_extensions']];
    }

    return $validators;
  }

  /**
   * Determines the URI for a file field.
   *
   * @param array<string, mixed> $settings
   *   The array of field settings.
   * @param array<string, mixed> $data
   *   An array of token objects to pass to Token::replace().
   *
   * @return string
   *   An unsanitized file directory URI with tokens replaced. The result of
   *   the token replacement is then converted to plain text and returned.
   *
   * @see \Drupal\Core\Utility\Token::replace()
   */
  protected static function doGetUploadLocation(array $settings, array $data = []): string {
    $file_directory = $settings['file_directory'] ?? self::DEFAULT_FILE_DIRECTORY;
    $destination = trim($file_directory, '/');

    // Replace tokens. As the tokens might contain HTML we convert it to plain
    // text.
    $destination = PlainTextOutput::renderFromHtml(\Drupal::token()->replace($destination, $data));
    return $settings['uri_scheme'] . '://' . $destination;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'file_extensions' => 'txt',
      'file_directory' => '[date:custom:Y]-[date:custom:m]',
      'max_filesize' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings() + static::defaultFieldSettings();
    $element['file_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File directory'),
      '#default_value' => $settings['file_directory'],
      '#description' => $this->t('Optional subdirectory within the upload destination where files will be stored. Do not include preceding or trailing slashes.'),
      '#element_validate' => [[static::class, 'validateDirectory']],
      '#weight' => 3,
    ];

    // Make the extension list a little more human-friendly by comma-separation.
    $extensions = str_replace(' ', ', ', $settings['file_extensions']);
    $element['file_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed file extensions'),
      '#default_value' => $extensions,
      '#description' => $this->t("Separate extensions with a comma or space. Each extension can contain alphanumeric characters, '.', and '_', and should start and end with an alphanumeric character."),
      '#element_validate' => [[static::class, 'validateExtensions']],
      '#weight' => 1,
      '#maxlength' => 256,
      // By making this field required, we prevent a potential security issue
      // that would allow files of any type to be uploaded.
      '#required' => TRUE,
    ];

    $element['max_filesize'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum upload size'),
      '#default_value' => $settings['max_filesize'],
      '#description' => $this->t('Enter a value like "512" (bytes), "80 KB" (kilobytes) or "50 MB" (megabytes) in order to restrict the allowed file size. If left empty the file sizes could be limited only by PHP\'s maximum post and file upload sizes (current limit <strong>%limit</strong>).', [
        '%limit' => ByteSizeMarkup::create(Environment::getUploadMaxSize()),
      ]),
      '#size' => 10,
      '#element_validate' => [[static::class, 'validateMaxFilesize']],
      '#weight' => 5,
    ];

    return $element;
  }

  /**
   * Form API callback.
   *
   * Removes slashes from the beginning and end of the destination value and
   * ensures that the file directory path is not included at the beginning of
   * the value.
   *
   * This function is assigned as an #element_validate callback in
   * fieldSettingsForm().
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateDirectory(array $element, FormStateInterface $form_state): void {
    // Strip slashes from the beginning and end of $element['file_directory'].
    $value = trim($element['#value'], '\\/');
    $form_state->setValueForElement($element, $value);
  }

  /**
   * Form API callback.
   *
   * This function is assigned as an #element_validate callback in
   * fieldSettingsForm().
   *
   * This doubles as a convenience clean-up function and a validation routine.
   * Commas are allowed by the end-user, but ultimately the value will be stored
   * as space-separated list for compatibility with file_validate_extensions().
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateExtensions(array $element, FormStateInterface $form_state): void {
    if (!empty($element['#value'])) {
      $extensions = preg_replace('/([, ]+\.?)/', ' ', trim(strtolower($element['#value'])));
      $extension_array = array_unique(array_filter(explode(' ', $extensions)));
      $extensions = implode(' ', $extension_array);
      if (!preg_match('/^([a-z0-9]+([._][a-z0-9])* ?)+$/', $extensions)) {
        $form_state->setError($element, new TranslatableMarkup("The list of allowed extensions is not valid. Allowed characters are a-z, 0-9, '.', and '_'. The first and last characters cannot be '.' or '_', and these two characters cannot appear next to each other. Separate extensions with a comma or space."));
      }
      else {
        $form_state->setValueForElement($element, $extensions);
      }

      // If insecure uploads are not allowed and txt is not in the list of
      // allowed extensions, ensure that no insecure extensions are allowed.
      if (!in_array('txt', $extension_array, TRUE) && !\Drupal::config('system.file')->get('allow_insecure_uploads')) {
        foreach ($extension_array as $extension) {
          if (preg_match(FileSystemInterface::INSECURE_EXTENSION_REGEX, 'test.' . $extension)) {
            $form_state->setError($element, new TranslatableMarkup('Add %txt_extension to the list of allowed extensions to securely upload files with a %extension extension. The %txt_extension extension will then be added automatically.', [
              '%extension' => $extension,
              '%txt_extension' => 'txt',
            ]));

            break;
          }
        }
      }
    }
  }

  /**
   * Form API callback.
   *
   * Ensures that a size has been entered and that it can be parsed by
   * \Drupal\Component\Utility\Bytes::toNumber().
   *
   * This function is assigned as an #element_validate callback in
   * fieldSettingsForm().
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateMaxFilesize(array $element, FormStateInterface $form_state): void {
    $element['#value'] = trim($element['#value']);
    $form_state->setValue($element['#parents'], $element['#value']);
    if (!empty($element['#value']) && !Bytes::validate($element['#value'])) {
      $form_state->setError($element, new TranslatableMarkup('The "@name" option must contain a valid value. You may either leave the text field empty or enter a string like "512" (bytes), "80 KB" (kilobytes) or "50 MB" (megabytes).', ['@name' => $element['#title']]));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): mixed {
    $random = new Random();
    $settings = $field->getFieldSettings();
    $settings['uri_scheme'] = $field->getSetting('uri_scheme');

    // Prepare destination.
    $dirname = static::doGetUploadLocation($settings);
    \Drupal::service('file_system')->prepareDirectory($dirname, FileSystemInterface::CREATE_DIRECTORY);

    // Ensure the directory ends with a slash.
    $dirname .= str_ends_with($dirname, '/') ? '' : '/';

    // Determine which extension to use when generating.
    $extension = 'txt';
    if (!empty($settings['file_extensions'])) {
      $extensions = explode(' ', $settings['file_extensions']);
      $extension = array_rand(array_flip($extensions));
    }

    // Generate a file entity.
    $destination = $dirname . $random->name(10) . '.' . $extension;
    $data = $random->paragraphs(3);
    /** @var \Drupal\file\FileRepositoryInterface $file_repository */
    $file_repository = \Drupal::service('file.repository');
    $file = $file_repository->writeData($data, $destination, FileExists::Error);

    return $file->id();
  }

}
