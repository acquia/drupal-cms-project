<?php

namespace Drupal\bpmn_io\Service;

use Drupal\modeler_api\Api;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentColor;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Mtownsend\XmlToArray\XmlToArray;

/**
 * The parser service for BPMN XML data.
 */
class Parser {

  public const int GATEWAY_TYPE_EXCLUSIVE = 0;
  public const int GATEWAY_TYPE_PARALLEL = 1;
  public const int GATEWAY_TYPE_INCLUSIVE = 2;
  public const int GATEWAY_TYPE_COMPLEX = 3;
  public const int GATEWAY_TYPE_EVENT_BASED = 4;

  /**
   * The model owner.
   *
   * @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface
   */
  protected ModelOwnerInterface $owner;

  /**
   * The raw XML string data.
   *
   * @var string
   */
  protected string $rawData;

  /**
   * The XML model.
   *
   * @var array
   */
  protected array $xmlModel;

  /**
   * The XML DOM.
   *
   * @var \DOMDocument
   */
  protected \DOMDocument $doc;

  /**
   * The XML DOM XPath.
   *
   * @var \DOMXPath
   */
  protected \DOMXPath $xpath;

  /**
   * The list of components.
   *
   * @var \Drupal\modeler_api\Component[]
   */
  protected array $components;

  /**
   * List of flows.
   *
   * @var array
   */
  protected array $flows;

  /**
   * List of successors.
   *
   * @var array
   */
  protected array $successors;

  /**
   * List of component colors.
   *
   * @var array
   */
  protected array $colors;


  /**
   * The IDX extension string.
   *
   * @var string
   */
  protected string $idxExtension;

  /**
   * Constructs the parser object.
   */
  public function __construct(
    protected PrepareComponents $prepareComponents,
  ) {}

  /**
   * Get all components.
   *
   * @return \Drupal\modeler_api\Component[]
   *   All components.
   */
  public function getComponents(): array {
    return $this->components;
  }

  /**
   * Gets the ID of the model.
   *
   * @return string
   *   The ID.
   */
  public function getId(): string {
    return $this->xmlModel[$this->xmlNsPrefix() . 'process'][0]['@attributes']['id'];
  }

  /**
   * Gets the label of the model.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return $this->xmlModel[$this->xmlNsPrefix() . 'process'][0]['@attributes']['name'] ?? 'noname';
  }

  /**
   * Gets the tags of the model.
   *
   * @return array
   *   The tags.
   */
  public function getTags(): array {
    $process = $this->xmlNsPrefix() . 'process';
    $extensions = $this->xmlNsPrefix() . 'extensionElements';
    $tagsString = isset($this->xmlModel[$process][0][$extensions]) ?
      $this->findProperty($this->xmlModel[$process][0][$extensions], 'Tags') :
      '';
    if ($tagsString === '') {
      return [];
    }
    $tags = explode(',', $tagsString);
    array_walk($tags, static function (&$item) {
      $item = trim((string) $item);
    });
    return $tags;
  }

  /**
   * Gets the changelog of the model.
   *
   * @return string
   *   The changelog.
   */
  public function getChangelog(): string {
    $process = $this->xmlNsPrefix() . 'process';
    $extensions = $this->xmlNsPrefix() . 'extensionElements';
    return $this->findProperty($this->xmlModel[$process][0][$extensions] ?? [], 'Changelog');
  }

  /**
   * Gets the template setting of the model.
   *
   * @return bool
   *   The template setting.
   */
  public function getTemplate(): bool {
    $process = $this->xmlNsPrefix() . 'process';
    $extensions = $this->xmlNsPrefix() . 'extensionElements';
    return mb_strtolower($this->findProperty($this->xmlModel[$process][0][$extensions] ?? [], 'Template')) === 'true';
  }

  /**
   * Gets the storage setting of the model.
   *
   * @return string
   *   The storage setting.
   */
  public function getStorage(): string {
    $process = $this->xmlNsPrefix() . 'process';
    $extensions = $this->xmlNsPrefix() . 'extensionElements';
    return $this->findProperty($this->xmlModel[$process][0][$extensions] ?? [], 'Storage');
  }

