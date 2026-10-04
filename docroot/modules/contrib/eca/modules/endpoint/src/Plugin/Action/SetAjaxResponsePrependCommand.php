<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Prepend content by the ajax response.
 */
#[Action(
  id: 'eca_endpoint_set_ajax_response_prepend',
  label: new TranslatableMarkup('Ajax Response: prepend content'),
)]
#[EcaAction(
  version_introduced: '2.0.0',
)]
class SetAjaxResponsePrependCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new PrependCommand($selector, $content, $settings);
  }

}
