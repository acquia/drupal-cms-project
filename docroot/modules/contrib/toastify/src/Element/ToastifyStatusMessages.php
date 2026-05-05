<?php

namespace Drupal\toastify\Element;

use Drupal\Core\Render\Element\StatusMessages;

/**
 * Extends the StatusMessages element to pass messages to Toastify.
 */
class ToastifyStatusMessages extends StatusMessages {

  /**
   * {@inheritdoc}
   *
   * This is needed because the parent class uses get_class().
   *
   * @see https://www.drupal.org/project/drupal/issues/3346560
   */
  public function getInfo() {
    $info = parent::getInfo();
    $info['#pre_render'] = [static::class . '::generatePlaceholder'];

    return $info;
  }

  /**
   * {@inheritdoc}
   *
   * This is needed because the parent class uses get_class().
   *
   * @see https://www.drupal.org/project/drupal/issues/3346560
   */
  public static function generatePlaceholder(array $element) {
    if (!toastify_is_active()) {
      return parent::generatePlaceholder($element);
    }

    $build = [
      '#lazy_builder' => [
        static::class . '::renderMessages',
        [$element['#display']],
      ],
      '#create_placeholder' => TRUE,
    ];

    // Directly create a placeholder as we need this to be placeholdered
    // regardless if this is a POST or GET request.
    // @todo remove this when https://www.drupal.org/node/2367555 lands.
    return \Drupal::service('render_placeholder_generator')->createPlaceholder($build);
  }

  /**
   * {@inheritdoc}
   *
   * Pass the messages to Toastify instead of rendering them.
   */
  public static function renderMessages($type = NULL): array {
    if (!toastify_is_active()) {
      return parent::renderMessages($type);
    }

    $render = [];
    if (isset($type)) {
      $messages = [
        $type => \Drupal::messenger()->deleteByType($type),
      ];
    }
    else {
      $messages = \Drupal::messenger()->deleteAll();
    }

    if ($messages) {
      // Pass the messages to Toastify.
      $render['#attached']['drupalSettings']['toastify']['messages'] = $messages;
    }

    return $render;
  }

}
