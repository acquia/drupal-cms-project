<?php

namespace Drupal\eca_cache\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to write into ECA cache.
 */
#[Action(
  id: 'eca_cache_write',
  label: new TranslatableMarkup('Cache ECA: write'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Write a value item into ECA cache.'),
  version_introduced: '2.0.0',
)]
class EcaCacheWrite extends CacheWrite {

  use EcaCacheTrait;

}
