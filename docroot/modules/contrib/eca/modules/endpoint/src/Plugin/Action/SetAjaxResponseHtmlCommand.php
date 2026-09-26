<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Apply html content by the ajax response.
 */
#[Action(
  id: 'eca_endpoint_set_ajax_response_html',
  label: new TranslatableMarkup('Ajax Response: apply html content'),
)]
#[EcaAction(
  version_introduced: '2.0.0',
)]
class SetAjaxResponseHtmlCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new HtmlCommand($selector, $content, $settings);
  }

}
