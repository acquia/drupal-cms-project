<?php

namespace Drupal\eca_cache\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to read from ECA cache.
 */
#[Action(
  id: 'eca_cache_read',
  label: new TranslatableMarkup('Cache ECA: read'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Read a value item from ECA cache and store it as a token.'),
  version_introduced: '2.0.0',
)]
class EcaCacheRead extends CacheRead {

  use EcaCacheTrait;

}