  /**
   * Gets the documentation of the model.
   *
   * @return string
   *   The documentation.
   */
  public function getDocumentation(): string {
    $documentation = $this->xmlModel[$this->xmlNsPrefix() . 'process'][0][$this->xmlNsPrefix() . 'documentation'] ?? '';
    if (empty($documentation)) {
      $documentation = '';
    }
    return $documentation;
  }

  /**
   * Gets the status of the model.
   *
   * @return bool
   *   The status.
   */
  public function getStatus(): bool {
    return mb_strtolower($this->xmlModel[$this->xmlNsPrefix() . 'process'][0]['@attributes']['isExecutable'] ?? 'true') === 'true';
  }

  /**
   * Gets the version of the model.
   *
   * @return string
   *   The version.
   */
  public function getVersion(): string {
    return $this->xmlModel[$this->xmlNsPrefix() . 'process'][0]['@attributes']['versionTag'] ?? '';
  }

  /**
   * Get the raw data.
   *
   * @return string
   *   The raw data.
   */
  public function getData(): string {
    return $this->rawData;
  }

  /**
   * Set and parse the data.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param string $data
   *   The raw data.
   */
  public function setData(ModelOwnerInterface $owner, string $data): void {
    $this->owner = $owner;
    $this->rawData = $data;
    $this->xmlModel = XmlToArray::convert($this->rawData);
    $this->doc = new \DOMDocument();
    $this->doc->loadXML($this->rawData);
    $this->xpath = new \DOMXPath($this->doc);

    $this->idxExtension = $this->xmlNsPrefix() . 'extensionElements';
    $this->components = [];
    $this->flows = [];
    $this->successors = [];

    $this->colors = [];
    if (isset($this->xmlModel['bpmndi:BPMNDiagram']['bpmndi:BPMNPlane']['bpmndi:BPMNShape'])) {
      foreach ($this->xmlModel['bpmndi:BPMNDiagram']['bpmndi:BPMNPlane']['bpmndi:BPMNShape'] as $item) {
        if (isset($item['@attributes']['fill']) && isset($item['@attributes']['stroke'])) {
          $this->colors[$item['@attributes']['bpmnElement']] = new ComponentColor(
            $item['@attributes']['fill'],
            $item['@attributes']['stroke'],
          );
        }
      }
    }

    if (!isset($this->xmlModel[$this->xmlNsPrefix() . 'process'][0])) {
      $this->xmlModel[$this->xmlNsPrefix() . 'process'] = [$this->xmlModel[$this->xmlNsPrefix() . 'process']];
    }
    foreach ($this->xmlModel[$this->xmlNsPrefix() . 'process'] as $process) {
      $this->getNestedComponents($owner, $process, $this->findAttribute($process, 'id'));
    }
    if ($element = $this->xmlModel[$this->xmlNsPrefix() . 'collaboration'] ?? NULL) {
      $this->getMetaData($owner, $element);
    }
  }

  /**
   * Get metadata like annotations for the given element.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param array $element
   *   The element.
   * @param string|null $parentId
   *   The parent ID, or NULL for the root element.
   */
  private function getMetaData(ModelOwnerInterface $owner, array $element, ?string $parentId = NULL): void {
    foreach ($this->getParticipants($element) as $participant) {
      $participantId = $this->findAttribute($participant, 'id');
      $this->components[] = new Component(
        $owner,
        $participantId,
        Api::COMPONENT_TYPE_SWIMLANE,
        '',
        $this->findAttribute($participant, 'name'),
        [],
        [],
        $this->findAttribute($participant, 'processRef'),
        $this->colors[$participantId] ?? NULL,
      );
    }

    $associations = [];
    foreach ($this->getAssociations($element) as $association) {
      $associationId = $this->findAttribute($association, 'id');
      $sourceId = $this->findAttribute($association, 'sourceRef');
      $targetId = $this->findAttribute($association, 'targetRef');
      if (isset($this->flows[$sourceId])) {
        $sourceId = implode(',', $this->flows[$sourceId]);
      }
      if (!isset($associations[$targetId])) {
        $associations[$targetId] = [];
      }
      $associations[$targetId][] = new ComponentSuccessor($associationId, $sourceId);
    }

    foreach ($this->getAnnotations($element) as $annotation) {
      $annotationId = $this->findAttribute($annotation, 'id');
      $this->components[] = new Component(
        $owner,
        $annotationId,
        Api::COMPONENT_TYPE_ANNOTATION,
        '',
        $annotation[$this->xmlNsPrefix() . 'text'] ?? '',
        [],
        $associations[$annotationId] ?? [],
        $parentId,
        $this->colors[$annotationId] ?? NULL,
      );
    }
  }

