<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Insert content after to the ajax response.
 */
#[Action(
  id: 'eca_endpoint_set_ajax_response_after',
  label: new TranslatableMarkup('Ajax Response: insert content after'),
)]
#[EcaAction(
  version_introduced: '2.0.0',
)]
class SetAjaxResponseAfterCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new AfterCommand($selector, $content, $settings);
  }

}
