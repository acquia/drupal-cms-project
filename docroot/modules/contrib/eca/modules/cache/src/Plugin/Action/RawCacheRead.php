<?php

namespace Drupal\eca_cache\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to read from raw cache.
 */
#[Action(
  id: 'eca_raw_cache_read',
  label: new TranslatableMarkup('Cache Raw: read'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Read a value item from raw cache and store it as a token.'),
  version_introduced: '2.0.0',
)]
class RawCacheRead extends CacheRead {

}