  /**
   * Recursive helper function to get components from element and children.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param array $element
   *   The element.
   * @param string|null $parentId
   *   The parent ID, or NULL for the root element.
   */
  private function getNestedComponents(ModelOwnerInterface $owner, array $element, ?string $parentId = NULL): void {
    foreach ($this->getSequenceFlows($element) as $sequenceFlow) {
      $flowId = $this->findAttribute($sequenceFlow, 'id');
      if (isset($sequenceFlow[$this->idxExtension])) {
        $pluginId = $this->findProperty($sequenceFlow[$this->idxExtension], 'pluginid');
        $condition = $flowId;
        if (!empty($pluginId) && !empty($condition)) {
          $this->components[] = new Component(
            $owner,
            $condition,
            Api::COMPONENT_TYPE_LINK,
            $pluginId,
            $this->findAttribute($sequenceFlow, 'name'),
            $this->findFields($sequenceFlow[$this->idxExtension], Api::COMPONENT_TYPE_LINK, $pluginId),
            [],
            $parentId,
            $this->colors[$flowId] ?? NULL,
          );
        }
        else {
          $condition = '';
        }
      }
      else {
        $condition = '';
      }
      $sourceId = $this->findAttribute($sequenceFlow, 'sourceRef');
      $targetId = $this->findAttribute($sequenceFlow, 'targetRef');
      $this->flows[$flowId] = [
        'source' => $sourceId,
        'target' => $targetId,
      ];
      $this->successors[$sourceId][] = new ComponentSuccessor($targetId, $condition);
    }

    $this->getMetaData($owner, $element, $parentId);

    foreach ($this->getGateways($element) as $gateway) {
      $gatewayId = $this->findAttribute($gateway, 'id');
      $this->components[] = new Component(
        $owner,
        $gatewayId,
        Api::COMPONENT_TYPE_GATEWAY,
        '',
        '',
        [],
        $this->successors[$gatewayId] ?? [],
        $parentId,
        $this->colors[$gatewayId] ?? NULL,
      );
    }

    foreach ($this->getStartEvents($element) as $startEvent) {
      $extension = $startEvent[$this->idxExtension] ?? [];
      $pluginId = $this->findProperty($extension, 'pluginid');
      if (empty($pluginId)) {
        continue;
      }
      $id = $this->findAttribute($startEvent, 'id');
      $this->components[] = new Component(
        $owner,
        $id,
        Api::COMPONENT_TYPE_START,
        $pluginId,
        $this->findAttribute($startEvent, 'name'),
        $this->findFields($extension, Api::COMPONENT_TYPE_START, $pluginId),
        $this->successors[$id] ?? [],
        $parentId,
        $this->colors[$id] ?? NULL,
      );
    }

    foreach ($this->getTasks($element) as $task) {
      $extension = $task[$this->idxExtension] ?? [];
      $pluginId = $this->findProperty($extension, 'pluginid');
      if (empty($pluginId)) {
        continue;
      }
      $id = $this->findAttribute($task, 'id');
      $this->components[] = new Component(
        $owner,
        $id,
        Api::COMPONENT_TYPE_ELEMENT,
        $pluginId,
        $this->findAttribute($task, 'name'),
        $this->findFields($extension, Api::COMPONENT_TYPE_ELEMENT, $pluginId),
        $this->successors[$id] ?? [],
        $parentId,
        $this->colors[$id] ?? NULL,
      );
    }

    foreach ($this->getSubprocesses($element) as $subprocess) {
      $id = $this->findAttribute($subprocess, 'id');
      $pluginId = str_replace('org.drupal.wrapper.', '', $this->findAttribute($subprocess, 'modelerTemplate'));
      $this->components[] = new Component(
        $owner,
        $id,
        Api::COMPONENT_TYPE_SUBPROCESS,
        $pluginId,
        $this->findAttribute($subprocess, 'name'),
        [],
        $this->successors[$id] ?? [],
        $parentId,
        $this->colors[$id] ?? NULL,
      );
      $this->getNestedComponents($owner, $subprocess, $id);
    }
  }

