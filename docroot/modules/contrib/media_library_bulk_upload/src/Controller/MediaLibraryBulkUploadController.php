<?php

namespace Drupal\media_library_bulk_upload\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\media\MediaTypeInterface;
use Drupal\media_library\MediaLibraryState;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Media Library Bulk Upload Controller class.
 */
class MediaLibraryBulkUploadController extends ControllerBase {

  /**
   * Constructs a new MediaBulkUploadController.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Prepares the list of possible media upload.
   *
   * @return array
   *   A render array for the media list.
   */
  public function listUpload() {
    $supported_media_types = $this->config('media_library_bulk_upload.settings')
      ->get('media_types');
    // If no limitation then load all media types.
    $supported_media_types = empty($supported_media_types) ? NULL : $supported_media_types;

    /** @var \Drupal\media\MediaTypeInterface[] $media_types */
    $media_types = $this->entityTypeManager->getStorage('media_type')
      ->loadMultiple($supported_media_types);

    $content = [];
    foreach ($media_types as $id => $media_type) {
      if (!$this->accessForm($this->currentUser, $media_type)->isAllowed()) {
        continue;
      }

      $url = Url::fromRoute('media_library.bulk_upload.upload_form', ['media_type' => $id]);

      $content[$id]['title'] = $media_type->label();
      $content[$id]['options'] = [];
      $content[$id]['description'] = '';
      $content[$id]['url'] = $url;
    }

    return [
      '#theme' => 'admin_block_content',
      '#content' => $content,
    ];
  }

  /**
   * Builds the form for uploading media in bulk.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type.
   *
   * @return array
   *   The render array for the media library.
   */
  public function uploadForm(MediaTypeInterface $media_type) {
    $allowed_media_type_ids = [$media_type->id()];

    // Create a new media library URL with the correct state parameters.
    $selected_type_id = reset($allowed_media_type_ids);
    $remaining = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;

    $state = MediaLibraryState::create('media_library.opener.bulk_upload', $allowed_media_type_ids, $selected_type_id, $remaining);

    return \Drupal::service('media_library.ui_builder')->buildUi($state);
  }

  /**
   * Access callback to validate if the user has access to the upload form list.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User to validate access on.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessList(AccountInterface $account) {
    if ($account->hasPermission('administer media')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $access_result = AccessResult::neutral();
    $media_types = $this->entityTypeManager->getStorage('media_type')
      ->loadMultiple();
    foreach ($media_types as $media_type) {
      $access_result = $access_result->orIf(AccessResult::allowedIfHasPermission($account, "use media {$media_type->id()} bulk upload form"));
    }

    return $access_result;
  }

  /**
   * Access callback to validate if the user has access to a bulk upload form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User to validate access on.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessForm(AccountInterface $account, MediaTypeInterface $media_type) {
    $config = $this->config('media_library_bulk_upload.settings');
    $supported_media_types = $config->get('media_types') ?? NULL;

    $access = !empty($supported_media_types) && !in_array($media_type->id(), $supported_media_types)
      ? AccessResult::forbidden("Media type {$media_type->label()} is not enabled for bulk upload.")
      : AccessResult::allowedIfHasPermission($account, "use media {$media_type->id()} bulk upload form");

    $access->addCacheTags($config->getCacheTags());
    return $access;
  }

}
