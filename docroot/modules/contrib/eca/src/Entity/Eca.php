<?php

namespace Drupal\eca\Entity;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Entity\Objects\EcaAction;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Entity\Objects\EcaGateway;
use Drupal\eca\Entity\Objects\EcaObject;
use Drupal\eca\Plugin\PluginUsageInterface;
use Drupal\eca\ProcessDebugger;
use Drupal\modeler_api\Api;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines the ECA entity type.
 */
#[ConfigEntityType(
  id: 'eca',
  label: new TranslatableMarkup('ECA'),
  label_collection: new TranslatableMarkup('ECAs'),
  label_singular: new TranslatableMarkup('ECA'),
  label_plural: new TranslatableMarkup('ECAs'),
  config_prefix: 'eca',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'status' => 'status',
    'template' => 'template',
    'weight' => 'weight',
  ],
  handlers: [
    'storage' => 'Drupal\eca\Entity\EcaStorage',
  ],
  admin_permission: 'administer eca',
  label_count: [
    'singular' => '@count ECA',
    'plural' => '@count ECAs',
  ],
  config_export: [
    'id',
    'uuid',
    'status',
    'weight',
    'template',
    'events',
    'conditions',
    'gateways',
    'actions',
  ]
)]
class Eca extends ConfigEntityBase implements EntityWithPluginCollectionInterface {

  use EcaTrait;

  /**
   * ID of the ECA config entity.
   *
   * @var string
   */
  protected string $id;

  /**
   * List of events.
   *
   * @var array
   */
  protected array $events = [];

  /**
   * List of conditions.
   *
   * @var array
   */
  protected array $conditions = [];

  /**
   * List of gateways.
   *
   * @var array|null
   */
  protected ?array $gateways = [];

  /**
   * List of actions.
   *
   * @var array
   */
  protected array $actions = [];

  /**
   * Whether this instance s in testing mode.
   *
   * @var bool
   */
  protected static bool $isTesting = FALSE;