  /**
   * Gets the bpmn XML namespace prefix.
   *
   * @return string
   *   The prefix.
   */
  private function xmlNsPrefix(): string {
    return 'bpmn2:';
  }

  /**
   * Returns all the sequenceFlow objects (condition) from the XML model.
   *
   * @param array $element
   *   The element in which to collect.
   *
   * @return array
   *   The list of all sequence flows in the model data.
   */
  private function getSequenceFlows(array $element): array {
    $conditions = $element[$this->xmlNsPrefix() . 'sequenceFlow'] ?? [];
    if (isset($conditions['@attributes'])) {
      return [$conditions];
    }
    return $conditions;
  }

  /**
   * Returns all the annotations from the XML model.
   *
   * @param array $element
   *   The element in which to collect.
   *
   * @return array
   *   The list of all annotations in the model data.
   */
  private function getAnnotations(array $element): array {
    $annotations = $element[$this->xmlNsPrefix() . 'textAnnotation'] ?? [];
    if (isset($annotations['@attributes'])) {
      return [$annotations];
    }
    return $annotations;
  }

  /**
   * Returns all the participant objects from the XML model.
   *
   * @param array $element
   *   The element in which to collect.
   *
   * @return array
   *   The list of all participants in the model data.
   */
  private function getParticipants(array $element): array {
    $participants = $element[$this->xmlNsPrefix() . 'participant'] ?? [];
    if (isset($participants['@attributes'])) {
      return [$participants];
    }
    return $participants;
  }

  /**
   * Returns all the association objects from the XML model.
   *
   * @param array $element
   *   The element in which to collect.
   *
   * @return array
   *   The list of all associations in the model data.
   */
  private function getAssociations(array $element): array {
    $associations = $element[$this->xmlNsPrefix() . 'association'] ?? [];
    if (isset($associations['@attributes'])) {
      return [$associations];
    }
    return $associations;
  }

  /**
   * Returns all the startEvent (events) objects from the XML model.
   *
   * @param array $element
   *   The element in which to collect.
   *
   * @return array
   *   The list of all start events in the model data.
   */
  private function getStartEvents(array $element): array {
    $events = $element[$this->xmlNsPrefix() . 'startEvent'] ?? [];
    if (isset($events['@attributes'])) {
      return [$events];
    }
    return $events;
  }

  /**
   * Returns all the task objects (actions) from the XML model.
   *
   * @param array $element
   *   The element in which to collect.
   *
   * @return array
   *   The list of all tasks in the model data.
   */
  private function getTasks(array $element): array {
    $actions = $element[$this->xmlNsPrefix() . 'task'] ?? [];
    if (isset($actions['@attributes'])) {
      return [$actions];
    }
    return $actions;
  }

  /**
   * Returns all the gateway objects from the XML model.
   *
   * @param array $element
   *   The element in which to collect.
   *
   * @return array
   *   The list of all gateways in the model data.
   */
  private function getGateways(array $element): array {
    $types = [
      self::GATEWAY_TYPE_EXCLUSIVE => 'exclusiveGateway',
      self::GATEWAY_TYPE_PARALLEL => 'parallelGateway',
      self::GATEWAY_TYPE_INCLUSIVE => 'inclusiveGateway',
      self::GATEWAY_TYPE_COMPLEX => 'complexGateway',
      self::GATEWAY_TYPE_EVENT_BASED => 'eventBasedGateway',
    ];
    $gateways = [];
    foreach ($types as $key => $type) {
      $objects = $element[$this->xmlNsPrefix() . $type] ?? [];
      if (isset($objects['@attributes'])) {
        $objects = [$objects];
      }
      foreach ($objects as $object) {
        $object['type'] = $key;
        $gateways[] = $object;
      }
    }
    return $gateways;
  }

