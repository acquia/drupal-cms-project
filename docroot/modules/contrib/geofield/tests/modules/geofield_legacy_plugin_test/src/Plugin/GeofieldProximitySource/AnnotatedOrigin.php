<?php

namespace Drupal\geofield_legacy_plugin_test\Plugin\GeofieldProximitySource;

use Drupal\geofield\Plugin\GeofieldProximitySourceBase;

/**
 * An annotated proximity source from an external module.
 *
 * @GeofieldProximitySource(
 *   id = "geofield_test_annotated_origin",
 *   label = @Translation("Annotated origin"),
 *   context = {}
 * )
 */
class AnnotatedOrigin extends GeofieldProximitySourceBase {

}
