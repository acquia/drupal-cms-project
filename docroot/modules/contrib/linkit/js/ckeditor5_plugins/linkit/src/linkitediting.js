import { Plugin } from 'ckeditor5/src/core';
import { findAttributeRange } from 'ckeditor5/src/typing';
import { getCurrentLinkRange, getMajorVersion, extractTextFromLinkRange } from './utils.js';

export default class LinkitEditing extends Plugin {
  init() {
    this.attrs = ['linkDataEntityType', 'linkDataEntityUuid', 'linkDataEntitySubstitution'];
    this.attrsView = ['data-entity-type', 'data-entity-uuid', 'data-entity-substitution'];
    this._allowAndConvertExtraAttributes();
    this._removeExtraAttributesOnUnlinkCommandExecute();
    this._refreshExtraAttributeValues();
    this._addExtraAttributesOnLinkCommandExecute();
  }

  _allowAndConvertExtraAttributes() {
    const editor = this.editor;

    editor.model.schema.extend('$text', { allowAttributes: this.attrs });

    // Model -> View (DOM)
    this.attrs.forEach((attribute, i) => {
      editor.conversion.for('downcast').attributeToElement({
        model: attribute,
        view: (value, { writer }) => {
          const linkViewElement = writer.createAttributeElement('a', {
            [this.attrsView[i]]: value
          }, { priority: 5 });

          // Without it the isLinkElement() will not recognize the link and the UI will not show up
          // when the user clicks a link.
          writer.setCustomProperty('link', true, linkViewElement);

          return linkViewElement;
        }
      });

      // View (DOM/DATA) -> Model
      editor.conversion.for('upcast')
        .elementToAttribute({
          view: {
            name: 'a',
            attributes: {
              [this.attrsView[i]]: true,
            }
          },
          model: {
            key: attribute,
            value: viewElement => viewElement.getAttribute(this.attrsView[i]),
          }
        });
    });
  }

  _addExtraAttributesOnLinkCommandExecute() {
    const editor = this.editor;
    const linkCommand = editor.commands.get('link');
    let linkCommandExecuting = false;

    linkCommand.on('execute', (evt, args) => {
      if (linkCommandExecuting) {
        linkCommandExecuting = false;
        return;
      }

      // If the additional attribute was passed, we stop the default execution
      // of the LinkCommand. We're going to create Model#change() block for undo
      // and execute the LinkCommand together with setting the extra attribute.
      evt.stop();

      // Prevent infinite recursion by keeping records of when link command is
      // being executed by this function.
      linkCommandExecuting = true;
      // Any decorators should be an object provided as the second element to
      // the execute params.
      // If no linkit_attributes passed in event arguments (eg decorator updated), then get values from state.
      let linkitAttributes = [];
      const decoratorsArgIndex = 1;
      if (args && args[decoratorsArgIndex] && !args[decoratorsArgIndex]['linkit_attributes']) {
        this.attrs.forEach((attribute) => {
          linkitAttributes[attribute] = evt.source[attribute];
        });
        args[decoratorsArgIndex]['linkit_attributes'] = linkitAttributes;
      }
      else {
        linkitAttributes = args[decoratorsArgIndex]['linkit_attributes'];
      }
      args[decoratorsArgIndex]['linkit_attributes'] = linkitAttributes;
      const extraAttributeValues = linkitAttributes;
      const model = this.editor.model;
      const selection = model.document.selection;
      const displayedText = args[args.length - 1] || args[1]['linkit_attributes']['displayedText'];
      // Linkit can update the Href value, so we need to know what the updated
      // value is to properly target ranges when the selection is collapsed. See
      // ...if (selection.isCollapsed)... below.
      const currentHref = args[0];

      // Remove linkit_attributes from the decorators object before passing
      // to the link command. The CKEditor5 link command iterates over ALL
      // keys in the decorators and calls writer.setAttribute() for each.
      // Passing linkit_attributes (an object) as a decorator causes a bogus
      // model attribute to be set, which corrupts the Document Differ when
      // combined with text replacement at paragraph boundaries.
      // The entity attribute values are already captured in extraAttributeValues.
      delete args[decoratorsArgIndex]['linkit_attributes'];

      // Wrapping the original command execution in a model.change() block to
      // make sure there's a single undo step when the extra attribute is added.
      model.change((writer) => {
        const batch = writer.batch;

        // Execute the link command. In CKEditor5 v45+, this handles
        // text replacement and sets linkHref.
        editor.execute('link', ...args);

        // Enqueue extra attribute changes in a SEPARATE change block
        // using the same batch (single undo step). This separation
        // lets post-fixers (including GHS) process the text replacement
        // before we add extra attributes, preventing the Differ crash.
        model.enqueueChange(batch, (attrWriter) => {

          const updateAttributes = (range, removeSelection) => {
            this.attrs.forEach((attribute) => {
              if (extraAttributeValues[attribute]) {
                attrWriter.setAttribute(attribute, extraAttributeValues[attribute], range);
              } else {
                attrWriter.removeAttribute(attribute, range);
              }
              if (removeSelection) {
                attrWriter.setSelection(range.end);
                const { plugins } = this.editor;
                if (plugins.has('TwoStepCaretMovement') && getMajorVersion(CKEDITOR_VERSION) >= 45) {
                  // After replacing the text of the link, we need to move the caret to the end of the link,
                  // override it's gravity to forward to prevent keeping e.g. bold attribute on the caret
                  // which was previously inside the link.
                  //
                  // If the plugin is not available, the caret will be placed at the end of the link and the
                  // bold attribute will be kept even if command moved caret outside the link.
                  plugins.get('TwoStepCaretMovement')._handleForwardMovement();
                } else {
                  // Remove any attributes to prevent link splitting.
                  attrWriter.removeSelectionAttribute(attribute);
                }
              }
            });
          };

          const updateLinkTextIfNeeded = (range, displayedText) => {
            const linkText = extractTextFromLinkRange(range);
            if (!linkText) {
              return range;
            }
            // In case target attributes are updated or
            // Legacy check for CKEditor < v45 storage; Once Drupal < 10.3/11.2
            // are unsupported, this can be removed.
            if (getMajorVersion(CKEDITOR_VERSION) < 45 && typeof displayedText == "object") {
              displayedText = linkText;
            }
            // In a scenario where the displayedText is blank, fall back on the
            // linkText, and if that is empty, use the href from args[0].
            let newText = displayedText || linkText || args[0];
            let newRange = attrWriter.createRange(range.start, range.start.getShiftedBy(linkText.length));
            return newRange;
          };

          if (selection.isCollapsed) {
            let range = getCurrentLinkRange(model, selection, currentHref);
            if (!range) {
              console.info('No link range found');
              return;
            }
            range = updateLinkTextIfNeeded(range, displayedText);
            updateAttributes(range, true);
          } else {
            const ranges = model.schema.getValidRanges(selection.getRanges(), 'linkDataEntityType');
            for (const range of ranges) {
              updateAttributes(range);
            }
          }
        });
      });
    }, { priority: 'high' } );
  }