  /**
   * Returns all the subprocess objects from the XML model.
   *
   * @param array $element
   *   The element in which to collect.
   *
   * @return array
   *   The list of all subprocesses in the model data.
   */
  private function getSubprocesses(array $element): array {
    $subprocesses = $element[$this->xmlNsPrefix() . 'subProcess'] ?? [];
    if (isset($subprocesses['@attributes'])) {
      return [$subprocesses];
    }
    return $subprocesses;
  }

  /**
   * Return a property of a given BPMN element.
   *
   * @param array $element
   *   The BPMN element from which the property should be returned.
   * @param string $property_name
   *   The name of the property in the BPMN element.
   *
   * @return string
   *   The property's value, default to an empty string.
   */
  private function findProperty(array $element, string $property_name): string {
    if (isset($element['camunda:properties']['camunda:property'])) {
      $elements = isset($element['camunda:properties']['camunda:property']['@attributes']) ?
        [$element['camunda:properties']['camunda:property']] :
        $element['camunda:properties']['camunda:property'];
      foreach ($elements as $child) {
        if (isset($child['@attributes']['name']) && $child['@attributes']['name'] === $property_name) {
          return $child['@attributes']['value'];
        }
      }
    }
    return '';
  }

  /**
   * Return an attribute of a given BPMN element.
   *
   * @param array $element
   *   The BPMN element from which the attribute should be returned.
   * @param string $attribute_name
   *   The name of the attribute in the BPMN element.
   *
   * @return string
   *   The attribute's value, default to an empty string.
   */
  private function findAttribute(array $element, string $attribute_name): string {
    return $element['@attributes'][$attribute_name] ?? '';
  }

  /**
   * Return all the field values of a given BPMN element.
   *
   * @param array $element
   *   The BPMN element from which the field values should be returned.
   * @param int|null $type
   *   The type of the BPMN element.
   * @param string|null $pluginId
   *   The model owner plugin ID.
   *
   * @return array
   *   An array containing all the field values, keyed by the field name.
   */
  private function findFields(array $element, ?int $type = NULL, ?string $pluginId = NULL): array {
    $fields = [];
    if (isset($element['camunda:field'])) {
      $elements = isset($element['camunda:field']['@attributes']) ? [$element['camunda:field']] : $element['camunda:field'];
      foreach ($elements as $child) {
        $fields[$child['@attributes']['name']] = isset($child['camunda:string']) && is_string($child['camunda:string']) ? $child['camunda:string'] : '';
      }
    }
    if ($type !== NULL && $pluginId !== NULL) {
      $this->prepareComponents->upcastConfiguration($fields, $this->owner->ownerComponentDefaultConfig($type, $pluginId));
    }
    return $fields;
  }

  /**
   * Updates all components in raw data.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param array $templates
   *   All available templates received from the model owner.
   *
   * @return bool
   *   TRUE, if the model got changed, FALSE otherwise.
   *
   * @see \Drupal\modeler_api\Plugin\ModelerInterface::updateComponents()
   */
  public function updateComponents(ModelOwnerInterface $owner, array $templates): bool {
    $changed = FALSE;
    $this->idxExtension = $this->xmlNsPrefix() . 'extensionElements';
    foreach ($this->xmlModel[$this->xmlNsPrefix() . 'process'] as $process) {
      foreach ($templates as $template) {
        foreach ($template['appliesTo'] as $type) {
          $this->updateRecursiveComponents($type, $process, $template, $changed);
        }
      }
    }
    if ($changed) {
      $this->setData($owner, $this->doc->saveXML());
    }
    return $changed;
  }

