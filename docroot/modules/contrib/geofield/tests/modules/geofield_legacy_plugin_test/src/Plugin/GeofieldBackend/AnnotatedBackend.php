<?php

namespace Drupal\geofield_legacy_plugin_test\Plugin\GeofieldBackend;

use Drupal\geofield\Plugin\GeofieldBackend\GeofieldBackendDefault;

/**
 * An annotated backend from an external module.
 *
 * @GeofieldBackend(
 *   id = "geofield_test_annotated_backend",
 *   admin_label = @Translation("Annotated backend")
 * )
 */
class AnnotatedBackend extends GeofieldBackendDefault {

}
