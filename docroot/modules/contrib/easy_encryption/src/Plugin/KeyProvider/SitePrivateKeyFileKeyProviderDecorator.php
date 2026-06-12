<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Plugin\KeyProvider;

use Drupal\Core\File\FileSystemInterface;
use Drupal\easy_encryption\Sodium\SodiumKeyPairRepositoryUsingKeyEntities;
use Drupal\easy_encryption\Sodium\SodiumKeyPairReadRepositoryInterface;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyProvider\FileKeyProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reads content of the site's private key files stored as PHP code.
 *
 * This decorator extends the File key provider to support reading private key
 * files that are stored as PHP code (wrapped in `<?php return 'value';`).
 * This format prevents accidental exposure if the file is accessed via the web,
 * since PHP will execute the code and return nothing visible.
 *
 * For files outside the Easy Encryption private key directory, this falls back
 * to the standard File key provider behavior.
 *
 * @internal
 *   This is an internal part of Easy Encryption and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class SitePrivateKeyFileKeyProviderDecorator extends FileKeyProvider {

  /**
   * The Sodium repository.
   */
  protected SodiumKeyPairReadRepositoryInterface $sodiumRepository;

  /**
   * The filesystem.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sodiumRepository = $container->get(SodiumKeyPairReadRepositoryInterface::class);
    $instance->fileSystem = $container->get(FileSystemInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(KeyInterface $key): string {
    $config = $this->getConfiguration();

    // If no file location is configured, delegate to parent.
    if (empty($config['file_location'])) {
      return parent::getKeyValue($key);
    }

    $path = $config['file_location'];

    // If this is an Easy Encryption private key file (stored as PHP code),
    // use require to execute and return the value.
    if ($this->sodiumRepository instanceof SodiumKeyPairRepositoryUsingKeyEntities && dirname($this->fileSystem->realpath($path)) === $this->fileSystem->realpath($this->sodiumRepository->getPrivateKeyDirectory())) {

      if (!file_exists($path) || !is_readable($path)) {
        return '';
      }

      $value = require $path;
      return is_string($value) ? $value : '';
    }

    // For all other files, use the standard File key provider logic.
    return parent::getKeyValue($key);
  }

}
