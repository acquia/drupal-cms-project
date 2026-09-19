<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Internal;

use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\key\KeyRepositoryInterface;

/**
 * Prevents uninstalling Easy Encryption while encrypted keys exist.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class EasyEncryptionUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validate($module): array {
    $reasons = [];

    if ($module !== 'easy_encryption') {
      return $reasons;
    }

    $encrypted_keys = $this->getEncryptedKeys();

    if (!empty($encrypted_keys)) {
      $key_list = [];
      foreach ($encrypted_keys as $key) {
        $key_list[] = $this->t('@label (@id)', [
          '@label' => $key->label(),
          '@id' => $key->id(),
        ]);
      }

      $reasons[] = $this->formatPlural(
        count($encrypted_keys),
        'The following key uses the Easy Encrypted key provider and must be deleted or reconfigured before uninstalling this module: @keys',
        'The following keys use the Easy Encrypted key provider and must be deleted or reconfigured before uninstalling this module: @keys',
        [
          '@keys' => implode(', ', $key_list),
        ]
      );

      if ($this->currentUser->hasPermission('administer keys')) {
        $reasons[] = $this->t(
          'Visit the <a href="@url">Keys</a> page to manage keys.',
          [
            '@url' => Url::fromRoute('entity.key.collection')->toString(),
          ]
        );
      }
    }

    return $reasons;
  }

  /**
   * Returns all Key entities that use the easy_encrypted key provider.
   *
   * @return \Drupal\key\KeyInterface[]
   *   An array of Key entities.
   */
  private function getEncryptedKeys(): array {
    return $this->keyRepository->getKeysByProvider('easy_encrypted');
  }

}
