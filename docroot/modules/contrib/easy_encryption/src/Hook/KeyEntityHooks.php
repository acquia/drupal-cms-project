<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\easy_encryption\KeyManagement\KeyUsageTrackerInterface;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Drupal\easy_encryption\Sodium\SodiumKeyPairRepositoryUsingKeyEntities;
use Drupal\easy_encryption\Sodium\SodiumKeyPairWriteRepositoryInterface;
use Drupal\key\Entity\Key;
use Drupal\key\KeyInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Key entity hook implementations.
 *
 * @internal
 *   This is an internal part of Easy Encrypt and may be changed or removed at
 *   any time without warning. External code should not interact with
 *   this class.
 */
final class KeyEntityHooks {

  /**
   * Constructs a new object.
   *
   * @phpstan-param \Closure(): \Drupal\key\Plugin\KeyPluginManager $keyProviderManager
   */
  public function __construct(
    private readonly KeyRegistryInterface $keyRegistry,
    private readonly KeyUsageTrackerInterface $usageTracker,
    #[AutowireServiceClosure('plugin.manager.key.key_provider')]
    private readonly \Closure $keyProviderManager,
    private readonly SodiumKeyPairWriteRepositoryInterface $sodiumKeyPairWriteRepository,
    private readonly Settings $settings,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_prepare_form() for Key form.
   */
  #[Hook('key_prepare_form')]
  public function keyEntityPrepareForm(KeyInterface $key, string $operation, FormStateInterface $formState): void {
    // Suggest easy_encrypted key provider on key add form instead of built-in
    // insecure providers, such as config or state.
    if ($operation === 'add' && !array_key_exists('key_provider', $formState->getUserInput())) {
      $key->setPlugin('key_provider', 'easy_encrypted');
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for Key add form.
   */
  #[Hook('form_key_add_form_alter')]
  public function keyAddFormAlter(array &$form, FormStateInterface $formState, string $form_id): void {
    $upgraded_key_provider_ids = $this->getUpgradableKeyProviders();
    if (!empty($upgraded_key_provider_ids)) {
      $definitions = ($this->keyProviderManager)()->getDefinitions();
      $upgraded_key_providers = array_intersect_key($definitions, array_flip($upgraded_key_provider_ids));

      if (empty($upgraded_key_providers)) {
        return;
      }

      $provider_labels = array_column($upgraded_key_providers, 'label');

      $form['easy_encryption_protected_providers_message'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [
            new PluralTranslatableMarkup(
              count($provider_labels),
              'New keys created using the @providers key provider will be automatically converted to use Easy Encrypted to store credentials securely.',
              'New keys created using any of the following key providers will be automatically converted to use Easy Encrypted to store credentials securely: @providers.',
              ['@providers' => implode(', ', $provider_labels)],
            ),
          ],
        ],
        '#weight' => -100,
      ];
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for Key entities.
   *
   * Default new keys to easy_encrypted when they are using an insecure
   * key provider so credentials created by recipes and automated tooling are
   * stored securely by default.
   */
  #[Hook('key_presave')]
  public function onKeyPreSave(KeyInterface $key): void {
    if (!$key->isNew() || $key->get('key_provider') === 'easy_encrypted') {
      return;
    }

    assert($key instanceof Key);

    $upgradeable_key_provider_ids = $this->getUpgradableKeyProviders();
    $should_bypass_key_provider_upgrade = self::shouldBypassKeyProviderUpgrade($key);
    if (!$should_bypass_key_provider_upgrade && in_array($key->get('key_provider'), $upgradeable_key_provider_ids, TRUE)) {
      $unencrypted_value = $key->getKeyValue();
      $key->set('key_provider', 'easy_encrypted');
      // @phpstan-ignore-next-line
      $key->getKeyProvider()->setKeyValue($key, $unencrypted_value);
      $key->set('key_provider_settings', $key->getPluginCollection('key_provider')->get('easy_encrypted')->getConfiguration());
    }

    // Do not leave traces of this feature flag in config.
    if ($should_bypass_key_provider_upgrade) {
      self::deactivateKeyProviderUpgradeBypass($key);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for Key entities.
   */
  #[Hook('key_delete')]
  public function onKeyDelete(KeyInterface $key): void {
    if ($this->sodiumKeyPairWriteRepository instanceof SodiumKeyPairRepositoryUsingKeyEntities) {
      $this->sodiumKeyPairWriteRepository->handleEntityDeletion($key);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_access() for Key entities.
   *
   * This is an imperfect solution until the follow Key issue is open.
   *
   * @see https://www.drupal.org/project/key/issues/3568554
   */
  #[Hook('key_access')]
  public function onKeyAccess(KeyInterface $key, string $operation, AccountInterface $account): AccessResult {
    if ($operation !== 'delete') {
      return AccessResult::neutral();
    }

    $cacheability = new CacheableMetadata();

    $active = $this->keyRegistry->getActiveKeyId();

    $cacheability->addCacheableDependency($active['cacheability']);

    $active_id = $active['result'];

    if ($active_id !== NULL) {
      $protected_key_ids = [];
      $protected_key_ids[] = SodiumKeyPairRepositoryUsingKeyEntities::privateKeyKeyEntityId($active_id->value);
      $protected_key_ids[] = SodiumKeyPairRepositoryUsingKeyEntities::publicKeyKeyEntityId($active_id->value);

      if (in_array($key->id(), $protected_key_ids, TRUE)) {
        return AccessResult::forbidden('This key entity is part of an active key pair used Easy Encryption and cannot be deleted.')->addCacheableDependency($cacheability);
      }
    }

    $usage_mapping = $this->usageTracker->getKeyUsageMapping();
    $cacheability->addCacheableDependency($usage_mapping['cacheability']);

    foreach ($usage_mapping['result'] as $mapping) {
      $cacheability->addCacheableDependency($mapping);

      $referenced_id = $mapping->keyId;
      $protected_key_ids = [];
      $protected_key_ids[] = SodiumKeyPairRepositoryUsingKeyEntities::privateKeyKeyEntityId($referenced_id->value);
      $protected_key_ids[] = SodiumKeyPairRepositoryUsingKeyEntities::publicKeyKeyEntityId($referenced_id->value);

      if (in_array($key->id(), $protected_key_ids, TRUE)) {
        return AccessResult::forbidden('This key entity is part of an active or still in used Easy Encryption key pair and cannot be deleted.')->addCacheableDependency($cacheability);
      }
    }

    return AccessResult::allowed()->addCacheableDependency($cacheability);
  }

  /**
   * Gets the list of key provider ids that upgraded to EE on Key create.
   *
   * @return string[]
   *   List of key provider ids.
   */
  private function getUpgradableKeyProviders(): mixed {
    return $this->settings::get('easy_encryption')['upgraded_key_providers'] ?? ['config', 'state'];
  }

  /**
   * Activates the third-party setting for bypassing key provider upgrades.
   *
   * @internal
   */
  public static function activateKeyProviderUpgradeBypass(KeyInterface $key): void {
    $key->setThirdPartySetting('easy_encryption', 'bypass_key_provider_upgrade', TRUE);
  }

  /**
   * Gets whether key provider upgrade should be bypassed.
   *
   * @internal
   */
  public static function shouldBypassKeyProviderUpgrade(KeyInterface $key): bool {
    return (bool) $key->getThirdPartySetting('easy_encryption', 'bypass_key_provider_upgrade', FALSE);
  }

  /**
   * Deactivates the third-party setting for bypassing key provider upgrades.
   *
   * @internal
   */
  public static function deactivateKeyProviderUpgradeBypass(KeyInterface $key): void {
    $key->unsetThirdPartySetting('easy_encryption', 'bypass_key_provider_upgrade');
  }

}
