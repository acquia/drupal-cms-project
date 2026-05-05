<?php

namespace Drupal\eca_cache\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to invalidate ECA cache.
 */
#[Action(
  id: 'eca_cache_invalidate',
  label: new TranslatableMarkup('Cache ECA: invalidate'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Invalidates a part or the whole ECA cache.'),
  version_introduced: '2.0.0',
)]
class EcaCacheInvalidate extends CacheInvalidate {

  use EcaCacheTrait;

}
