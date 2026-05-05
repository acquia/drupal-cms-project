/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved */
import { Plugin } from 'ckeditor5/src/core';
import { toWidget, Widget } from 'ckeditor5/src/widget';

import InsertIconCommand from './insertIconCommand';

/**
 * The Drupal Icon Editing plugin.
 *
 * Handles the transformation from the CKEditor 5 UI to Drupal-specific markup.
 *
 * @private
 */
export default class IconEditing extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [Widget];
  }

  constructor(editor) {
    super(editor);

    this.attrs = {
      drupalIconId: 'data-icon-id',
      drupalIconSettings: 'data-icon-settings',
      class: 'class',
      role: 'role',
      ariaLabel: 'aria-label',
      ariaHidden: 'aria-hidden',
    };
    this.converterAttributes = ['drupalIconId', 'drupalIconSettings'];
  }

  /**
   * @inheritdoc
   */
  init() {
    this._defineSchema();
    this._defineConverters();

    this.editor.commands.add('insertIcon', new InsertIconCommand(this.editor));
  }

  /**
   * Registers drupalIcon as an element in the DOM converter.
   *
   * @private
   */
  _defineSchema() {
    const { schema } = this.editor.model;

    schema.register('drupalIcon', {
      inheritAllFrom: '$inlineObject',
      allowAttributes: Object.keys(this.attrs),
    });

    // Register `<drupal-icon>` as a block element in the DOM converter. This
    // ensures that the DOM converter knows to handle the `<drupal-icon>` as a
    // block element.
    // @todo copy from Media, do we need it?
    // this.editor.editing.view.domConverter.blockElements.push('drupal-icon');
  }

  /**
   * Defines handling of material icon element in the content.
   *
   * @private
   */
  _defineConverters() {
    const { conversion } = this.editor;

    // Upcast Converters: determine how existing HTML is interpreted by the
    // editor. These trigger when an editor instance loads.
    conversion.for('upcast').elementToElement({
      model: 'drupalIcon',
      view: {
        name: 'drupal-icon',
      },
    });

    // Data Downcast Converters: converts stored model data into HTML.
    // These trigger when content is saved.
    conversion.for('dataDowncast').elementToElement({
      model: 'drupalIcon',
      view: {
        name: 'drupal-icon',
      },
    });

    // Editing Downcast Converters. These render the content to the user for
    // editing, i.e. this determines what gets seen in the editor. These trigger
    // after the Data Upcast Converters, and are re-triggered any time there
    // are changes to any of the models' properties.
    conversion
      .for('editingDowncast')
      .elementToElement({
        model: 'drupalIcon',
        view: (modelElement, { writer }) => {
          const container = writer.createContainerElement('span', {
            class: 'drupal-icon',
          });
          writer.setCustomProperty('drupalIcon', true, container);
          return toWidget(container, writer, {
            label: Drupal.t('Icon widget'),
          });
        },
      })
      .add((dispatcher) => {
        const converter = async (event, data, conversionApi) => {
          const viewWriter = conversionApi.writer;
          const modelElement = data.item;
          const container = conversionApi.mapper.toViewElement(modelElement);
          const existingPreview = container.getChild(0);

          if (existingPreview) {
            viewWriter.remove(existingPreview);
          }

          const iconPreview = viewWriter.createRawElement('span', {
            'data-drupal-icon-preview': 'loading',
          });

          viewWriter.insert(
            viewWriter.createPositionAt(container, 0),
            iconPreview,
          );

          try {
            const { preview } = await IconEditing._fetchIcon(modelElement);
            if (!iconPreview) {
              return;
            }

            this.editor.editing.view.change((writer) => {
              const iconPreviewContainer = writer.createRawElement(
                'span',
                { 'data-drupal-icon-preview': 'ready' },
                (domElement) => {
                  domElement.innerHTML = preview;
                },
              );

              writer.insert(
                writer.createPositionBefore(iconPreview),
                iconPreviewContainer,
              );
              writer.remove(iconPreview);
            });
          } catch (error) {
            // eslint-disable-next-line no-console
            console.error('Error fetching icon preview:', error);
          }
        };

        this.converterAttributes.forEach((attribute) => {
          dispatcher.on(`attribute:${attribute}:drupalIcon`, converter);
        });

        return dispatcher;
      });

    // Set attributeToAttribute conversion for all supported attributes.
    Object.keys(this.attrs).forEach((modelKey) => {
      const attributeMapping = {
        model: {
          key: modelKey,
          name: 'drupalIcon',
        },
        view: {
          name: 'drupal-icon',
          key: this.attrs[modelKey],
        },
      };
      // Attributes should be rendered only in dataDowncast to avoid having
      // unfiltered data-attributes on the Drupal Icon widget.
      conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
      conversion.for('upcast').attributeToAttribute(attributeMapping);
    });
  }

  /**
   * Fetches preview from the server.
   *
   * @param {module:engine/model/element~Element} modelElement
   *   The model element which preview should be loaded.
   * @return {Promise<{preview: string, label: string}>}
   *   A promise that returns an object.
   *
   * @private
   */
  static async _fetchIcon(modelElement) {
    let settings = modelElement.getAttribute('drupalIconSettings');
    if (typeof settings === 'undefined') {
      settings = '';
    }
    const query = {
      icon_id: modelElement.getAttribute('drupalIconId'),
      settings,
    };

    const response = await fetch(
      `${Drupal.url('ui-icons/icon/preview')}?${new URLSearchParams(query)}`,
    );

    if (response.ok) {
      const preview = await response.text();
      return { preview };
    }

    // @todo from filter settings if set?
    const title = `The referenced icon: "${modelElement.getAttribute('drupalIconId')}" is missing and needs to be re-embedded.`;
    const error = `<span class="drupal-icon"><svg width="15" height="14" fill="none" xmlns="http://www.w3.org/2000/svg"><title>${title}</title><path d="M7.002 0a7 7 0 100 14 7 7 0 000-14zm3 5c0 .551-.16 1.085-.477 1.586l-.158.22c-.07.093-.189.241-.361.393a9.67 9.67 0 01-.545.447l-.203.189-.141.129-.096.17L8 8.369v.63H5.999v-.704c.026-.396.078-.73.204-.999a2.83 2.83 0 01.439-.688l.225-.21-.01-.015.176-.14.137-.128c.186-.139.357-.277.516-.417l.148-.18A.948.948 0 008.002 5 1.001 1.001 0 006 5H4a3 3 0 016.002 0zm-1.75 6.619a.627.627 0 01-.625.625h-1.25a.627.627 0 01-.626-.625v-1.238c0-.344.281-.625.626-.625h1.25c.344 0 .625.281.625.625v1.238z" fill="#d72222"/></svg></span>`;
    return { preview: error };
  }
}
