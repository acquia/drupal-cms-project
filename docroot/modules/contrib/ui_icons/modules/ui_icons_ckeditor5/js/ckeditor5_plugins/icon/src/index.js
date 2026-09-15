/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved */
import { Plugin } from 'ckeditor5/src/core';
import IconUi from './iconUi';
import IconEditing from './iconEditing';

class Icon extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [IconUi, IconEditing];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'Icon';
  }
}

export default {
  Icon,
};
