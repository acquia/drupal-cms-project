<?php

declare(strict_types=1);

namespace Drupal\ui_icons_picker\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command for adding icon to the Icon picker input.
 *
 * This command is implemented by
 * Drupal.AjaxCommands.prototype.updateIconLibrarySelection() defined in
 * ui_icons_picker/js/picker.js.
 *
 * @ingroup ajax
 */
class UpdateIconSelectionCommand implements CommandInterface {

  public function __construct(
    private string $icon_full_id,
    private string $wrapper_id,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'updateIconLibrarySelection',
      'icon_full_id' => $this->icon_full_id,
      'wrapper_id' => $this->wrapper_id,
    ];
  }

}
