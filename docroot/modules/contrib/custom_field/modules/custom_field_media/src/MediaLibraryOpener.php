<?php

declare(strict_types=1);

namespace Drupal\custom_field_media;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media_library\MediaLibraryOpenerInterface;
use Drupal\media_library\MediaLibraryState;

/**
 * The media library opener for form elements.
 */
class MediaLibraryOpener implements MediaLibraryOpenerInterface {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(MediaLibraryState $state, AccountInterface $account) {
    $process_result = function ($result) {
      if ($result instanceof RefinableCacheableDependencyInterface) {
        $result->addCacheContexts(['url.query_args']);
      }
      return $result;
    };

    return $process_result(AccessResult::allowed());
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectionResponse(MediaLibraryState $state, array $selected_ids): AjaxResponse {
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
