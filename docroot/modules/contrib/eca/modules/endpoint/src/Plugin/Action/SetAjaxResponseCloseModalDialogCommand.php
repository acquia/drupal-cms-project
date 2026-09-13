<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Add the close modal dialog command to the ajax response.
 */
#[Action(
  id: 'eca_endpoint_set_ajax_response_close_modal_dialog',
  label: new TranslatableMarkup('Ajax Response: close modal dialog'),
)]
#[EcaAction(
  version_introduced: '2.0.0',
)]
class SetAjaxResponseCloseModalDialogCommand extends SetAjaxResponseCloseDialogCommand {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $persist = (bool) $this->configuration['persist'];
    return new CloseModalDialogCommand($persist);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    unset($config['selector']);
    return $config;
  }

}
