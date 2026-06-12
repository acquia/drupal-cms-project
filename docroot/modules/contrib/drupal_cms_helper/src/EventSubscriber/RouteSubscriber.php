<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaTypeInterface;
use Drupal\media\Plugin\media\Source\File;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\String\Inflector\EnglishInflector;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 *
 * @todo Remove when https://www.drupal.org/i/3569875 is released.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $collection->get('media_library.bulk_upload.list')
      ?->setDefault('_title', 'Add media');

    $collection->get('media_library.bulk_upload.upload_form')
      // The identifier for this service is its class name, so yes, the title
      // callback should only have one colon in it.
      ?->setDefault('_title_callback', self::class . ':bulkUploadFormTitle');
  }

  /**
   * Generates better, dynamic titles for the media bulk upload forms.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type being uploaded.
   *
   * @return \Stringable
   *   The translated title for the bulk upload form.
   */
  public function bulkUploadFormTitle(MediaTypeInterface $media_type): \Stringable {
    $label = $media_type->label();

    // Only file-based media can be uploaded in bulk.
    if ($media_type->getSource() instanceof File) {
      // @todo Make this multilingual.
      [$label] = (new EnglishInflector())->pluralize($media_type->label());
    }
    return $this->t('Add @media', ['@media' => $label]);
  }

}
