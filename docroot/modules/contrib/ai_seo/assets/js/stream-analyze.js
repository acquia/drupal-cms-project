/**
 * @file
 * Streams AI SEO/GEO analysis and renders the report live as markdown.
 *
 * Chunks arrive as SSE events and are immediately rendered into the page using
 * a lightweight markdown renderer tailored to the report format. The user sees
 * the report building word-by-word — no spinner staring.
 */
(function (Drupal, drupalSettings) {

  'use strict';

  // ---------------------------------------------------------------------------
  // Minimal markdown renderer (no external dependency).
  // Handles exactly what the AI report format produces.
  // ---------------------------------------------------------------------------

  function escHtml(text) {
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function inlineMarkdown(text) {
    text = escHtml(text);
    // Bold
    text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    // Italic
    text = text.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    // Inline code
    text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
    return text;
  }

  function renderMarkdown(text) {
    const lines = text.split('\n');
    let html = '';
    let inCodeBlock = false;
    let codeLang = '';
    let codeLines = [];
    let inList = false;
    let listType = '';

    function closeList() {
      if (inList) {
        html += listType === 'ul' ? '</ul>\n' : '</ol>\n';
        inList = false;
        listType = '';
      }
    }

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];

      // --- Code fences ---
      if (line.startsWith('```')) {
        if (!inCodeBlock) {
          closeList();
          inCodeBlock = true;
          codeLang = line.slice(3).trim();
          codeLines = [];
        }
        else {
          inCodeBlock = false;
          const langClass = codeLang ? ' class="language-' + escHtml(codeLang) + '"' : '';
          html += '<pre><code' + langClass + '>' + escHtml(codeLines.join('\n')) + '</code></pre>\n';
          codeLines = [];
        }
        continue;
      }

      if (inCodeBlock) {
        codeLines.push(line);
        continue;
      }

      // --- Headings ---
      if (line.startsWith('#### ')) {
        closeList();
        html += '<h4>' + inlineMarkdown(line.slice(5)) + '</h4>\n';
        continue;
      }
      if (line.startsWith('### ')) {
        closeList();
        html += '<h3>' + inlineMarkdown(line.slice(4)) + '</h3>\n';
        continue;
      }
      if (line.startsWith('## ')) {
        closeList();
        html += '<h2>' + inlineMarkdown(line.slice(3)) + '</h2>\n';
        continue;
      }
      if (line.startsWith('# ')) {
        closeList();
        html += '<h1>' + inlineMarkdown(line.slice(2)) + '</h1>\n';
        continue;
      }

      // --- Horizontal rule ---
      if (line.match(/^[-*_]{3,}$/)) {
        closeList();
        html += '<hr>\n';
        continue;
      }

      // --- Blockquote ---
      if (line.startsWith('> ')) {
        closeList();
        html += '<blockquote><p>' + inlineMarkdown(line.slice(2)) + '</p></blockquote>\n';
        continue;
      }

      // --- Unordered list ---
      if (line.match(/^[-*] /)) {
        if (!inList || listType !== 'ul') {
          closeList();
          html += '<ul>\n';
          inList = true;
          listType = 'ul';
        }
        html += '<li>' + inlineMarkdown(line.slice(2)) + '</li>\n';
        continue;
      }

      // --- Ordered list ---
      const olMatch = line.match(/^(\d+)\. /);
      if (olMatch) {
        if (!inList || listType !== 'ol') {
          closeList();
          html += '<ol>\n';
          inList = true;
          listType = 'ol';
        }
        html += '<li>' + inlineMarkdown(line.slice(olMatch[0].length)) + '</li>\n';
        continue;
      }

      // --- Empty line ---
      if (line.trim() === '') {
        closeList();
        continue;
      }

      // --- Paragraph ---
      closeList();
      html += '<p>' + inlineMarkdown(line) + '</p>\n';
    }

    closeList();

    // Incomplete code block at the end (still streaming) — show what we have.
    if (inCodeBlock && codeLines.length > 0) {
      html += '<pre><code>' + escHtml(codeLines.join('\n')) + '</code></pre>\n';
    }

    return html;
  }

  // ---------------------------------------------------------------------------
  // Drupal behavior
  // ---------------------------------------------------------------------------

  // ---------------------------------------------------------------------------
  // Draft streaming — attaches to [data-ai-seo-stream-url] divs injected by
  // the AJAX modal callback in ai_seo.module.
  // ---------------------------------------------------------------------------

  // ---------------------------------------------------------------------------
  // Field value capture for nested paragraph fields.
  //
  // Two problems prevent getUserInput() from seeing the field value in PHP:
  //  1. CKEditor 5 only syncs content to its hidden textarea on form submit,
  //     and that textarea has display:none so jQuery skips it in serialization.
  //  2. Plain text/textarea fields inside deeply nested Paragraphs subforms
  //     also fail to appear in the POST data reliably.
  //
  // Fix: on mousedown (fires before click → before Drupal AJAX collects the
  // form), read the value directly from the DOM and store it in a dedicated
  // hidden input (_ai_seo_value_override), keyed by the field's value-parents
  // path so PHP can match it to the right field. PHP uses this override first,
  // falling back to getUserInput() only when the override is absent.
  //
  // Value source priority:
  //  1. CKEditor 5 editor.getData()  — live HTML, works even if textarea is hidden
  //  2. DOM element by [name] lookup — builds name from data-value-parents path
  // ---------------------------------------------------------------------------

  Drupal.behaviors.aiSeoFieldCke5Sync = {
    attach: function (context) {
      once('ai-seo-cke5-sync', '.ai-seo-field-btn', context).forEach(function (btn) {
        btn.addEventListener('mousedown', function () {
          var outer = btn.closest('.ai-seo-field-outer');
          if (!outer) {
            return;
          }

          var capturedValue = null;

          // 1. CKEditor 5: find the editor whose contenteditable sits inside
          //    this field wrapper and call getData() for the live HTML.
          if (typeof Drupal.CKEditor5Instances !== 'undefined') {
            Drupal.CKEditor5Instances.forEach(function (editor) {
              if (capturedValue !== null) {
                return;
              }
              try {
                var editable = editor.ui.view.editable.element;
                if (editable && outer.contains(editable)) {
                  capturedValue = editor.getData();
                }
              }
              catch (_) {}
            });
          }

          // 2. Plain textfield / textarea: build the exact HTML name attribute
          //    from data-value-parents and query the DOM for that element.
          //    This bypasses any form-serialization exclusion for nested fields.
          if (capturedValue === null) {
            var valueParents = [];
            try {
              valueParents = JSON.parse(btn.dataset.valueParents || '[]');
            }
            catch (_) {}

            if (valueParents.length > 0) {
              // ['field_sections', 0, 'subform', 'field_content', 0, 'value']
              // → 'field_sections[0][subform][field_content][0][value]'
              var inputName = valueParents[0] + valueParents.slice(1).map(function (p) {
                return '[' + p + ']';
              }).join('');

              var el = document.querySelector('[name="' + CSS.escape(inputName) + '"]');
              if (el && (el.value || el.value === '')) {
                capturedValue = el.value;
              }
            }
          }

          if (capturedValue === null) {
            return;
          }

          var form = btn.closest('form');
          if (!form) {
            return;
          }

          var override = form.querySelector('[name="_ai_seo_value_override"]');
          if (!override) {
            override = document.createElement('input');
            override.type = 'hidden';
            override.name = '_ai_seo_value_override';
            form.appendChild(override);
          }

          override.value = JSON.stringify({
            path: btn.dataset.valueParents || '[]',
            value: capturedValue,
          });
        });
      });
    },
  };

  Drupal.behaviors.aiSeoDraftStream = {
    attach: function (context) {
      const nodes = (context.querySelectorAll || function (s) { return document.querySelectorAll(s); }).call(context, '[data-ai-seo-stream-url]');

      nodes.forEach(function (previewDiv) {
        if (previewDiv.dataset.aiSeoStreamAttached) {
          return;
        }
        previewDiv.dataset.aiSeoStreamAttached = '1';

        const streamUrl  = previewDiv.dataset.aiSeoStreamUrl;
        const csrfToken  = previewDiv.dataset.aiSeoStreamToken;
        const key        = previewDiv.dataset.aiSeoStreamKey;
        const headerDiv  = document.getElementById('ai-seo-draft-stream-header');

        const url = new URL(streamUrl, window.location.origin);
        url.searchParams.set('token', csrfToken);
        url.searchParams.set('key',   key);

        let fullText      = '';
        let renderPending = false;
        let userScrolledUp = false;

        function isAtBottom(el) {
          return el.scrollTop + el.clientHeight >= el.scrollHeight - 10;
        }

        previewDiv.addEventListener('scroll', function () {
          userScrolledUp = !isAtBottom(previewDiv);
        });

        function scheduleRender() {
          if (renderPending) return;
          renderPending = true;
          requestAnimationFrame(function () {
            renderPending = false;
            previewDiv.innerHTML = renderMarkdown(fullText);
            if (!userScrolledUp) {
              previewDiv.scrollTop = previewDiv.scrollHeight;
            }
          });
        }

        const es = new EventSource(url.toString());

        es.onmessage = function (event) {
          let data;
          try {
            data = JSON.parse(event.data);
          }
          catch (_) {
            return;
          }

          if (data.type === 'chunk') {
            fullText += data.text;
            scheduleRender();
          }
          else if (data.type === 'done_draft') {
            es.close();
            if (headerDiv) {
              headerDiv.innerHTML = '<span>' + Drupal.t('Analysis complete.') + '</span>';
            }
          }
          else if (data.type === 'error') {
            es.close();
            previewDiv.innerHTML =
              '<div class="messages messages--error">' +
                Drupal.checkPlain(data.message) +
              '</div>';
            if (headerDiv) {
              headerDiv.style.display = 'none';
            }
          }
        };

        es.onerror = function () {
          if (es.readyState === EventSource.CLOSED) {
            return;
          }
          es.close();
          previewDiv.innerHTML =
            '<div class="messages messages--error">' +
              Drupal.t('Connection failed. Please close this dialog and try again.') +
            '</div>';
          if (headerDiv) {
            headerDiv.style.display = 'none';
          }
        };
      });
    },
  };

  Drupal.behaviors.aiSeoStream = {
    attach: function (context) {
      const settings = drupalSettings.aiSeo;
      if (!settings || !settings.streamUrl) {
        return;
      }

      const form = context.querySelector
        ? context.querySelector('form#analyze-url-form')
        : document.querySelector('form#analyze-url-form');

      if (!form || form.dataset.aiSeoStreamAttached) {
        return;
      }
      form.dataset.aiSeoStreamAttached = '1';

      const btn = form.querySelector('.btn--analyze');
      if (!btn) {
        return;
      }

      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const reportType   = (form.querySelector('[name="report_type"]') || {}).value || 'full';
        const langcode     = (form.querySelector('[name="langcode"]')    || {}).value || '';
        const revisionEl   = form.querySelector('[name="revision_id"]');
        const anonEl       = form.querySelector('[name="request_as_anonymous"]');
        const revisionId   = revisionEl ? revisionEl.value : '';
        const requestAsAnon = anonEl ? (anonEl.checked ? '1' : '0') : '1';

        const url = new URL(settings.streamUrl, window.location.origin);
        url.searchParams.set('token',                settings.csrfToken);
        url.searchParams.set('report_type',          reportType);
        url.searchParams.set('langcode',             langcode);
        url.searchParams.set('request_as_anonymous', requestAsAnon);
        if (revisionId) {
          url.searchParams.set('revision_id', revisionId);
        }

        // Replace the form area with a live preview container.
        const resultsDiv = document.getElementById('seo-analyze-results');
        if (resultsDiv) {
          resultsDiv.innerHTML =
            '<div class="ai-seo-stream-wrap">' +
              '<div class="ai-seo-stream-header">' +
                '<div class="ai-seo-progress__spinner" aria-hidden="true"></div>' +
                '<span>' + Drupal.t('Generating report…') + '</span>' +
              '</div>' +
              '<div class="ai-seo-stream-preview reports__container seo-report-body"></div>' +
            '</div>';
        }

        btn.disabled = true;
        btn.classList.add('is-loading');

        const previewDiv = resultsDiv && resultsDiv.querySelector('.ai-seo-stream-preview');
        let fullText = '';
        let renderPending = false;
        let userScrolledUp = false;

        function isAtBottom(el) {
          return el.scrollTop + el.clientHeight >= el.scrollHeight - 10;
        }

        if (previewDiv) {
          previewDiv.addEventListener('scroll', function () {
            userScrolledUp = !isAtBottom(previewDiv);
          });
        }

        function scheduleRender() {
          if (renderPending) return;
          renderPending = true;
          requestAnimationFrame(function () {
            renderPending = false;
            if (previewDiv) {
              previewDiv.innerHTML = renderMarkdown(fullText);
              if (!userScrolledUp) {
                previewDiv.scrollTop = previewDiv.scrollHeight;
              }
            }
          });
        }

        const es = new EventSource(url.toString());

        es.onmessage = function (event) {
          let data;
          try {
            data = JSON.parse(event.data);
          }
          catch (_) {
            return;
          }

          if (data.type === 'chunk') {
            fullText += data.text;
            scheduleRender();
          }
          else if (data.type === 'done') {
            es.close();
            window.location.href = data.redirect;
          }
          else if (data.type === 'error') {
            es.close();
            btn.disabled = false;
            btn.classList.remove('is-loading');
            if (resultsDiv) {
              resultsDiv.innerHTML =
                '<div class="messages messages--error">' +
                  Drupal.checkPlain(data.message) +
                '</div>';
            }
          }
        };

        es.onerror = function () {
          if (es.readyState === EventSource.CLOSED) {
            return;
          }
          es.close();
          btn.disabled = false;
          btn.classList.remove('is-loading');
          if (resultsDiv) {
            resultsDiv.innerHTML =
              '<div class="messages messages--error">' +
                Drupal.t('Connection failed. Please try again.') +
              '</div>';
          }
        };
      });
    },
  };

}(Drupal, drupalSettings));
