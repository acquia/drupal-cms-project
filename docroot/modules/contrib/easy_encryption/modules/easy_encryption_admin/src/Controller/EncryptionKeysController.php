<?php

declare(strict_types=1);

namespace Drupal\easy_encryption_admin\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\easy_encryption\Encryption\EncryptionKeyId;
use Drupal\easy_encryption\KeyManagement\KeyUsageTrackerInterface;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Drupal\easy_encryption\KeyTransfer\KeyTransferInterface;
use Drupal\easy_encryption\Sodium\PrivateKeyStorageMigrator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for the Easy Encryption keys admin UI.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class EncryptionKeysController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly KeyRegistryInterface $registry,
    private readonly KeyUsageTrackerInterface $keyUsageTracker,
    private readonly KeyTransferInterface $keyTransfer,
    private readonly PrivateKeyStorageMigrator $migrator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get(KeyRegistryInterface::class),
      $container->get(KeyUsageTrackerInterface::class),
      $container->get(KeyTransferInterface::class),
      $container->get(PrivateKeyStorageMigrator::class),
    );
  }

  /**
   * Lists known encryption keys with usage counts.
   */
  public function list(): array {
    if ($this->migrator->isMigrationNeeded()['result']) {
      $this->messenger()->addWarning(
        $this->t(
          'The private key is currently stored in the database. <a href=":url">Migrate it to the filesystem</a> for better security.',
          [
            ':url' => Url::fromRoute('easy_encryption_admin.migrate_private_key')->toString(),
          ]
        )
      );
    }

    $cacheability = new CacheableMetadata();

    $known = $this->registry->listKnownKeyIds();
    $references = $this->keyUsageTracker->getKeyUsageMapping();
    $cacheability->addCacheableDependency($references['cacheability']);

    $counts = [];
    foreach ($references['result'] as $usageMapping) {
      $cacheability->addCacheableDependency($usageMapping);

      $idString = (string) $usageMapping->keyId;
      $counts[$idString] = ($counts[$idString] ?? 0) + 1;
    }

    $rows = [];
    foreach ($known['result'] as $key_id) {
      $key_id_string = (string) $key_id;
      $rows[] = [
        'id' => $key_id_string,
        'usages' => $counts[$key_id_string] ?? 0,
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'export' => [
                'title' => $this->t('Export'),
                'url' => Url::fromRoute('easy_encryption_admin.keys_export', [
                  'encryption_key_id' => $key_id_string,
                ]),
              ],
            ],
          ],
        ],
      ];
    }

    $build = [
      '#type' => 'table',
      '#header' => [
        $this->t('Encryption key id'),
        $this->t('Usages'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No encryption keys found.'),
    ];

    CacheableMetadata::createFromRenderArray($build)->addCacheableDependency($cacheability)->applyTo($build);
    return $build;
  }

  /**
   * Downloads an exported encryption key package.
   */
  public function export(string $encryption_key_id): StreamedResponse {
    $key_id = EncryptionKeyId::fromNormalized($encryption_key_id);
    $export_text = $this->keyTransfer->exportKey($key_id);

    $response = new StreamedResponse(static function () use ($export_text): void {
      echo $export_text;
    });

    $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="easy-encryption-key-' . $encryption_key_id . '.txt"');
    $response->headers->set('Cache-Control', 'no-store, max-age=0, private');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    return $response;
  }

}
