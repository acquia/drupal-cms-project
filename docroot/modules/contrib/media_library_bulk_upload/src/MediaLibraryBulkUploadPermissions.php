<?php

namespace Drupal\media_library_bulk_upload;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\Entity\MediaType;

/**
 * Provides dynamic permissions..
 */
class MediaLibraryBulkUploadPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of permissions.
   *
   * @return array
   *   The node type permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function getPermissions(): array {
    $permissions = [];
    foreach (MediaType::loadMultiple() as $media_type) {
      $permissions += $this->buildPermissions($media_type);
    }
    return $permissions;
  }

  /**
   * Returns a list of permissions for a media type..
   *
   * @param \Drupal\media\Entity\MediaType $media_type
   *   The media type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(MediaType $media_type) {
    return [
      "use media {$media_type->id()} bulk upload form" => [
        'title' => $this->t('%type_name : Use media library bulk upload form', ['%type_name' => $media_type->label()]),
      ],
    ];
  }

}
