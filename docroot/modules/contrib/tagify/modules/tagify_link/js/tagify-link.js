/**
 * @file
 * Tagify behaviour for link field widgets.
 */

// cspell:ignore whitelist

/* global Tagify */

(function (Drupal, once) {
  /**
   * Builds the entity-id badge markup.
   * @param {string|number} entityId - The referenced entity id.
   * @return {string} The entity-id markup, or an empty string.
   */
  function entityIdMarkup(entityId) {
    return entityId
      ? `<div class='tagify__tag_with-entity-id'><div class='tagify__tag__entity-id-wrap'><span class='tagify__tag-entity-id'>${Drupal.checkPlain(
          String(entityId),
        )}</span></div></div>`
      : '';
  }

  /**
   * Wraps the matched portion of a label in <strong>, escaping each piece.
   * @param {string} inputTerm - The full label to highlight.
   * @param {string} searchTerm - The typed text to match.
   * @return {string} The label with matches wrapped in <strong>.
   */
  function highlightMatchingLetters(inputTerm, searchTerm) {
    const term = inputTerm == null ? '' : inputTerm.toString();
    const search = searchTerm == null ? '' : searchTerm.toString();
    const escapedSearch = search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    if (!escapedSearch) {
      return Drupal.checkPlain(term);
    }
    // Split on the RAW term then escape each piece, so the <strong> wrapping
    // never lands inside an HTML entity produced by escaping.
    const regex = new RegExp(`(${escapedSearch})`, 'gi');
    return term
      .split(regex)
      .map((segment, index) =>
        index % 2 === 1
          ? `<strong>${Drupal.checkPlain(segment)}</strong>`
          : Drupal.checkPlain(segment),
      )
      .join('');
  }

  /**
   * Builds the info-label badge markup.
   * @param {string} infoLabel - The extra information to show.
   * @return {string} The info-label markup, or an empty string.
   */
  function infoLabelMarkup(infoLabel) {
    return infoLabel
      ? `<div class='tagify__tag__info-label-wrap'><span class='tagify__tag-info-label'>${infoLabel}</span></div>`
      : '';
  }

  Drupal.behaviors.tagifyLink = {
    attach(context) {
      once('tagify-link', 'input.tagify-link-widget', context).forEach(
        (input) => {
          const { dataset } = input;
          const matchLimit = parseInt(dataset.matchLimit || 20, 10);
          const { autocompleteUrl } = dataset;
          const showEntityId = dataset.showEntityId === '1';
          const targetType = dataset.targetType || 'node';
          const placeholder = dataset.placeholder || '';
          const dropdownEnabled = parseInt(
            dataset.suggestionsDropdown ?? 1,
            10,
          );

          // Capture rendered width before tagify hides the input.
          const inputWidth = input.getBoundingClientRect().width;

          const initialValue = input.value;
          input.value = '';

          const tagify = new Tagify(input, {
            maxTags: 1,
            placeholder,
            enforceWhitelist: false,
            // Entity tags save as "entity:<type>/<id>" (core stores it
            // verbatim); free entries save their raw value.
            originalInputValueFormat: (values) => {
              if (!values.length) {
                return '';
              }
              const tag = values[0];
              return tag.entity_id
                ? `entity:${targetType}/${tag.entity_id}`
                : tag.value;
            },
            editTags: false,
            templates: {
              tag(tagData) {
                // Tagify pre-escapes `value`, so only escape the raw `label`;
                // the `value` fallback (typed entries) is already escaped.
                const label =
                  tagData.label !== undefined
                    ? Drupal.checkPlain(tagData.label)
                    : tagData.value;
                const entityId = showEntityId
                  ? entityIdMarkup(tagData.entity_id)
                  : '';
                const textClass = entityId
                  ? 'tagify__tag-text-with-entity-id'
                  : 'tagify__tag-text';
                return (
                  `<tag title="${label}" contenteditable='false'` +
                  ` spellcheck='false' tabIndex="-1"` +
                  ` class="tagify__tag ${tagData.class || ''}"` +
                  ` ${this.getAttributes(tagData)}>` +
                  `<x id="tagify__tag-remove-button" title='Remove ${label}'` +
                  ` class='tagify__tag__removeBtn' role='button'` +
                  ` aria-label='remove ${label} tag' tabindex="0"></x>` +
                  `<div id="tagify__tag-items">${entityId}` +
                  `<span class="${textClass}">${label}</span>` +
                  `${infoLabelMarkup(tagData.info_label)}</div>` +
                  `</tag>`
                );
              },
              input() {
                const s = this.settings;
                return (
                  `<span contenteditable data-placeholder="${s.placeholder || ''}"` +
                  ' tabIndex="0" class="tagify__input" role="textbox"' +
                  ' aria-multiline="false"' +
                  ' style="flex:1;min-width:0;padding:var(--tag-pad,0.5em 0.3em);">' +
                  '</span>'
                );
              },
              dropdownItem(tagData) {
                const label = highlightMatchingLetters(
                  tagData.label ?? tagData.value,
                  this.state.inputText,
                );
                return (
                  `<div ${this.getAttributes(tagData)}` +
                  ` class='tagify__dropdown__item ${tagData.class || ''}'` +
                  ` tabindex="0" role="option">` +
                  `<div class="tagify__dropdown__item-highlighted">${label}</div>` +
                  `${infoLabelMarkup(tagData.info_label)}</div>`
                );
              },
              // Debounced so it doesn't flash while results are loading.
              dropdownItemNoMatch: Drupal.debounce(
                (data) =>
                  `<div class='${tagify.settings.classNames.dropdownItem} tagify--dropdown-item-no-match'` +
                  ` value="noMatch" tabindex="0" role="option">` +
                  `<p>${Drupal.t('No matching suggestions found for:')}</p>` +
                  `<strong class="tagify--value">${Drupal.checkPlain(data.value)}</strong></div>`,
                250,
              ),
            },
            dropdown: {
              enabled: dropdownEnabled,
              maxItems: matchLimit,
              closeOnSelect: true,
              highlightFirst: true,
              fuzzySearch: false,
              searchKeys: ['label'],
              classname: 'tagify-link-dropdown',
            },
          });

          // Match the input's exact pre-tagify width; use px so it's
          // independent of whatever container/display quirks tagify introduces.
          tagify.DOM.scope.style.display = 'flex';
          tagify.DOM.scope.style.width =
            inputWidth > 0 ? `${inputWidth}px` : '100%';

          // Restore the existing value as a tag.
          if (dataset.defaultValue) {
            try {
              tagify.addTags(JSON.parse(dataset.defaultValue));
            } catch (e) {
              tagify.addTags([{ value: initialValue, label: initialValue }]);
            }
          } else if (initialValue) {
            tagify.addTags([{ value: initialValue, label: initialValue }]);
          }

          if (!autocompleteUrl) {
            return;
          }

          /**
           * Fetches suggestions for a query and shows the dropdown.
           * @param {string} q - The search query (may be empty for on-click).
           */
          function loadSuggestions(q) {
            tagify.loading(true);
            fetch(`${autocompleteUrl}?q=${encodeURIComponent(q)}`)
              .then((r) => r.json())
              .then((results) => {
                tagify.whitelist = results.map((r) =>
                  r.entity_id !== undefined
                    ? {
                        // entity_id drives the saved URI; value is display only.
                        value: r.label,
                        label: r.label,
                        entity_id: r.entity_id,
                        info_label: r.info_label,
                      }
                    : { value: r.value, label: r.label || r.value },
                );
                // Pass the query so the dropdown highlight has something to
                // match (this.state.inputText).
                tagify.loading(false).dropdown.show(q);
              })
              .catch(() => {
                tagify.loading(false);
              });
          }

          let timer;
          tagify.on('input', (e) => {
            const q = e.detail.value;
            clearTimeout(timer);

            if (!q) {
              tagify.whitelist = [];
              return;
            }

            timer = setTimeout(() => loadSuggestions(q), 300);
          });

          // 'On click' mode: fetch with an empty query when the input is
          // clicked.
          if (!dropdownEnabled) {
            tagify.DOM.input.addEventListener('click', () => {
              if (!tagify.value.length) {
                loadSuggestions('');
              }
            });
          }
        },
      );
    },
  };
})(Drupal, once);
