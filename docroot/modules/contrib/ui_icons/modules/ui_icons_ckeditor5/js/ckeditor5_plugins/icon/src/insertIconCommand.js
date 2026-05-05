/**
 * @file defines insertIconsCommand, which is executed when the icon
 * toolbar button is pressed.
 */
/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved */
import { Command } from 'ckeditor5/src/core';

function createDrupalIcon(writer, attributes) {
  const drupalIcon = writer.createElement('drupalIcon', attributes);
  return drupalIcon;
}

/**
 * The insert icon command.
 *
 * The command is registered by the `IconEditing` plugin as
 * `InsertIconCommand`.
 *
 * In order to insert icon at the current selection position, execute the
 * command and pass the attributes desired in the drupal-icon element:
 *
 * @example
 *    editor.execute('InsertIconCommand', {
 *      'data-icon-id': 'pack_id:icon_id',
 *      'data-icon-settings': '{key: value, key_2: value_2}',
 *    });
 *
 * @private
 */
export default class InsertIconCommand extends Command {
  execute(settings) {
    if (!settings.icon) {
      return;
    }
    const modelAttributes = {
      drupalIconId: settings.icon,
      drupalIconSettings: JSON.stringify(settings.icon_settings),
    };

    this.editor.model.change((writer) => {
      this.editor.model.insertObject(createDrupalIcon(writer, modelAttributes));
    });
  }

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'drupalIcon',
    );
    this.isEnabled = allowedIn !== null;
  }
}
