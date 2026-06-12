<?php

namespace Drupal\eca_cache\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to write into raw cache.
 */
#[Action(
  id: 'eca_raw_cache_write',
  label: new TranslatableMarkup('Cache Raw: write'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Write a value item into raw cache.'),
  version_introduced: '2.0.0',
)]
class RawCacheWrite extends CacheWrite {

}
