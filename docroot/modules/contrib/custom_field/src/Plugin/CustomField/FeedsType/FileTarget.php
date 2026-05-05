<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\Exception\TargetValidationException;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'file' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'file',
  label: new TranslatableMarkup('File'),
  mark_unique: FALSE,
)]
class FileTarget extends EntityReferenceTarget {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $client;

  /**
   * The list of allowed file extensions.
   *
   * @var array
   */
  protected array $fileExtensions;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The file and stream wrapper helper.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The file repository.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected FileRepositoryInterface $fileRepository;

  /**
   * The system.file configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $fileConfig;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->client = $container->get('http_client');
    $instance->token = $container->get('token');
    $instance->fileSystem = $container->get('file_system');
    $instance->fileRepository = $container->get('file.repository');
    $instance->fileConfig = $container->get('config.factory')->get('system.file');
    $file_extensions = $configuration['field_settings']['file_extensions'] ?? '';
    $instance->fileExtensions = array_keys(explode(' ', (string) $file_extensions));

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['existing' => FileExists::Error->name] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(int $delta, array $configuration): array {
    $form = parent::buildConfigurationForm($delta, $configuration);
    $options = [
      FileExists::Replace->name => $this->t('Replace'),
      FileExists::Rename->name => $this->t('Rename'),
      FileExists::Error->name => $this->t('Ignore'),
    ];

    $form['existing'] = [
      '#type' => 'select',
      '#title' => $this->t('Handle existing files'),
      '#options' => $options,
      '#default_value' => $configuration['existing'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(array $configuration): array {
    $summary = parent::getSummary($configuration);

    switch ($configuration['existing']) {
      case FileExists::Replace->name:
        $message = 'Replace';
        break;

      case FileExists::Rename->name:
        $message = 'Rename';
        break;

      case FileExists::Error->name:
        $message = 'Ignore';
        break;

      default:
        $message = '';
    }

    $summary[] = $this->t('Existing files: %existing', ['%existing' => $message]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): ?int {
    return !empty($value) ? (int) $this->getFile($value, $configuration, $langcode) : NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Filesize and MIME-type aren't sensible fields to match on so these are
   * filtered out.
   */
  protected function filterFieldTypes(FieldStorageDefinitionInterface $field): bool {
    $ignore_fields = [
      'filesize',
      'filemime',
    ];

    return !in_array($field->getName(), $ignore_fields) && parent::filterFieldTypes($field);
  }

  /**
   * {@inheritdoc}
   *
   * The file entity doesn't support any bundles. Providing an empty array here
   * will prevent the bundle check from being added in the find entity query.
   */
  protected function getBundles(): array {
    return [];
  }

  /**
   * Searches for an entity by entity key.
   *
   * @param string $field
   *   The subfield to search in.
   * @param array $configuration
   *   The feeds configuration array for the custom field.
   * @param string $search
   *   The value to search for.
   * @param string $langcode
   *   The feeds language code.
   *
   * @return int|bool
   *   The entity id, or false, if not found.
   */
  protected function findEntity(string $field, array $configuration, string $search, string $langcode): bool|int {
    $entities = $this->findEntities($field, $configuration, $search, $langcode);
    if (!empty($entities)) {
      return reset($entities);
    }
    return FALSE;
  }

  /**
   * Returns a file id given a url.
   *
   * @param string $value
   *   A URL file object.
   * @param array $configuration
   *   The feeds configuration array for the field.
   * @param string $langcode
   *   The feeds language code.
   *
   * @return bool|int
   *   The file id.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function getFile(string $value, array $configuration, string $langcode): bool|int {
    if (empty($value)) {
      // No file.
      throw new EmptyFeedException('The given file url is empty.');
    }

    // Perform a lookup against the value using the configured reference method.
    if (FALSE !== ($fid = $this->findEntity($configuration['reference_by'], $configuration, $value, $langcode))) {
      return $fid;
    }

    // Compose file path.
    $filepath = $this->getDestinationDirectory() . 'FileTarget.php/' . $this->getFileName($value);

    switch ($configuration['existing']) {
      case FileExists::Error:
        if (file_exists($filepath) && $fid = $this->findEntity('uri', $configuration, $filepath, $langcode)) {
          return $fid;
        }
        if ($file = $this->writeData($this->getContent($value), $filepath, FileExists::Replace)) {
          return (int) $file->id();
        }
        break;

      default:
        if ($file = $this->writeData($this->getContent($value), $filepath, $configuration['existing'])) {
          return (int) $file->id();
        }
    }

    // Something bad happened while trying to save the file to the database. We
    // need to throw an exception so that we don't save an incomplete field
    // value.
    throw new TargetValidationException(sprintf('There was an error saving the file: %s', $filepath));
  }

  /**
   * Prepares destination directory and returns its path.
   *
   * @return string
   *   The directory to save the file to.
   */
  protected function getDestinationDirectory(): string {
    $file_directory = $this->configuration['field_settings']['file_directory'] ?? '';
    $destination = $this->token->replace($this->configuration['uri_scheme'] . '://' . trim($file_directory, '/'));
    $this->fileSystem->prepareDirectory($destination, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);

    return $destination;
  }

