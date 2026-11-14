<?php

namespace Drupal\media_library_bulk_upload;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_library\MediaLibraryOpenerInterface;
use Drupal\media_library\MediaLibraryState;

/**
 * The media library opener for media bulk uploads.
 */
class MediaLibraryBulkUploadOpener implements MediaLibraryOpenerInterface {

  /**
   * Constructs a MediaLibraryBulkUploadOpener object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function checkAccess(MediaLibraryState $state, AccountInterface $account) {
    $allowed_type_ids = $state->getAllowedTypeIds();
    $media_types = $this->entityTypeManager->getStorage('media_type')
      ->loadMultiple($allowed_type_ids);

    if (empty($allowed_type_ids) || count($media_types) !== count($allowed_type_ids)) {
      return AccessResult::forbidden('Invalid media types specified.')
        ->addCacheTags(['config:media_type_list'])
        ->addCacheableDependency($state);
    }

    // The user must be allowed to bulk upload every media type the media
    // library was opened for.
    $access = AccessResult::allowed();
    foreach ($media_types as $media_type) {
      $access = $access->andIf($this->checkMediaTypeAccess($media_type, $account));
    }
    return $access->addCacheableDependency($state);
  }

  /**
   * Checks if an account may bulk upload media of a given media type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkMediaTypeAccess(MediaTypeInterface $media_type, AccountInterface $account): AccessResultInterface {
    $config = $this->configFactory->get('media_library_bulk_upload.settings');
    $supported_media_types = $config->get('media_types');

    $access = !empty($supported_media_types) && !in_array($media_type->id(), $supported_media_types, TRUE)
      ? AccessResult::forbidden("Media type {$media_type->label()} is not enabled for bulk upload.")
      : AccessResult::allowedIfHasPermission($account, "use media {$media_type->id()} bulk upload form");

    return $access->addCacheableDependency($config);
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectionResponse(MediaLibraryState $state, array $selected_ids) {
    $response = new AjaxResponse();

    $parameters = $state->getOpenerParameters();
    if (empty($parameters['field_widget_id'])) {
      throw new \InvalidArgumentException('field_widget_id parameter is missing.');
    }

    // Create a comma-separated list of media IDs, insert them in the hidden
    // field of the widget, and trigger the field update via the hidden submit
    // button.
    $widget_id = $parameters['field_widget_id'];
    $ids = implode(',', $selected_ids);
    $response
      ->addCommand(new InvokeCommand("[data-media-library-widget-value=\"$widget_id\"]", 'val', [$ids]))
      ->addCommand(new InvokeCommand("[data-media-library-widget-update=\"$widget_id\"]", 'trigger', ['mousedown']));

    return $response;
  }

}
