<?php

namespace Drupal\geofield_proximity_source_test\Plugin\GeofieldProximitySource;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\geofield\Attribute\GeofieldProximitySource;
use Drupal\geofield\Plugin\GeofieldProximitySourceBase;

/**
 * A proximity source that sets no_ui, so the source select leaves it out.
 */
#[GeofieldProximitySource(
  id: 'geofield_test_hidden_origin',
  label: new TranslatableMarkup('Hidden Origin'),
  description: new TranslatableMarkup('A source that is not offered in the source select.'),
  context: [],
  no_ui: TRUE,
)]
class HiddenOrigin extends GeofieldProximitySourceBase {

}
