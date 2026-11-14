<?php

namespace Drupal\editoria11y_test_extender\Plugin\views\display_extender;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsDisplayExtender;
use Drupal\views\Plugin\views\display_extender\DisplayExtenderPluginBase;

/**
 * A display extender that only exists once this test module is installed.
 *
 * The annotation is kept for Drupal versions before 10.3, which do not
 * discover the attribute.
 *
 * @ViewsDisplayExtender(
 *   id = "editoria11y_test_extender",
 *   title = @Translation("Editoria11y test display extender")
 * )
 */
#[ViewsDisplayExtender(
  id: 'editoria11y_test_extender',
  title: new TranslatableMarkup('Editoria11y test display extender'),
)]
class TestDisplayExtender extends DisplayExtenderPluginBase {

}