  /**
   * Recursive helper function to update BPMN elements.
   *
   * @param string $type
   *   The BPMN type.
   * @param array $parent
   *   The BPMN element.
   * @param array $template
   *   The model owner template.
   * @param bool $changed
   *   The status, if anything has changed.
   */
  private function updateRecursiveComponents(string $type, array $parent, array $template, bool &$changed): void {
    $objects = match ($type) {
      'bpmn:StartEvent' => $this->getStartEvents($parent),
      'bpmn:SequenceFlow' => $this->getSequenceFlows($parent),
      'bpmn:Task' => $this->getTasks($parent),
      'bpmn:SubProcess' => $this->getSubprocesses($parent),
      default => [],
    };
    foreach ($objects as $object) {
      if (isset($object['@attributes']['modelerTemplate']) && $template['id'] === $object['@attributes']['modelerTemplate']) {
        $fields = $this->findFields($object[$this->idxExtension]);
        $id = $object['@attributes']['id'];
        /**
         * @var \DOMElement|null $element
         */
        $element = $this->xpath->query("//*[@id='$id']")->item(0);
        if ($element) {
          /**
           * @var \DOMElement|null $extensions
           */
          $extensions = $this->xpath->query("//*[@id='$id']/$this->idxExtension")
            ->item(0);
          if (!$extensions) {
            $node = $this->doc->createElement($this->idxExtension);
            $extensions = $element->appendChild($node);
          }
          foreach ($template['properties'] as $property) {
            switch ($property['binding']['type']) {
              case 'camunda:property':
                if ($this->findProperty($object[$this->idxExtension], $property['binding']['name']) !== $property['value']) {
                  $element->setAttribute($property['binding']['name'], $property['value']);
                  $changed = TRUE;
                }
                break;

              case 'camunda:field':
                if (isset($fields[$property['binding']['name']])) {
                  // Field exists, remove it from the list.
                  unset($fields[$property['binding']['name']]);
                }
                else {
                  $fieldNode = $this->doc->createElement('camunda:field');
                  $fieldNode->setAttribute('name', $property['binding']['name']);
                  $valueNode = $this->doc->createElement('camunda:string');
                  $valueNode->textContent = $property['value'];
                  $fieldNode->appendChild($valueNode);
                  $extensions->appendChild($fieldNode);
                  $changed = TRUE;
                }
                break;
            }
          }
          // Remove remaining fields from the model.
          foreach ($fields as $name => $value) {
            /**
             * @var \DOMElement $fieldElement
             */
            if ($fieldElement = $this->xpath->query("//*[@id='$id']/$this->idxExtension/camunda:field[@name='$name']")
              ->item(0)) {
              $extensions->removeChild($fieldElement);
              $changed = TRUE;
            }
          }
        }
      }
      if ($type === 'bpmn:SubProcess') {
        $this->updateRecursiveComponents($type, $object, $template, $changed);
      }
    }
  }

  /**
   * Set the executable attribute to true.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   */
  public function enable(ModelOwnerInterface $owner): void {
    /** @var \DOMElement|null $element */
    $element = $this->xpath->query("//*[@id='{$this->getId()}']")->item(0);
    if ($element) {
      $element->setAttribute('isExecutable', 'true');
      $this->setData($owner, $this->doc->saveXML());
    }
  }

  /**
   * Set the executable attribute to false.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   */
  public function disable(ModelOwnerInterface $owner): void {
    /** @var \DOMElement|null $element */
    $element = $this->xpath->query("//*[@id='{$this->getId()}']")->item(0);
    if ($element) {
      $element->setAttribute('isExecutable', 'false');
      $this->setData($owner, $this->doc->saveXML());
    }
  }

  /**
   * Prepare raw data for a cloned version of the model.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param string $id
   *   The new ID.
   * @param string $label
   *   The new label.
   */
  public function clone(ModelOwnerInterface $owner, string $id, string $label): void {
    /** @var \DOMElement|null $element */
    $element = $this->xpath->query("//*[@id='{$this->getId()}']")->item(0);
    if ($element) {
      $element->setAttribute('id', $id);
      $element->setAttribute('name', $label);
      $this->setData($owner, $this->doc->saveXML());
    }
  }

}
