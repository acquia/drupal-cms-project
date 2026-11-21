<?php

namespace Drupal\custom_field\Element;

use Drupal\Core\Datetime\Element\Datelist as CoreDatelist;
use Drupal\Core\Render\Attribute\FormElement;

/**
 * Provides a custom_field_datelist element.
 */
#[FormElement('custom_field_datelist')]
class DateList extends CoreDatelist {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $info = parent::getInfo();
    $info['#theme'] = NULL;
    $info['#theme_wrappers'] = [];

    return $info;
  }

}