  _removeExtraAttributesOnUnlinkCommandExecute() {
    const editor = this.editor;
    const unlinkCommand = editor.commands.get('unlink');
    const model = this.editor.model;
    const selection = model.document.selection;

    let isUnlinkingInProgress = false;

    // Make sure all changes are in a single undo step so cancel the original unlink first in the high priority.
    unlinkCommand.on('execute', evt => {
      if (isUnlinkingInProgress) {
        return;
      }

      evt.stop();

      // This single block wraps all changes that should be in a single undo step.
      model.change(() => {
        // Now, in this single "undo block" let the unlink command flow naturally.
        isUnlinkingInProgress = true;

        // Do the unlinking within a single undo step.
        editor.execute('unlink');

        // Let's make sure the next unlinking will also be handled.
        isUnlinkingInProgress = false;

        // The actual integration that removes the extra attribute.
        model.change(writer => {
          // Get ranges to unlink.
          let ranges;

          this.attrs.forEach((attribute) => {
            if (selection.isCollapsed) {
              ranges = [findAttributeRange(
                selection.getFirstPosition(),
                attribute,
                selection.getAttribute(attribute),
                model
              )];
            } else {
              ranges = model.schema.getValidRanges(selection.getRanges(), attribute);
            }

            // Remove the extra attribute from specified ranges.
            for (const range of ranges) {
              writer.removeAttribute(attribute, range);
            }
          });
        });
      });
    }, { priority: 'high' });
  }

  _refreshExtraAttributeValues() {
    const editor = this.editor;
    const attributes = this.attrs
    const linkCommand = editor.commands.get('link');
    const model = this.editor.model;
    const selection = model.document.selection;

    attributes.forEach((attribute) => {
      linkCommand.set(attribute, null);
    });
    model.document.on('change', () => {
      attributes.forEach((attribute) => {
        linkCommand[attribute] = selection.getAttribute(attribute);
      });
    });
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'LinkitEditing';
  }
}
