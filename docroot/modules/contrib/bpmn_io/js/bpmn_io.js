(function ($, Drupal, drupalSettings) {

  Drupal.bpmn_io = {};

  Drupal.behaviors.bpmn_io = {
    attach: function (context, settings) {
      if (Drupal.bpmn_io.modeler === undefined) {
        window.addEventListener('resize', function () {
          let container = $('#bpmn-io');
          let offset = container.offset();
          let width = container.width();
          $('#bpmn-io .canvas')
            .css('top', offset.top)
            .css('left', offset.left)
            .css('width', width);
          $('#bpmn-io .property-panel')
            .css('max-height', $(window).height() - offset.top);
        }, false);
        window.dispatchEvent(new Event('resize'));

        Drupal.bpmn_io.observed = [];
        Drupal.bpmn_io.readOnly = settings.modeler_api.mode === 'view';
        Drupal.bpmn_io.id = settings.bpmn_io.id;
        Drupal.bpmn_io.isNew = settings.bpmn_io.is_new;
        Drupal.bpmn_io.selectHandlerActive = true;
        Drupal.bpmn_io.modeler = window.modeler;
        Drupal.bpmn_io.layoutProcess = window.layoutProcess;
        Drupal.bpmn_io.expectToBeAccessible = window.expectToBeAccessible;
        Drupal.bpmn_io.expectToBeAccessible($('#bpmn-io .canvas'));
        Drupal.bpmn_io.eventBus = Drupal.bpmn_io.modeler.get('eventBus');
        Drupal.bpmn_io.loader = Drupal.bpmn_io.modeler.get('elementTemplatesLoader');
        Drupal.bpmn_io.loader.setTemplates(settings.bpmn_io.templates);
        Drupal.bpmn_io.elementTemplates = Drupal.bpmn_io.modeler.get('elementTemplates');
        Drupal.bpmn_io.templateChooser = Drupal.bpmn_io.modeler.get('elementTemplateChooser');
        Drupal.bpmn_io.eventBus.fire('i18n.changed');
        Drupal.bpmn_io.open(settings.bpmn_io.bpmn);
        Drupal.bpmn_io.modeler.get('minimap').close();
        Drupal.bpmn_io.eventHandler();
        $('#bpmn-io .djs-container').append($('#bpmn-io-widgets'));

        if (Drupal.bpmn_io.readOnly) {
          $('#bpmn-io-widgets .widget.paste, #bpmn-io-widgets .widget.layout').remove();
          $('.djs-palette, .djs-context-pad-parent').addClass('hidden');
        }
        $('form .button.modeler_api-save').click(function () {
          Drupal.bpmn_io.export();
          return false;
        });
        $('#bpmn-io-widgets .widget.search').click(function () {
          let editorActions = Drupal.bpmn_io.modeler.get("editorActions");
          editorActions.trigger("find");
          return false;
        });
        $('#bpmn-io-widgets .widget.zoom-in').click(function () {
          Drupal.bpmn_io.canvas.zoom(Drupal.bpmn_io.canvas.zoom()+.5);
          return false;
        });
        $('#bpmn-io-widgets .widget.zoom-out').click(function () {
          Drupal.bpmn_io.canvas.zoom(Drupal.bpmn_io.canvas.zoom()-.25);
          return false;
        });
        $('#bpmn-io-widgets .widget.zoom-fit').click(function () {
          Drupal.bpmn_io.canvas.zoom('fit-viewport');
          return false;
        });
        $('#bpmn-io-widgets .widget.paste').click(function () {
          Drupal.bpmn_io.pasteFromLocalStorage();
          return false;
        }).attr('disabled', 'disabled');
        $('#bpmn-io-widgets .widget.copy').click(function () {
          Drupal.bpmn_io.copyToLocalStorage();
          return false;
        }).attr('disabled', 'disabled');
        $('#bpmn-io-widgets .widget.svg').click(function () {
          Drupal.bpmn_io.saveSVG();
          return false;
        });
        $('#bpmn-io-widgets .widget.layout').click(function () {
          Drupal.bpmn_io.autoLayout();
          return false;
        });
        $('#bpmn-io-widgets .widget.info').click(function () {
          let element = Drupal.bpmn_io.canvas.getRootElement();
          Drupal.bpmn_io.loadConfigForm(element);
          return false;
        });
        $('#bpmn-io-widgets .widget.minimap').append($('.djs-minimap'));
        $('form.ai-agent-form').submit(function () {
          Drupal.bpmn_io.addDataToFormField();
          return true;
        });
      }
      Drupal.bpmn_io.addObserver('admin-toolbar');
      Drupal.bpmn_io.addObserver('gin_sidebar');
      Drupal.bpmn_io.addObserver('drupal-off-canvas-wrapper');
      Drupal.bpmn_io.prepareMessages();
    },
  };

  Drupal.bpmn_io.alterPaletteEntries = function (entries) {
    let mapping = {
      links: 'global-connect-tool',
      events: 'create.start-event',
      gateways: 'create.exclusive-gateway',
      tasks: 'create.task',
      subprocesses: 'create.subprocess-expanded',
      participants: 'create.participant-expanded',
      na01: 'create.intermediate-event',
      na02: 'create.end-event',
      na03: 'create.data-object',
      na04: 'create.data-store',
      na05: 'create.group',
    };
    for (let key in mapping) {
      if (!drupalSettings.bpmn_io.supportedTypes.includes(key)) {
        delete entries[mapping[key]];
      }
    }
  };

  Drupal.bpmn_io.alterContextPadEntries = function (target, entries) {
    let mapping = {
      links: 'connect',
      events: '',
      gateways: 'append.gateway',
      tasks: 'append.task',
      subprocesses: 'append.subprocess-expanded',
      annotations: 'append.text-annotation',
      na01: 'append.end-event',
      na02: 'append.intermediate-event',
    };
    for (let key in mapping) {
      if (mapping[key] !== '' && !drupalSettings.bpmn_io.supportedTypes.includes(key)) {
        delete entries[mapping[key]];
      }
    }
    if (target.type !== 'bpmn:SubProcess') {
      delete entries.replace;
    }
  };

  Drupal.bpmn_io.addObserver = function (id) {
    if (Drupal.bpmn_io.observed.includes(id)) {
      return;
    }
    let element = document.getElementById(id);
    if (element === null) {
      return;
    }
    Drupal.bpmn_io.observed.push(id);
    new ResizeObserver(function () {
      setTimeout(function () {
        window.dispatchEvent(new Event('resize'));
      }, 200);
    }).observe(element);
  };

  Drupal.bpmn_io.prepareMessages = function () {
    $('.messages-list:not(.bpmn-io-processed)')
      .addClass('bpmn-io-processed')
      .click(function () {
        $(this).empty();
      });
  };

  Drupal.bpmn_io.addDataToFormField = async function () {
    let result = await Drupal.bpmn_io.modeler.saveXML({ format: true });
    $('input[name="modeler_api_data"]')[0].value = result.xml;
    $('input#bio-properties-panel-id').remove();
  };

  Drupal.bpmn_io.export = async function () {
    $('.messages-list').empty();
    let result = await Drupal.bpmn_io.modeler.saveXML({ format: true });
    if (drupalSettings.modeler_api.token_url === undefined) {
      return;
    }
    let response = await fetch(drupalSettings.modeler_api.token_url);
    if (response.ok) {
      let token = await response.text();
      let request = Drupal.ajax({
        url: drupalSettings.modeler_api.save_url,
        submit: result.xml,
        beforeSend: function (xhr) {
          xhr.overrideMimeType("application/json;charset=UTF-8");
          xhr.setRequestHeader("X-CSRF-Token", token);
          xhr.setRequestHeader("X-Modeler-API-isNew", Drupal.bpmn_io.isNew);
        },
        progress: {
          type: 'fullscreen',
          message: Drupal.t('Saving model...'),
        },
      });
      request.execute();
    }
    Drupal.bpmn_io.prepareMessages();
  };

  Drupal.bpmn_io.open = async function (bpmnXML) {
    await Drupal.bpmn_io.modeler.importXML(bpmnXML);
    Drupal.bpmn_io.canvas = Drupal.bpmn_io.modeler.get('canvas');
    Drupal.bpmn_io.overlays = Drupal.bpmn_io.modeler.get('overlays');
    Drupal.bpmn_io.canvas.zoom('fit-viewport');
  };

  Drupal.bpmn_io.dragAndDrop = function (panel) {
    if (panel === undefined) {
      return;
    }
    let BORDER_SIZE = 4;
    let m_pos;

    function resize(e) {
      let dx = m_pos - e.x;
      m_pos = e.x;
      panel.style.width = (parseInt($(panel).outerWidth()) - BORDER_SIZE + dx) + 'px';
    }

    panel.addEventListener('mousedown', function (e) {
      if (e.offsetX < BORDER_SIZE) {
        m_pos = e.x;
        document.addEventListener('mousemove', resize, false);
      }
    }, false);
    document.addEventListener('mouseup', function () {
      document.removeEventListener('mousemove', resize, false);
    }, false);
  };

  Drupal.bpmn_io.saveSVG = function () {
    Drupal.bpmn_io.canvas.focus();
    Drupal.bpmn_io.modeler.saveSVG({ format: true }).then((model) => {
      let svgBlob = new Blob([model.svg], {
        type: 'image/svg+xml'
      });
      let downloadLink = document.createElement('a');
      downloadLink.download = Drupal.bpmn_io.id + '.svg';
      downloadLink.innerHTML = 'Get BPMN SVG';
      downloadLink.href = window.URL.createObjectURL(svgBlob);
      downloadLink.onclick = function (event) {
        document.body.removeChild(event.target);
      };
      downloadLink.style.visibility = 'hidden';
      document.body.appendChild(downloadLink);
      downloadLink.click();
    });
  };

  Drupal.bpmn_io.autoLayout = async function (annotations) {
    if (annotations === undefined) {
      // Collect annotations from current canvas.
      annotations = {};
      Drupal.bpmn_io.canvas.getRootElement().children.forEach(function (element) {
        if (element.type === 'bpmn:TextAnnotation') {
          annotations[element.id] = {
            text: element.businessObject.text,
            sources: {},
          };
        }
      });
      Drupal.bpmn_io.canvas.getRootElement().children.forEach(function (element) {
        if (element.type === 'bpmn:Association') {
          annotations[element.businessObject.targetRef.id].sources[element.id] = element.businessObject.sourceRef.id;
        }
      });
    }
    // Export and auto-layout the model.
    try {
      let result = await Drupal.bpmn_io.modeler.saveXML({ format: true });
      let diagramWithLayoutXML = await Drupal.bpmn_io.layoutProcess(result.xml);
      await Drupal.bpmn_io.open(diagramWithLayoutXML);
    } catch (err) {
      console.log(err);
      return;
    }
    if (annotations.length === 0) {
      return;
    }
    let bpmnFactory = Drupal.bpmn_io.modeler.get('bpmnFactory'),
        elementRegistry = Drupal.bpmn_io.modeler.get('elementRegistry'),
        elementFactory = Drupal.bpmn_io.modeler.get('elementFactory'),
        modeling = Drupal.bpmn_io.modeler.get('modeling');
    let sanitizeNextId = function (id) {
      if (id.includes(',')) {
        let [sourceId, targetId] = id.split(',');
        elementRegistry.get(sourceId).outgoing.forEach(function (outgoing) {
          if (
            outgoing.type === 'bpmn:SequenceFlow' &&
            outgoing.businessObject.targetRef.id === targetId &&
            outgoing.businessObject.modelerTemplate === undefined
          ) {
            id = outgoing.id;
          }
        });
      }
      return id;
    };
    // Create and conncet annotations.
    Object.keys(annotations).forEach((id) => {
      let bpmnType = `bpmn:TextAnnotation`;
      let data = annotations[id];
      let objectData = {id};
      if (data.text) {
        objectData.text = data.text;
      }
      let businessObject = bpmnFactory.create(bpmnType, objectData);
      let el = elementFactory.createShape({type: bpmnType, businessObject});
      // Find the container.
      let [[firstKey, firstId]] = Object.entries(data.sources);
      firstId = sanitizeNextId(firstId);
      let firstSource = elementRegistry.get(firstId);
      if (firstSource === undefined) {
        console.error('Could not find source for annotation ' + id);
        return;
      }
      let x, y;
      if (firstSource.type === 'bpmn:SequenceFlow') {
        x = firstSource.waypoints[0].original.x;
        y = firstSource.waypoints[0].original.y;
      }
      else {
        x = firstSource.x;
        y = firstSource.y;
      }
      // Add the shape to the diagram and attach it to the process.
      modeling.createShape(el, {x: x + 100, y: y - 100});

      // Create connections.
      let target = elementRegistry.get(id);
      Object.entries(data.sources).forEach((item) => {
        let [id, next] = item;
        next = sanitizeNextId(next);
        let attrs = {id: id, type: 'bpmn:Association'};
        let source = elementRegistry.get(next);
        if (source === undefined) {
          console.error('Could not find source for association ' + id);
          return;
        }
        modeling.createConnection(source, target, attrs);
      });
    });
    Drupal.bpmn_io.canvas.zoom('fit-viewport');
  };

  Drupal.bpmn_io.findExtensionValue = function (element, property) {
    let propertyValue;
    if (element.di.bpmnElement.extensionElements !== undefined) {
      element.di.bpmnElement.extensionElements.values.forEach(function (value) {
        if (value.values !== undefined) {
          value.values.forEach(function (innerValue) {
            if (innerValue.name === property) {
              propertyValue = innerValue.value;
            }
          });
        }
      });
    }
    return propertyValue;
  };

  Drupal.bpmn_io.setExtensionValue = function (element, propertyKey, propertyValue) {
    let found = false;
    let extensionElements;
    let properties;
    let moddle = Drupal.bpmn_io.modeler.get('moddle');
    let property = moddle.create('camunda:Property');
    property.name = propertyKey;
    property.value = propertyValue;
    if (element.di.bpmnElement.extensionElements === undefined) {
      extensionElements = moddle.create('bpmn:ExtensionElements');
      properties = moddle.create('camunda:Properties');
      extensionElements.get('values').push(properties);
    }
    else {
      extensionElements = element.di.bpmnElement.extensionElements;
      let i = 0;
      let j;
      element.di.bpmnElement.extensionElements.values.forEach(function (value) {
        if (value.values !== undefined) {
          j = 0;
          value.values.forEach(function (innerValue) {
            if (innerValue.name === propertyKey) {
              extensionElements.values[i].values[j].value = propertyValue;
              found = true;
            }
            j++;
          });
          if (!found) {
            properties = extensionElements.values[i];
          }
        }
        i++;
      });
      if (!found && properties === undefined) {
        properties = moddle.create('camunda:Properties');
        extensionElements.values.push(properties);
      }
    }
    if (!found) {
      properties.get('values').push(property);
    }
    return extensionElements;
  };

  Drupal.bpmn_io.updateModelMetadata = function (element, name, value) {
    switch (name) {
      case 'label':
        name = 'name';
        break;

      case 'version':
        name = 'versionTag';
        break;

      case 'model_id':
        name = 'id';
        Drupal.bpmn_io.id = value;
        break;

      case 'executable':
        name = 'isExecutable';
        value = value === true || value === 'yes' || value === 'true';
        break;

      case 'template':
        name = 'extensionElements';
        value = value === true || value === 'yes' || value === 'true';
        value = Drupal.bpmn_io.setExtensionValue(element, 'Template', value);
        break;

      case 'storage':
        name = 'extensionElements';
        value = Drupal.bpmn_io.setExtensionValue(element, 'Storage', value);
        break;

      case 'documentation':
        value = [ Drupal.bpmn_io.modeler.get('bpmnFactory').create('bpmn:Documentation', {text: value}) ];
        break;

      case 'tags':
        name = 'extensionElements';
        value = Drupal.bpmn_io.setExtensionValue(element, 'Tags', value);
        break;

      case 'changelog':
        name = 'extensionElements';
        value = Drupal.bpmn_io.setExtensionValue(element, 'Changelog', value);
        break;

      default:
        return;
    }
    element.di.bpmnElement[name] = value;
  };

  Drupal.bpmn_io.loadConfigForm = function (element, pluginId) {
    let config = {};
    if (element.type === 'bpmn:Process') {
      config.label = element.di.bpmnElement.name === 'undefined' ? '' : element.di.bpmnElement.name;
      config.version = element.di.bpmnElement.versionTag;
      config.model_id = element.di.bpmnElement.id;
      config.executable = element.di.bpmnElement.isExecutable;
      config.template = Drupal.bpmn_io.findExtensionValue(element, 'Template') ?? 'false';
      config.storage = Drupal.bpmn_io.findExtensionValue(element, 'Storage') ?? '';
      config.documentation = element.di.bpmnElement.documentation[0]?.text ?? '';
      config.tags = Drupal.bpmn_io.findExtensionValue(element, 'Tags');
      config.changelog = Drupal.bpmn_io.findExtensionValue(element, 'Changelog');
    }
    else {
      if (!element.di.bpmnElement.id.startsWith(pluginId)) {
        let prefix = pluginId.replace(':', '__') + '_',
            suffix = element.di.bpmnElement.id.split('_').pop(),
            counter = 0,
            unique = false,
            id,
            elementRegistry = Drupal.bpmn_io.modeler.get('elementRegistry');
        while (!unique) {
          id = prefix + suffix + (counter > 0 ? '_' + counter : '');
          if (elementRegistry.get(id) === undefined) {
            unique = true;
          }
          counter++;
        }
        Drupal.bpmn_io.modeler.get('modeling').updateProperties(element, {id: id});
      }
      element.di.bpmnElement.extensionElements.values.forEach(function (value) {
        if (value.name !== undefined) {
          config[value.name] = value.string;
        }
      });
    }

    $(document).one('ajaxComplete', function handleAjaxComplete() {
      let alwaysUpdate = [];
      let updateField = function (currentField) {
        let fieldName = $(currentField).attr('name');
        let newValue = ($(currentField).attr('type') === 'checkbox') ?
            ($(currentField).prop('checked') ? 'yes' : 'no') :
            $(currentField).val();
        if (element.type === 'bpmn:Process') {
          Drupal.bpmn_io.updateModelMetadata(element, fieldName, newValue);
        }
        else {
          if ($(currentField).attr('data-modeler-api-model-id') === '1') {
            Drupal.bpmn_io.canvas.getRootElement().di.bpmnElement.id = newValue;
            Drupal.bpmn_io.id = newValue;
          }
          let i = 0;
          element.di.bpmnElement.extensionElements.values.forEach(function (objvalue) {
            if (objvalue.name !== undefined && objvalue.name === fieldName) {
              element.di.bpmnElement.extensionElements.values[i].string = newValue;
              return true;
            }
            i++;
          });
        }
      };
      let listenForChanges = function (element, key) {
        $('#drupal-off-canvas form [name="' + key + '"]')
          .each(function () {
            if ($(this).hasClass('machine-name-target')) {
              alwaysUpdate.push(this);
            }
          })
          .on('keypress', function (e) {
            if (e.which === 13 && this.type !== 'textarea') {
              e.preventDefault();
              return false;
            }
          })
          .on('change', function () {
            updateField(this);
            alwaysUpdate.forEach(function (item) {
              updateField(item);
            });
          });
      };
      if (element.type === 'bpmn:Process') {
        for (let key in config) {
          listenForChanges(element, key);
        }
        return;
      }
      if (element.di.bpmnElement.name === undefined) {
        Drupal.bpmn_io.modeler.get('modeling').updateProperties(element, {name: $('.ui-dialog-titlebar h2').text()});
      }
      if (element.di.bpmnElement.extensionElements !== undefined) {
        element.di.bpmnElement.extensionElements.values.forEach(function (value) {
          if (value.name !== undefined) {
            listenForChanges(element, value.name);
          }
        });
      }

      $('#drupal-off-canvas-wrapper .widget.remove-template').click(function () {
        Drupal.bpmn_io.elementTemplates.removeTemplate(Drupal.bpmn_io.selectedElements[0]);
        return false;
      });
    });

    let request = Drupal.ajax({
      url: drupalSettings.modeler_api.config_url,
      submit: {
        entityId: Drupal.bpmn_io.id,
        isNew: Drupal.bpmn_io.isNew,
        type: element.type,
        pluginId: pluginId,
        config: config,
        width: Drupal.bpmn_io.getOffCanvasWidth(),
        readOnly: Drupal.bpmn_io.readOnly,
      },
      progress: {
        type: 'fullscreen',
        message: Drupal.t('Loading config form...'),
      },
    });
    request.execute();
  };

  Drupal.bpmn_io.getOffCanvasWidth = function () {
    let key = 'bpmnIo.' + drupalSettings.bpmn_io.owner + '.' + Drupal.bpmn_io.id + '.offCanvasWidth';
    let width = '';
    let offCanvas = document.getElementById('drupal-off-canvas-wrapper');
    if (offCanvas) {
      let style = offCanvas.currentStyle || window.getComputedStyle(offCanvas);
      width = parseFloat(style.width);
    }
    if (width === '') {
      width = localStorage.getItem(key) ?? '';
    }
    localStorage.setItem(key, width);
    return width;
  };

  Drupal.bpmn_io.closeOffCanvas = function () {
    $('#drupal-off-canvas-wrapper .ui-dialog-titlebar h2').empty();
    $('#drupal-off-canvas').empty();
  };

  Drupal.bpmn_io.elementSelected = function (elements) {
    Drupal.bpmn_io.closeOffCanvas();
    if (elements.length !== 1) {
      return;
    }
    if (elements[0].type === 'label') {
      return;
    }
    let element = elements[0];
    let templates = Drupal.bpmn_io.elementTemplates.getAll(element);
    if (templates.length === 0) {
      return;
    }
    let pluginId = Drupal.bpmn_io.findExtensionValue(element, 'pluginid');
    if (pluginId === undefined) {
      if (Drupal.bpmn_io.readOnly) {
        return;
      }
      if (templates.length > 1) {
        Drupal.bpmn_io.eventBus.fire('elementTemplates.select', {element: element});
        return true;
      }
      Drupal.bpmn_io.elementTemplates.applyTemplate(element, templates[0]);
      pluginId = Drupal.bpmn_io.findExtensionValue(element, 'pluginid');
    }
    Drupal.bpmn_io.loadConfigForm(element, pluginId);
  };

  Drupal.bpmn_io.checkClipboard = function () {
    try {
      let clipboardData = localStorage.getItem('bpmnio.' + drupalSettings.bpmn_io.owner + '.clipboard');
      if (clipboardData) {
        $('#bpmn-io-widgets .widget.paste').removeAttr('disabled');
      } else {
        $('#bpmn-io-widgets .widget.paste').attr('disabled', 'disabled');
      }
    } catch (error) {
      console.error('Error checking clipboard: ', error);
    }
    setTimeout(function () {Drupal.bpmn_io.checkClipboard()}, 1000);
  };


  Drupal.bpmn_io.eventHandler = function () {
    Drupal.bpmn_io.checkClipboard();
    Drupal.bpmn_io.eventBus.on('selection.changed', 500 , function (e) {
      if (e.newSelection.length === 0) {
        $('#bpmn-io-widgets .widget.copy').attr('disabled', 'disabled');
      }
      else {
        $('#bpmn-io-widgets .widget.copy').removeAttr('disabled');
      }
      Drupal.bpmn_io.selectedElements = e.newSelection;
      if (Drupal.bpmn_io.selectHandlerActive) {
        Drupal.bpmn_io.elementSelected(e.newSelection);
      }
    });
    Drupal.bpmn_io.eventBus.on('searchPad.opened', 1500 , function () {
      Drupal.bpmn_io.selectHandlerActive = false;
    });
    Drupal.bpmn_io.eventBus.on('searchPad.closed', 1500 , function () {
      Drupal.bpmn_io.selectHandlerActive = true;
    });
    Drupal.bpmn_io.eventBus.on('commandStack.elements.delete.preExecuted', 1500 , function () {
      Drupal.bpmn_io.selectHandlerActive = false;
    });
    Drupal.bpmn_io.eventBus.on('commandStack.elements.delete.postExecuted', 1500 , function () {
      Drupal.bpmn_io.selectHandlerActive = true;
    });
    Drupal.bpmn_io.eventBus.on('elementTemplateChooser.chosen', (e) => {
      Drupal.bpmn_io.eventBus.fire('popupMenu.close');
      Drupal.bpmn_io.elementTemplates.applyTemplate(e.element, e.template);
      let pluginId = Drupal.bpmn_io.findExtensionValue(e.element, 'pluginid');
      if (pluginId !== undefined) {
        Drupal.bpmn_io.loadConfigForm(e.element, pluginId);
      }
    });
  };

  Drupal.bpmn_io.copyToLocalStorage = function () {
    Drupal.bpmn_io.modeler.get('copyPaste').copy(Drupal.bpmn_io.selectedElements);
    localStorage.setItem('bpmnio.' + drupalSettings.bpmn_io.owner + '.clipboard', JSON.stringify(Drupal.bpmn_io.modeler.get('clipboard').get()));
  };

  Drupal.bpmn_io.pasteFromLocalStorage = function () {
    let createReviver = function (moddle) {
      let elCache = {};
      return function(key, object) {
        if (typeof object === 'object' && typeof object.$type === 'string') {
          var objectId = object.id;
          if (objectId && elCache[objectId]) {
            return elCache[objectId];
          }
          var type = object.$type;
          var attrs = Object.assign({}, object);
          delete attrs.$type;
          var newEl = moddle.create(type, attrs);
          if (objectId) {
            elCache[objectId] = newEl;
          }
          return newEl;
        }
        return object;
      };
    };
    let serializedCopy = localStorage.getItem('bpmnio.' + drupalSettings.bpmn_io.owner + '.clipboard');
    let parsedCopy = JSON.parse(serializedCopy, createReviver(Drupal.bpmn_io.modeler.get('moddle')));
    Drupal.bpmn_io.modeler.get('clipboard').set(parsedCopy);
    Drupal.bpmn_io.modeler.get('copyPaste').paste();
  };

})(jQuery, Drupal, drupalSettings);
