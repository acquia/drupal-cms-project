<?php

namespace Drupal\eca_views\Plugin\views\argument_default;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;

/**
 * The ECA argument default handler.
 *
 * @ingroup views_argument_default_plugins
 */
#[ViewsArgumentDefault(
  id: 'eca',
  title: new TranslatableMarkup('ECA')
)]
class Eca extends ArgumentDefaultPluginBase {

}
