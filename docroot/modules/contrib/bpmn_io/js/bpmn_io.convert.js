(function ($, Drupal) {

  Drupal.bpmn_io_convert = {
    initialized: false
  };

  Drupal.behaviors.bpmn_io_convert = {};

  /**
   * Import the elements.
   *
   * @param root
   *   The root element.
   */
  Drupal.behaviors.bpmn_io_convert.importElements = async function (root) {
    let bpmnFactory = Drupal.bpmn_io.modeler.get('bpmnFactory'),
      elementRegistry = Drupal.bpmn_io.modeler.get('elementRegistry'),
      elementFactory = Drupal.bpmn_io.modeler.get('elementFactory'),
      modeling = Drupal.bpmn_io.modeler.get('modeling');
    // Set metadata.
    for (let key in drupalSettings.bpmn_io_convert.metadata) {
      Drupal.bpmn_io.updateModelMetadata(root, key, drupalSettings.bpmn_io_convert.metadata[key]);
    }
    // Remember containers.
    let containers = {};
    let container = root;

    // Create elements.
    Object.keys(drupalSettings.bpmn_io_convert.elements).forEach((id) => {
      let bpmnType = `bpmn:${drupalSettings.bpmn_io_convert.bpmn_mapping[id]}`;
      // Skip conditions as they are not elements.
      if (bpmnType === 'bpmn:SequenceFlow') {
        return;
      }
      // Skip swimlanes for now as they are broken with auto-layout.
      if (bpmnType === 'bpmn:Participant') {
        return;
      }

      let data = drupalSettings.bpmn_io_convert.elements[id];
      let parentId = data.parentId;
      delete data.parentId;

      // Create the 'collection' for the configuration of the plugin.
      let extEl = Drupal.behaviors.bpmn_io_convert.createExtensionElements(data);

      // Create the object that contains our "business" logic.
      let objectData = {id};
      if (data.label) {
        objectData.name = data.label;
      }
      if (data.plugin) {
        objectData.modelerTemplate = `org.drupal.${drupalSettings.bpmn_io_convert.template_mapping[id]}.${data.plugin}`;
      }
      if (extEl) {
        objectData.extensionElements = extEl;
      }
      let businessObject = bpmnFactory.create(bpmnType, objectData);

      // Create the element shape.
      let el = elementFactory.createShape({type: bpmnType, businessObject});

      // Find the container.
      container = root;
      if (parentId !== null && parentId !== undefined) {
        container = containers[parentId];
      }
      // Add the shape to the diagram and attach it to the process.
      modeling.createShape(el, {x: Math.floor(Math.random() * 400) + 1, y: Math.floor(Math.random() * 400) + 1}, container);

      // Remember the element, if it's a subprocess.
      if (bpmnType === 'bpmn:SubProcess') {
        containers[data.plugin] = el;
      }
      // Remember the element, if it's a participant.
      if (bpmnType === 'bpmn:Participant') {
        containers[id] = el;
      }
    });

    // Connect elements.
    Object.keys(drupalSettings.bpmn_io_convert.elements).forEach((id) => {
      let data = drupalSettings.bpmn_io_convert.elements[id];
      if (!('successors' in data) || data.successors.length === 0) {
        return;
      }

      data.successors.forEach((next) => {
        // Determine source and target.
        let source = elementRegistry.get(id);
        let target = elementRegistry.get(next.id);
        if (source === undefined || target === undefined) {
          console.error(`Could not find element ${id} or ${next.id} when trying to create a connection. Skipping.`);
          return;
        }
        let attrs = {type: 'bpmn:SequenceFlow'};

        // Apply condition to the connection?
        if (next.condition) {
          let conditionData = drupalSettings.bpmn_io_convert.elements[next.condition]
          let bpmnType = `bpmn:${drupalSettings.bpmn_io_convert.bpmn_mapping[next.condition]}`;

          // Create the 'collection' for the configuration of the condition.
          let extEl = Drupal.behaviors.bpmn_io_convert.createExtensionElements(conditionData);

          let objectData = {
            id: next.condition,
            modelerTemplate: `org.drupal.${drupalSettings.bpmn_io_convert.template_mapping[next.condition]}.${conditionData.plugin}`,
            extensionElements: extEl
          };
          if (conditionData.label && conditionData.label !== next.condition) {
            objectData.name = conditionData.label;
          }

          // Create the object that contains our "business" logic.
          attrs.businessObject = bpmnFactory.create(bpmnType, objectData);
        }

        // Find the container.
        container = root;
        let cid = source.parent.id;
        if (containers[cid] !== undefined) {
          container = containers[cid];
        }
        modeling.createConnection(source, target, attrs, container);
      });
    });

    // Mark the model as 'initialized'.
    Drupal.bpmn_io_convert.initialized = true;

    await Drupal.bpmn_io.autoLayout(drupalSettings.bpmn_io_convert.annotations);
    // Hide the overlay.
    $('#bpmn-io .convert-overlay').addClass('completed');
    if (drupalSettings.bpmn_io_convert.reload) {
      // Save the model.
      await Drupal.bpmn_io.export();
      // Redirect to the actual edit-form.
      setTimeout(() => {
        window.location = drupalSettings.bpmn_io_convert.metadata.redirect_url;
      }, 1000);
    }

    // Set element colors.
    Object.keys(drupalSettings.bpmn_io_convert.colors).forEach((id) => {
      let element = elementRegistry.get(id);
      if (element !== undefined) {
        modeling.setColor(element, drupalSettings.bpmn_io_convert.colors[id]);
      }
    });
  };

  /**
   * Create an ExtensionElements-object.
   *
   * @param data
   *   The data to convert.
   *
   * @returns {bpmn:ExtensionElements}|null
   *   Returns the object.
   */
  Drupal.behaviors.bpmn_io_convert.createExtensionElements = function (data) {
    if (!data.plugin) {
      return null;
    }

    let moddle = Drupal.bpmn_io.modeler.get('moddle');
    let extEl = moddle.create('bpmn:ExtensionElements');

    let property = moddle.create('camunda:Property');
    property.name = 'pluginid';
    property.value = data.plugin;
    let properties = moddle.create('camunda:Properties');
    properties.get('values').push(property);
    extEl.get('values').push(properties);

    if ('configuration' in data) {
      Object.keys(data.configuration).forEach((key) => {
        let field = moddle.create('camunda:Field');
        field.name = key;
        field.string = data.configuration[key];
        extEl.get('values').push(field);
      });
    }

    return extEl;
  }

})(jQuery, Drupal);
