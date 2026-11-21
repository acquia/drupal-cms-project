<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Add open modal dialog command to the ajax response.
 */
#[Action(
  id: 'eca_endpoint_set_ajax_response_open_modal_dialog',
  label: new TranslatableMarkup('Ajax Response: open modal dialog'),
)]
#[EcaAction(
  version_introduced: '2.0.0',
)]
class SetAjaxResponseOpenModalDialogCommand extends SetAjaxResponseOpenDialogCommand {

  /**
   * {@inheritdoc}
   */
  protected function getDialogCommand(string $selector, string $title, string|array $content, array $options, ?array $settings): CommandInterface {
    return new OpenModalDialogCommand($title, $content, $options, $settings);
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
