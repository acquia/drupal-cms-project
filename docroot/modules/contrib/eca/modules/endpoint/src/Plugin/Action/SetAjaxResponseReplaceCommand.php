<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Replace content by the ajax response.
 */
#[Action(
  id: 'eca_endpoint_set_ajax_response_replace',
  label: new TranslatableMarkup('Ajax Response: replace content'),
)]
#[EcaAction(
  version_introduced: '2.0.0',
)]
class SetAjaxResponseReplaceCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new ReplaceCommand($selector, $content, $settings);
  }

}