  /**
   * Set the instance into testing mode.
   *
   * This will prevent dependency calculation which would fail during test setup
   * if not all dependant config entities were available from the test module
   * itself.
   *
   * Problem is, that we can't add all the config dependencies to the test
   * modules, because that would fail if we enable the test modules in a real
   * Drupal instance, as some of those config entities already exist from
   * core modules.
   */
  public static function setTesting(): void {
    static::$isTesting = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities): void {
    parent::postLoad($storage, $entities);
    /** @var \Drupal\eca\Entity\Eca $entity */
    foreach ($entities as $entity) {
      if ($entity->get('weight') === NULL) {
        $entity->set('weight', 0);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->getThirdPartySetting('modeler_api', 'label', 'undefined');
  }

  /**
   * Determines if the current model is a template or not.
   *
   * @return bool
   *   TRUE, if the model is a template, FALSE otherwise.
   */
  public function isTemplate(): bool {
    return (bool) $this->get('template');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    foreach ([
      'events' => $this->eventPluginManager(),
      'conditions' => $this->conditionPluginManager(),
      'actions' => $this->actionPluginManager(),
    ] as $plugins => $manager) {
      foreach ($this->{$plugins} as $id => $pluginDef) {
        $plugin = $manager->createInstance($pluginDef['plugin'], $pluginDef['configuration']);
        // Allows ECA plugins to react upon being added to an ECA entity.
        if ($plugin instanceof PluginUsageInterface) {
          $plugin->pluginUsed($this, $id);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): static {
    // As ::trustData() states that dependencies are not calculated on save,
    // calculation is skipped when flagged as trusted.
    // @see Drupal\Core\Config\Entity\ConfigEntityInterface::trustData
    if (static::$isTesting || $this->hasTrustedData()) {
      return $this;
    }
    parent::calculateDependencies();
    foreach ($this->dependencyCalculation()->calculateDependencies($this) as $type => $names) {
      foreach ($names as $name) {
        $this->addDependency($type, $name);
      }
    }
    return $this;
  }

  /**
   * Builds the cache ID for an ID inside this ECA config entity.
   *
   * @param string $id
   *   An idea for which a cache ID inside this ECA config entity is needed.
   *
   * @return string
   *   The cache ID.
   */
  protected function buildCacheId(string $id): string {
    return "eca:$this->id:$id";
  }

  /**
   * Determines if ECA validation is being disabled for the current request.
   *
   * @return bool
   *   TRUE, if the current request has a query argument eca_validation set to
   *   off, FALSE otherwise.
   */
  protected function isValidationDisabled(): bool {
    $request = $this->request();
    // @noinspection StrContainsCanBeUsedInspection
    $isAjax = mb_strpos($request->query->get(MainContentViewSubscriber::WRAPPER_FORMAT, ''), 'drupal_ajax') !== FALSE;
    if ($isAjax && ($referer = $request->headers->get('referer')) && $query = parse_url($referer, PHP_URL_QUERY)) {
      // @noinspection StrContainsCanBeUsedInspection
      return mb_strpos($query, 'eca_validation=off') !== FALSE;
    }
    return $request->query->get('eca_validation', '') === 'off';
  }

  /**
   * {@inheritdoc}
   */
  public function resetComponents(): void {
    $this->events = [];
    $this->conditions = [];
    $this->actions = [];
    $this->gateways = [];
  }

  /**
   * {@inheritdoc}
   */
  public function addCondition(string $id, string $plugin_id, string $label, array $fields): bool {
    $this->conditions[$id] = [
      'plugin' => $plugin_id,
      'label' => $label,
      'configuration' => $fields,
    ];
    $original_id = $this->conditionPluginManager()->getDefinition($plugin_id)['original_id'] ?? NULL;
    if ($original_id !== NULL) {
      $this->conditions[$id]['original_id'] = $original_id;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function addGateway(string $id, int $type, array $successors, string $label = 'Gateway'): bool {
    $this->gateways[$id] = [
      'label' => $label,
      'type' => $type,
      'successors' => $successors,
    ];
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function addEvent(string $id, string $plugin_id, string $label, array $fields, array $successors, ?array $appliedTemplates = NULL): bool {
    $this->events[$id] = [
      'plugin' => $plugin_id,
      'label' => $label,
      'configuration' => $fields,
      'successors' => $successors,
    ];
    if ($appliedTemplates !== NULL) {
      $this->events[$id]['applied_templates'] = $appliedTemplates;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function addAction(string $id, string $plugin_id, string $label, array $fields, array $successors): bool {
    $this->actions[$id] = [
      'plugin' => $plugin_id,
      'label' => $label,
      'configuration' => $fields,
      'successors' => $successors,
    ];
    $original_id = $this->actionPluginManager()->getDefinition($plugin_id)['original_id'] ?? NULL;
    if ($original_id !== NULL) {
      $this->actions[$id]['original_id'] = $original_id;
    }
    return TRUE;
  }

  /**
   * Returns a list of info strings about included events in this ECA model.
   *
   * @return array
   *   A list of info strings about included events in this ECA model.
   */
  public function getEventInfos(): array {
    $events = [];
    foreach ($this->getUsedEvents() as $used_event) {
      $events[] = $this->getEventInfo($used_event);
    }
    return $events;
  }

  /**
   * Returns an info string about the ECA event.
   *
   * @return string
   *   The info string.
   */
  public function getEventInfo(EcaEvent $ecaEvent): string {
    $plugin = $ecaEvent->getPlugin();
    $event_info = $plugin->getPluginDefinition()['label'];
    // If available, additionally display the first config value of the event.
    if ($event_config = $ecaEvent->getConfiguration()) {
      $first_key = key($event_config);
      $first_value = current($event_config);
      $form = $plugin->buildConfigurationForm([], new FormState());
      if (isset($form[$first_key]['#options'][$first_value])) {
        $first_value = $form[$first_key]['#options'][$first_value];
      }
      $event_info .= ' (' . $first_value . ')';
    }
    return $event_info;
  }

  /**
   * Provides a list of all used events by this ECA config entity.
   *
   * @param array|null $ids
   *   (optional) When set, only the subset of given event object IDs are being
   *   returned.
   *
   * @return \Drupal\eca\Entity\Objects\EcaEvent[]
   *   The list of used events.
   */
  public function getUsedEvents(?array $ids = NULL): array {
    $events = [];
    $ids = $ids ?? array_keys($this->events);
    foreach ($ids as $id) {
      if (!isset($this->events[$id])) {
        continue;
      }
      $def = &$this->events[$id];
      /** @var \Drupal\eca\Entity\Objects\EcaEvent|null $event */
      $event = $this->getEcaObject('event', $def['plugin'], $id, $def['label'] ?? 'noname', $def['configuration'] ?? [], $def['successors'] ?? []);
      if ($event) {
        $events[$id] = $event;
      }
      unset($def);
    }
    return $events;
  }

  /**
   * Get a single ECA event object.
   *
   * @param string $id
   *   The ID of the event object within this ECA configuration.
   *
   * @return \Drupal\eca\Entity\Objects\EcaEvent|null
   *   The ECA event object, or NULL if not found.
   */
  public function getEcaEvent(string $id): ?EcaEvent {
    return current($this->getUsedEvents([$id])) ?: NULL;
  }

  /**
   * Get the used conditions.
   *
   * @return array
   *   List of used conditions.
   */
  public function getConditions(): array {
    return $this->conditions;
  }

  /**
   * Get the used actions.
   *
   * @return array
   *   List of used action.
   */
  public function getActions(): array {
    return $this->actions;
  }

  /**
   * Returns a list of all elements belonging to an event in the ECA model.
   *
   * @param string $eventId
   *   The ID of the event for which the elements are requested.
   *
   * @return array
   *   A key-value list of all elements belonging to an event in the ECA model,
   *   where the element IDs are the keys and the element types are the values.
   */
  public function getAllEventElements(string $eventId): array {
    $elements = [
      $eventId => 'event',
    ];
    $this->addSuccessors($elements, $this->events[$eventId]['successors'] ?? []);
    return $elements;
  }

  /**
   * Adds all successors to the element list recursively.
   *
   * @param array $elements
   *   The list of elements to which successors are added.
   * @param array $successors
   *   The list of successors to add.
   */
  private function addSuccessors(array &$elements, array $successors): void {
    foreach ($successors as $successor) {
      if (!isset($elements[$successor['id']])) {
        if (isset($this->actions[$successor['id']])) {
          $type = 'action';
        }
        elseif (isset($this->gateways[$successor['id']])) {
          $type = 'gateway';
        }
        else {
          // This is an error and shouldn't ever happen.
          continue;
        }
        $elements[$successor['id']] = $type;
        $this->addSuccessors($elements, $this->{$type . 's'}[$successor['id']]['successors'] ?? []);
      }
      if ($successor['condition'] && !isset($successors[$successor['condition']])) {
        if (isset($this->conditions[$successor['condition']])) {
          $elements[$successor['condition']] = 'condition';
        }
      }
    }
  }

  /**
   * Provides a list of valid successors to any ECA item in a given context.
   *
   * @param \Drupal\eca\ProcessDebugger $debugger
   *   The current process debugger.
   * @param \Drupal\eca\Entity\Objects\EcaObject $eca_object
   *   The ECA item, for which the successors are requested.
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The originally triggered event in which context to determine the list
   *   of valid successors.
   * @param array $context
   *   A list of tokens from the current context to be used for meaningful
   *   log messages.
   *
   * @return \Drupal\eca\Entity\Objects\EcaObject[]
   *   The list of valid successors.
   */
  public function getSuccessors(ProcessDebugger $debugger, EcaObject $eca_object, Event $event, array $context): array {
    $successors = [];
    foreach ($eca_object->getSuccessors() as $successor) {
      $context['%successorid'] = $successor['id'];
      if ($action = $this->actions[$successor['id']] ?? FALSE) {
        $context['%successorlabel'] = $action['label'] ?? 'noname';
        $this->logger()->debug('Check action successor %successorlabel (%successorid) from ECA %ecalabel (%ecaid) for event %event.', $context);
        if ($successorObject = $this->getEcaObject('action', $action['plugin'], $successor['id'], $action['label'] ?? 'noname', $action['configuration'] ?? [], $action['successors'] ?? [], $eca_object->getEvent())) {
          if ($this->conditionServices()->assertCondition($event, $successor['condition'], $this->conditions[$successor['condition']] ?? NULL, $context)) {
            $debugger->addSuccessor($eca_object->getId(), $successorObject->getId(), $successor['condition'], Api::COMPONENT_TYPE_ELEMENT);
            $successors[] = $successorObject;
          }
          else {
            $debugger->ignoreSuccessor($eca_object->getId(), $successorObject->getId(), $successor['condition'], Api::COMPONENT_TYPE_ELEMENT);
          }
        }
        else {
          $this->logger()->error('Invalid action successor %successorlabel (%successorid) from ECA %ecalabel (%ecaid) for event %event.', $context);
        }
      }
      elseif ($gateway = $this->gateways[$successor['id']] ?? FALSE) {
        $context['%successorlabel'] = $gateway['label'] ?? 'noname';
        $this->logger()->debug('Check gateway successor %successorlabel (%successorid) from ECA %ecalabel (%ecaid) for event %event.', $context);
        $successorObject = new EcaGateway($this, $successor['id'], $gateway['label'] ?? 'noname', $eca_object->getEvent(), $gateway['type']);
        $successorObject->setSuccessors($gateway['successors']);
        if ($this->conditionServices()->assertCondition($event, $successor['condition'], $this->conditions[$successor['condition']] ?? NULL, $context)) {
          $debugger->addSuccessor($eca_object->getId(), $successorObject->getId(), $successor['condition'], Api::COMPONENT_TYPE_GATEWAY);
          $successors[] = $successorObject;
        }
        else {
          $debugger->ignoreSuccessor($eca_object->getId(), $successorObject->getId(), $successor['condition'], Api::COMPONENT_TYPE_GATEWAY);
        }
      }
      else {
        $this->logger()->error('Non existent successor (%successorid) from ECA %ecalabel (%ecaid) for event %event.', $context);
      }
    }
    return $successors;
  }

  /**
   * Provides an ECA item build from given properties.
   *
   * @param string $type
   *   The ECA object type. Can bei either "event" or "action".
   * @param string $plugin_id
   *   The plugin ID.
   * @param string $id
   *   The item ID given by the modeller.
   * @param string $label
   *   The label.
   * @param array $fields
   *   The configuration of the item.
   * @param array $successors
   *   The list of associated successors.
   * @param \Drupal\eca\Entity\Objects\EcaEvent|null $event
   *   The original ECA event object, if looking for an action, NULL otherwise.
   *
   * @return \Drupal\eca\Entity\Objects\EcaObject|null
   *   The ECA object if available, NULL otherwise.
   */
  private function getEcaObject(string $type, string $plugin_id, string $id, string $label, array $fields, array $successors, ?EcaEvent $event = NULL): ?EcaObject {
    $ecaObject = NULL;
    switch ($type) {
      case 'event':
        try {
          /**
           * @var \Drupal\eca\Plugin\ECA\Event\EventInterface $plugin
           */
          $plugin = $this->eventPluginManager()->createInstance($plugin_id, $fields);
        }
        catch (PluginException $e) {
          // This can be ignored.
        }
        if (isset($plugin)) {
          $ecaObject = new EcaEvent($this, $id, $label, $plugin);
        }
        break;

      case 'action':
        if ($event !== NULL) {
          try {
            /**
             * @var \Drupal\Core\Action\ActionInterface $plugin
             */
            $plugin = $this->actionPluginManager()->createInstance($plugin_id, $fields);
          }
          catch (PluginException $e) {
            // This can be ignored.
          }
          if (isset($plugin)) {
            $ecaObject = new EcaAction($this, $id, $label, $event, $plugin);
          }
        }
        break;

    }
    if ($ecaObject !== NULL) {
      foreach ($fields as $key => $value) {
        $ecaObject->setConfiguration($key, $value);
      }
      $ecaObject->setSuccessors($successors);
      return $ecaObject;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    $collections = [];
    if ($this->isValidationDisabled()) {
      return $collections;
    }
    if (!empty($this->events)) {
      foreach ($this->events as $id => $info) {
        if (empty($info['plugin'])) {
          continue;
        }
        $collections['events.' . $id] = new DefaultSingleLazyPluginCollection($this->eventPluginManager(), $info['plugin'], $info['configuration'] ?? []);
      }
    }
    if (!empty($this->conditions)) {
      foreach ($this->conditions as $id => $info) {
        if (empty($info['plugin'])) {
          continue;
        }
        $collections['conditions.' . $id] = new DefaultSingleLazyPluginCollection($this->conditionPluginManager(), $info['plugin'], $info['configuration'] ?? []);
      }
    }
    if (!empty($this->actions)) {
      foreach ($this->actions as $id => $info) {
        if (empty($info['plugin'])) {
          continue;
        }
        $collections['actions.' . $id] = new DefaultSingleLazyPluginCollection($this->actionPluginManager(), $info['plugin'], $info['configuration'] ?? []);
      }
    }
    return $collections;
  }

  /**
   * Adds a dependency that could only be calculated on runtime.
   *
   * After adding a dependency on runtime, this configuration should be saved.
   *
   * @param string $type
   *   Type of dependency being added: 'module', 'theme', 'config', 'content'.
   * @param string $name
   *   If $type is 'module' or 'theme', the name of the module or theme. If
   *   $type is 'config' or 'content', the result of
   *   EntityInterface::getConfigDependencyName().
   *
   * @see \Drupal\Core\Entity\EntityInterface::getConfigDependencyName()
   *
   * @return static
   *   The ECA config itself.
   */
  public function addRuntimeDependency(string $type, string $name): Eca {
    $this->addDependency($type, $name);
    return $this;
  }

}
