import { Plugin } from 'ckeditor5/src/core';
import LinkitEditing from './linkitediting';
import initializeAutocomplete from './autocomplete';

class Linkit extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [LinkitEditing];
  }

  init() {
    this._state = {};
    const editor = this.editor;
    const options = editor.config.get('linkit');
    // TRICKY: Work-around until the CKEditor team offers a better solution: force the ContextualBalloon to get instantiated early thanks to DrupalImage not yet being optimized like https://github.com/ckeditor/ckeditor5/commit/c276c45a934e4ad7c2a8ccd0bd9a01f6442d4cd3#diff-1753317a1a0b947ca8b66581b533616a5309f6d4236a527b9d21ba03e13a78d8.
    editor.plugins.get('LinkUI')._createViews();
    this._enableLinkAutocomplete();
    this._handleExtraFormFieldSubmit();
    this._handleDataLoadingIntoExtraFormField();
  }

  _enableLinkAutocomplete() {
    const editor = this.editor;
    const options = editor.config.get('linkit');
    const linkFormView = editor.plugins.get('LinkUI').formView;
    const linkitInput = linkFormView.urlInputView.fieldView.element;
    let wasAutocompleteAdded = false;

    linkFormView.extendTemplate({
      attributes: {
        class: ['ck-vertical-form', 'ck-link-form_layout-vertical'],
      },
    });

    editor.plugins
      .get('ContextualBalloon')
      .on('set:visibleView', (evt, propertyName, newValue, oldValue) => {
        if (newValue !== linkFormView || wasAutocompleteAdded) {
          return;
        }

      /**
       * Used to know if a selection was made from the autocomplete results.
       *
       * @type {boolean}
       */
      let selected;

      initializeAutocomplete(
        linkitInput,
        {
          ...options,
          selectHandler: (event, { item }) => {
            if (!item.path) {
              throw 'Missing path param.' + JSON.stringify(item);
            }

            if (item.entity_type_id || item.entity_uuid || item.substitution_id) {
              if (!item.entity_type_id || !item.entity_uuid || !item.substitution_id) {
                throw 'Missing path param.' + JSON.stringify(item);
              }

              this.set('entityType', item.entity_type_id);
              this.set('entityUuid', item.entity_uuid);
              this.set('entitySubstitution', item.substitution_id);
            }
            else {
              this.set('entityType', '');
              this.set('entityUuid', '');
              this.set('entitySubstitution', '');
            }

            // If the displayed text is empty and not read only (paragraph is selected)
            // use the entity label as the default value.
            if (
              linkFormView.hasOwnProperty('displayedTextInputView') &&
              linkFormView.displayedTextInputView.fieldView.element.value === '' &&
              item.label &&
              !linkFormView.displayedTextInputView.fieldView.element.readOnly
            ) {
              // The item label has been sanitized for display as HTML. We want this back in the original format so that
              // characters are not double encoded (e.g. we want "foo &amp; bar" to be "foo & bar").
              const label = document.createElement('span');
              label.innerHTML = item.label;
              linkFormView.displayedTextInputView.fieldView.value = label.textContent
            }

            event.target.value = item.path ?? '';
            selected = true;
            return false;
          },
          openHandler: (event) => {
            selected = false;
          },
          closeHandler: (event) => {
            // Upon close, ensure there is no selection (#3447669).
            selected = false;
          },
        },
      );

      wasAutocompleteAdded = true;
      linkFormView.urlInputView.fieldView.template.attributes.class.push('form-linkit-autocomplete');
    });
  }

  _handleExtraFormFieldSubmit() {
    const editor = this.editor;
    const linkFormView = editor.plugins.get('LinkUI').formView;
    const linkCommand = editor.commands.get('link');

    // Only selections from autocomplete set converter attributes.
    const linkit = editor.plugins.get('Linkit');
    this.listenTo(linkFormView, 'submit', () => {
      // Stop the execution of the link command caused by closing the form.
      // Inject the extra attribute value.
      linkCommand.once('execute', (evt, args) => {
        // CKEditor v45 includes a 'displayed text' input value. If present,
        // send this information along so we can properly update the selection.
        let displayedText = '';
        if (typeof linkFormView.displayedTextInputView != 'undefined') {
          displayedText = linkFormView.displayedTextInputView.fieldView.element.value;
        }
        // Clear out linkit attributes for external URLs but leave attributes
        // to prevent issues (see #3535098).
        if (this._isValidHttpUrl(args[0])) {
          args[1]['linkit_attributes'] = {
            'displayedText': displayedText,
          }
        }
        else {
          // In CKEditor v45+ decorators go in the second argument (args[1]).
          args[1]['linkit_attributes'] = {
            'linkDataEntityType': this.entityType,
            'linkDataEntityUuid': this.entityUuid,
            'linkDataEntitySubstitution': this.entitySubstitution,
            'displayedText': displayedText,
          }
        }
        // - The highest priority listener here
        //   injects the argument.
        // - The high priority listener in
        //   _addExtraAttributeOnLinkCommandExecute() gets that argument and sets
        //   the extra attribute.
        // - The normal (default) priority listener in ckeditor5-link sets
        //   (creates) the actual link.
      }, { priority: 'highest' });
    }, { priority: 'high' });
  }

  _handleDataLoadingIntoExtraFormField() {
    const editor = this.editor;
    const linkCommand = editor.commands.get('link');
    this.bind('entityType').to(linkCommand, 'linkDataEntityType');
    this.bind('entityUuid').to(linkCommand, 'linkDataEntityUuid');
    this.bind('entitySubstitution').to(linkCommand, 'linkDataEntitySubstitution');
  }

  _isValidHttpUrl(string) {
    let url;
    try {
      url = new URL(string);
    }
    catch (_) {
      return false;
    }
    return url.protocol === "https:";
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'Linkit';
  }
}

export default {
  Linkit,
};