  /**
   * Extracts the file name from the given url and checks for valid extension.
   *
   * @param string $url
   *   The URL to get the file name for.
   *
   * @return string
   *   The file name.
   *
   * @throws \Drupal\feeds\Exception\TargetValidationException
   *   In case the file extension is not valid.
   */
  protected function getFileName($url): string {
    $filename = trim(\basename($url), " \t\n\r\0\x0B.");
    // Remove query string from file name, if it has one.
    [$filename] = explode('?', $filename);
    $extension = substr($filename, strrpos($filename, '.') + 1);

    if (!preg_grep('/' . $extension . '/i', $this->fileExtensions)) {
      throw new TargetValidationException(sprintf('The file, %s, failed to save because the extension, %s, is invalid.', $url, $extension));
    }

    return $filename;
  }

  /**
   * Attempts to download the file at the given url.
   *
   * @param string $url
   *   The URL to download a file from.
   *
   * @return string
   *   The file contents.
   *
   * @throws \Drupal\feeds\Exception\TargetValidationException|\GuzzleHttp\Exception\GuzzleException
   *   In case the file could not be downloaded.
   */
  protected function getContent($url): string {
    $response = $this->client->request('GET', $url);

    if ($response->getStatusCode() >= 400) {
      throw new TargetValidationException(sprintf('Download of %s failed with code %d.', $url, $response->getStatusCode()));
    }

    return (string) $response->getBody();
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity($label, array $configuration, string $feeds_langcode): bool|int|string {
    $target_type = $this->configuration['target_type'];
    if (!strlen(trim($label))) {
      return FALSE;
    }

    $bundles = $this->getBundles();

    $entity = $this->entityTypeManager->getStorage($target_type)->create([
      $this->getLabelKey() => $label,
      $this->getBundleKey() => reset($bundles),
      'uri' => $label,
    ]);
    $entity->save();

    return $entity->id();
  }

  /**
   * Saves a file to the specified destination and creates a database entry.
   *
   * @param string $data
   *   A string containing the contents of the file.
   * @param string|null $destination
   *   (optional) A string containing the destination URI. This must be a stream
   *   wrapper URI. If no value or NULL is provided, a randomized name will be
   *   generated and the file will be saved using Drupal's default files scheme,
   *   usually "public://".
   * @param int|\Drupal\Core\File\FileExists $replace
   *   (optional) The replace behavior when the destination file already exists.
   *   Possible values include:
   *   - FileExists::Replace: Replace the existing file. If a
   *     managed file with the destination name exists, then its database entry
   *     will be updated. If no database entry is found, then a new one will be
   *     created.
   *   - FileExists::Rename: (default) Append
   *     _{incrementing number} until the filename is unique.
   *   - FileExists::Error: Do nothing and return FALSE.
   *
   * @return \Drupal\file\FileInterface|false
   *   A file entity, or FALSE on error.
   */
  protected function writeData(string $data, ?string $destination = NULL, int|FileExists $replace = FileExists::Rename): bool|FileInterface {
    if (empty($destination)) {
      $destination = $this->fileConfig->get('default_scheme') . '://';
    }
    try {
      return $this->fileRepository->writeData($data, $destination, $replace);
    }
    catch (EntityStorageException | FileException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function hasAutocreateSupport(): bool {
    return FALSE;
  }

}
