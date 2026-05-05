<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Insert content before by the ajax response.
 */
#[Action(
  id: 'eca_endpoint_set_ajax_response_before',
  label: new TranslatableMarkup('Ajax Response: insert before content'),
)]
#[EcaAction(
  version_introduced: '2.0.0',
)]
class SetAjaxResponseBeforeCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new BeforeCommand($selector, $content, $settings);
  }

}
