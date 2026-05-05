<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Append content to the ajax response.
 */
#[Action(
  id: 'eca_endpoint_set_ajax_response_append',
  label: new TranslatableMarkup('Ajax Response: append content'),
)]
#[EcaAction(
  version_introduced: '2.0.0',
)]
class SetAjaxResponseAppendCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new AppendCommand($selector, $content, $settings);
  }

}
