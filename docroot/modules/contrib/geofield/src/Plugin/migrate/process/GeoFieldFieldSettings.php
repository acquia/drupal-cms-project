<?php

namespace Drupal\geofield\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Configure field instance settings for geofield.
 */
#[MigrateProcess('geofield_field_settings')]
class GeoFieldFieldSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * @return mixed
   *   The transformed value.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($row->getSourceProperty('type') == 'geofield' && isset($value['backend'])) {
      $value['backend'] = ($value['backend'] != 'default') ? $value['backend'] : 'geofield_backend_default';
    }
    return $value;
  }

}
