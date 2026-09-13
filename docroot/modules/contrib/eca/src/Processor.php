<?php

namespace Drupal\eca;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Entity\Objects\EcaObject;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\Plugin\CleanupInterface;
use Drupal\eca\Plugin\ObjectWithPluginInterface;
use Drupal\eca\PluginManager\Event as PluginManagerEvent;
use Drupal\eca\Token\Browser;
use Drupal\modeler_api\TemplateTokenResolver;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Executes enabled ECA config regards applying events.
 */
class Processor {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The manager for ECA event plugins.
   *
   * @var \Drupal\eca\PluginManager\Event
   */
  protected PluginManagerEvent $eventPluginManager;

  /**
   * A shortened list of ECA events where execution was applied.
   *
   * This list is only used as a temporary reminder for being able to recognize
   * a possible infinite recursion.
   *
   * @var array
   */
  protected array $executionHistory = [];

  /**
   * A parameterized threshold of the maximum allowed level of recursion.
   *
   * @var int
   */
  protected int $recursionThreshold;

  /**
   * The fallback for the maximum occurrences of one event on the stack.
   *
   * The authoritative value is the "eca.max_event_occurrences" container
   * parameter. This constant only serves code that constructs the processor
   * directly instead of getting it from the container.
   */
  public const int DEFAULT_MAX_EVENT_OCCURRENCES = 10;

  /**
   * How often one event object may appear on the execution stack.
   *
   * @var int
   */
  protected int $maxEventOccurrences;

  /**
   * A flag indicating whether an error was already logged regards recursion.
   *
   * The flag is used to prevent log flooding, as this may quickly happen when
   * infinite recursion would happen a lot. The site owner should see at least
   * one of such an error and may (hopefully) react accordingly.
   *
   * @var bool
   */
  protected bool $recursionErrorLogged = FALSE;

  /**
   * The Drupal state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The token browser.
   *
   * @var \Drupal\eca\Token\Browser
   */
  protected Browser $tokenBrowser;

  /**
   * The maximum number of applied events kept in memory.
   *
   * The list is only ever read once per HTTP response, to render the ECA
   * inspector widget. Capping it keeps the memory footprint constant in a
   * long-running single process, where no response is ever built and the list
   * would otherwise grow for the whole lifetime of the process.
   *
   * @see \Drupal\eca\Processor::$appliedEvents
   */
  public const int MAX_APPLIED_EVENTS = 100;

  /**
   * The list of applied events during the current request.
   *
   * Only events whose execution actually started are recorded, and only the
   * most recent ::MAX_APPLIED_EVENTS of them are kept.
   *
   * @var \Drupal\eca\ProcessDebugger[]
   */
  protected static array $appliedEvents = [];

  /**
   * The template token resolver.
   *
   * This is NULL while the Modeler API is not installed, which is the case for
   * a site that boots ECA 3 code before eca_update_8012() has installed it.
   *
   * @var \Drupal\modeler_api\TemplateTokenResolver|null
   *
   * @see \Drupal\eca\EcaServiceProvider::alter()
   */
  protected ?TemplateTokenResolver $templateTokenResolver;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Returns the list of applied events during the current request.
   *
   * @return \Drupal\eca\ProcessDebugger[]
   *   The list of applied events, capped at ::MAX_APPLIED_EVENTS entries.
   */
  public static function getAppliedEvents(): array {
    return self::$appliedEvents;
  }

  /**
   * Records a debugger of an event that started executing.
   *
   * Debuggers of events that never started are deliberately not recorded:
   * their history is unreachable, because it is only ever read through
   * ::getAppliedEvents(), whose sole consumer skips them. Keeping them would
   * be pure memory growth.
   *
   * @param \Drupal\eca\ProcessDebugger $debugger
   *   The debugger of the event that just started.
   *
   * @see \Drupal\eca_ui\EventSubscriber\EcaEventCollector::onResponse()
   */
  protected static function recordAppliedEvent(ProcessDebugger $debugger): void {
    self::$appliedEvents[] = $debugger;
    if (count(self::$appliedEvents) > self::MAX_APPLIED_EVENTS) {
      // Drop the oldest entries and keep the list sequentially indexed. This
      // also releases the recorded history of those entries.
      self::$appliedEvents = array_slice(self::$appliedEvents, -self::MAX_APPLIED_EVENTS);
    }
  }

  /**
   * Get the service instance of this class.
   *
   * @return \Drupal\eca\Processor
   *   The service instance.
   */
  public static function get(): Processor {
    return \Drupal::service('eca.processor');
  }

  /**
   * Processor constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\eca\PluginManager\Event $event_plugin_manager
   *   The manager for ECA event plugins.
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal state.
   * @param \Drupal\eca\Token\Browser $tokenBrowser
   *   The token browser.
   * @param \Drupal\modeler_api\TemplateTokenResolver|null $templateTokenResolver
   *   The template token resolver, or NULL if the Modeler API is not installed.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param int $recursion_threshold
   *   A parameterized threshold of the maximum allowed level of recursion.
   * @param int $max_event_occurrences
   *   How often a single event object may appear on the execution stack.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelInterface $logger, EventDispatcherInterface $event_dispatcher, PluginManagerEvent $event_plugin_manager, StateInterface $state, Browser $tokenBrowser, ?TemplateTokenResolver $templateTokenResolver, AccountProxyInterface $currentUser, int $recursion_threshold, int $max_event_occurrences = self::DEFAULT_MAX_EVENT_OCCURRENCES) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->eventPluginManager = $event_plugin_manager;
    $this->recursionThreshold = $recursion_threshold;
    $this->maxEventOccurrences = $max_event_occurrences;
    $this->state = $state;
    $this->tokenBrowser = $tokenBrowser;
    $this->templateTokenResolver = $templateTokenResolver;
    $this->currentUser = $currentUser;
    $this->tokenBrowser->checkTestingTimeout();
    ProcessDebugger::$debug = $this->state->get('_eca_internal_debug_mode', FALSE) ?? FALSE;
  }

  /**
   * Determines, if the current stack trace is within ECA processing an event.
   *
   * @return bool
   *   TRUE, if the current stack trace is within ECA processing an event, FALSE
   *   otherwise.
   */
  public function isEcaContext(): bool {
    return (bool) $this->executionHistory || $this->state->get('_eca_internal_test_context');
  }

  /**
   * Main method that executes ECA config regards applying events.
   *
   * @param object $event
   *   The event being triggered.
   * @param string $event_name
   *   The event name that was triggered.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \InvalidArgumentException
   *   When the given event is not of a documented object type.
   */
  public function execute(object $event, string $event_name): void {
    if (!($event instanceof Event)) {
      throw new \InvalidArgumentException(sprintf('Passed $event parameter is not of an expected event object type, %s given', get_class($event)));
    }

    $subscribed = current($this->state->get('eca.subscribed', [])[$event_name] ?? []);
    if (!$subscribed) {
      // This can happen if within a request ECA models get disabled.
      $this->logger->info('The ECA processor got invoked for executing, but no subscribed configuration was found.');
      return;
    }

    /** @var \Drupal\eca\Entity\EcaStorage $eca_storage */
    $eca_storage = $this->entityTypeManager->getStorage('eca');
    $context = ['%event' => $event_name];

    foreach ($subscribed as $eca_id => $wildcards) {
      $context['%ecaid'] = $eca_id;
      unset($context['%eventid'], $context['%eventlabel']);
      foreach ($wildcards as $eca_event_id => $wildcard) {
        $debugger = new ProcessDebugger($this->tokenBrowser, $eca_id, $eca_event_id);
        $context['%eventid'] = $eca_event_id;
        unset($context['%ecalabel'], $context['%eventlabel']);

        /** @var \Symfony\Contracts\EventDispatcher\Event $event */
        if (!$this->wildcardApplies($event, $event_name, $wildcard, $context)) {
          $debugger->doesNotApply();
          $this->logger->debug('Appliance check for event %event defined by ECA ID %ecaid resulted to not apply, successors will not be executed.', $context);
          continue;
        }

        $this->logger->debug('Begin applying process for event %event defined by ECA ID %ecaid.', $context);

        /** @var \Drupal\eca\Entity\Eca|null $eca */
        $eca = $eca_storage->load($eca_id);
        if (!$eca) {
          // If an ECA model got deleted, we may end up here and then ignore
          // this model as it not longer exists.
          $debugger->doesNotExist();
          continue;
        }
        $debugger->setEcaLabel($eca->label());
        $context['%ecalabel'] = $eca->label();

        if (!($ecaEvent = $eca->getEcaEvent($eca_event_id))) {
          $debugger->doesNotExist();
          $this->logger->error('Event object %eventid does not exist in configuration of ECA ID %ecaid.', $context);
          continue;
        }
        $debugger->setEventLabel($ecaEvent->getLabel());
        $context['%eventlabel'] = $ecaEvent->getLabel();

        if ($this->templateTokenResolver !== NULL && $this->currentUser->hasPermission('modeler api edit eca')) {
          $appliedTemplates = $eca->get('events')[$eca_event_id]['applied_templates'] ?? [];
          foreach ($appliedTemplates as $applied_template) {
            [$eventId, $ecaId, $target, $config] = json_decode($applied_template, TRUE);
            if (isset($config[$eventId])) {
              $hiddenConfig = $config[$eventId];
              unset($config[$eventId]);
            }
            else {
              $hiddenConfig = [];
            }
            $hiddenConfig['newModelId'] = $ecaId;
            $this->templateTokenResolver->addAppliedTemplate('eca', $eventId, $target, $hiddenConfig, $config ?? []);
          }
        }

        /** @var \Symfony\Contracts\EventDispatcher\Event $event */
        if (!$ecaEvent->execute($debugger, NULL, $event, $context)) {
          $debugger->doesNotExecute();
          $this->logger->debug('Event object execution returned false, successors will not be executed.', $context);
          continue;
        }

        // We need to check whether this is the root of all execution calls,
        // for being able to purge the whole execution history once it is not
        // needed anymore.
        $is_root_execution = empty($this->executionHistory);
        // Take a look for a repetitive execution order. If we find one,
        // we see it as the beginning of infinite recursion and stop. The
        // occurrence limit is checked alongside it, because the repetition
        // rule alone does not bound the stack: it only rejects a push whose
        // event already opens a repeating block, and an unbounded stack can go
        // on satisfying that forever.
        if (!$is_root_execution && ($this->recursionThresholdSurpassed($eca, $ecaEvent) || $this->eventOccurrenceLimitReached($eca, $ecaEvent))) {
          $debugger->recursionDetected();
          if (!$this->recursionErrorLogged) {
            $this->logger->error('Recursion within configured ECA events detected. Please adjust your ECA configuration so that it avoids infinite loops. Affected event: %eventlabel (%eventid) from ECA %ecalabel (%ecaid).', $context);
            $this->recursionErrorLogged = TRUE;
          }
          continue;
        }

        // Temporarily keep in mind on which ECA event object execution is
        // about to be applied. If that behavior starts to repeat, then halt
        // the execution pipeline to prevent infinite recursion.
        $this->executionHistory[] = $eca->id() . ':' . $ecaEvent->getId();

        $before_event = new BeforeInitialExecutionEvent($eca, $ecaEvent, $event, $event_name);
        // The BEFORE_INITIAL_EXECUTION dispatch belongs inside the try that
        // owns the cleanup. A listener throwing from it used to escape past
        // every unwind, leaving behind whatever earlier listeners had already
        // changed - an account switch that never switched back, and the
        // history entry pushed just above, which made every later chain in the
        // request look nested.
        //
        // The cost is that AFTER_INITIAL_EXECUTION now also fires for a chain
        // whose before handlers did not all run, so an after handler must
        // unwind only what its own before handler actually did. All four
        // in-tree subscribers guard on a prestate flag for that reason.
        //
        // @see \Drupal\eca\EventSubscriber\EcaExecutionTokenSubscriber
        // @see \Drupal\eca\EventSubscriber\EcaExecutionFormSubscriber
        // @see \Drupal\eca\EventSubscriber\EcaExecutionSwitchAccountSubscriber
        try {
          $this->eventDispatcher->dispatch($before_event, EcaEvents::BEFORE_INITIAL_EXECUTION);

          // Now that we have any required context, we may execute the logic.
          $debugger->started($event_name);
          self::recordAppliedEvent($debugger);
          $this->logger->info('Start %eventlabel (%eventid) from ECA %ecalabel (%ecaid) for event %event.', $context);
          $this->executeSuccessors($debugger, $eca, $ecaEvent, $event, $context);
        }
        catch (\Exception $ex) {
          throw $ex;
        }
        finally {
          // Tearing this chain down is still ECA processing this event, so the
          // history entry of the chain stays on the stack until the teardown
          // has finished. A subscriber of the event dispatched below may well
          // dispatch a Drupal event that ECA subscribes to, and the chain that
          // starts from there is nested inside this one - keeping the entry is
          // what lets that chain recognize itself as nested, both for the
          // recursion guard and for everything else keyed off the root
          // execution boundary. The unwind is guaranteed by its own finally
          // block, so a subscriber that throws cannot leave the entry behind.
          try {
            $pre_state = $before_event->getPrestate(NULL);
            $this->dispatchAfterInitialExecution(new AfterInitialExecutionEvent($eca, $ecaEvent, $event, $event_name, $pre_state));
          }
          finally {
            // At this point, no nested triggering of events happened or was
            // prevented by something else. Therefore remove the last added
            // item from the history stack as it's not needed anymore. A nested
            // chain pushes and pops its own entry, so the stack unwinds in
            // reverse order and this always removes the entry of this chain.
            array_pop($this->executionHistory);

            if ($is_root_execution) {
              // Forget what we've done here. We only take care for nested
              // triggering of events regarding possible infinite recursion.
              // By resetting the array, all root-level executions will not know
              // anything from each other.
              $this->executionHistory = [];

              // The token browser caches normalized token data so that the
              // many debug steps of one execution chain don't re-normalize the
              // same unchanged values. That cache is only useful for the chain
              // it was built for, and the browser is a shared service, so
              // anything left behind would be retained for the rest of the
              // process. Releasing it here bounds that memory. Correctness
              // does not depend on this: the validity hash is taken over the
              // data a token resolves to, so a stale entry cannot survive a
              // change in the first place.
              $this->tokenBrowser->resetProcessedValues();
            }
          }

          $debugger->storeHistoryByEvent();
          $this->logger->debug('Finished applying process for event %event defined by ECA ID %ecaid.', $context);
        }
      }
    }
  }

  /**
   * Dispatches the after-execution event so that every listener gets to run.
   *
   * This event is the only one ECA dispatches itself rather than handing to
   * the event dispatcher, and it is worth being precise about why. Its
   * listeners are cleanup handlers: EcaExecutionSwitchAccountSubscriber
   * unwinds the account switch from here, at priority -500, so it runs last.
   * A plain dispatch stops at the first listener that throws, which left that
   * switch in place for the rest of the process - and the contexts where that
   * hurts most, a Drush command, a queue worker, a persistent worker SAPI,
   * are exactly the ones where no request boundary comes along to bound it.
   *
   * The sibling problem on the before event was fixable by moving its dispatch
   * inside the try block that owns the cleanup. That is no help here: the
   * cleanup at stake is itself a listener of this event, so no try or finally
   * around the dispatch can reach it - an earlier listener that throws still
   * skips it.
   *
   * Every listener is therefore invoked in turn, throwables are collected
   * rather than allowed to abort the remaining ones, and the first of them is
   * re-thrown once all listeners have had their turn. Nothing is swallowed:
   * the caller sees exactly the throwable it would have seen before, and any
   * further ones are logged.
   *
   * Re-throwing the first one unchanged, rather than wrapping it to chain the
   * others onto it, is deliberate. PHP has no way to attach a previous
   * throwable to one that already exists, so chaining would mean replacing it
   * with a new instance and changing the class the caller catches - and doing
   * so only when more than one listener happened to throw, which is a worse
   * contract than either consistent option.
   *
   * The loop mirrors what the dispatcher does around each listener, which is
   * little: check propagation, then call with the event, the event name and
   * the dispatcher. ::getListeners() resolves lazy service listeners while it
   * sorts them, so what arrives here is ready to call.
   *
   * @param \Drupal\eca\Event\AfterInitialExecutionEvent $event
   *   The event to dispatch.
   *
   * @see \Symfony\Component\EventDispatcher\EventDispatcher::callListeners()
   * @see \Symfony\Component\EventDispatcher\EventDispatcher::sortListeners()
   * @see \Drupal\eca\EventSubscriber\EcaExecutionSwitchAccountSubscriber
   */
  private function dispatchAfterInitialExecution(AfterInitialExecutionEvent $event): void {
    $throwables = [];
    foreach ($this->eventDispatcher->getListeners(EcaEvents::AFTER_INITIAL_EXECUTION) as $listener) {
      // Isolation covers throwing listeners only. A listener that deliberately
      // stops propagation still skips everything below it, cleanup handlers
      // included, exactly as it would with a plain dispatch.
      if ($event->isPropagationStopped()) {
        break;
      }
      try {
        $listener($event, EcaEvents::AFTER_INITIAL_EXECUTION, $this->eventDispatcher);
      }
      catch (\Throwable $throwable) {
        $throwables[] = $throwable;
      }
    }

    if ($throwables === []) {
      return;
    }
    $first = array_shift($throwables);
    foreach ($throwables as $throwable) {
      $this->logger->error('A subscriber of the ECA after-execution event threw %type: %message in %file, line %line. Another subscriber of the same event threw first, and that throwable is the one being passed on to the caller.', [
        '%type' => get_class($throwable),
        '%message' => $throwable->getMessage(),
        '%file' => $throwable->getFile(),
        '%line' => $throwable->getLine(),
      ]);
    }
    throw $first;
  }

  /**
   * Whether the given event passes the appliance of the given wildcard.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The system event.
   * @param string $event_name
   *   The event name that was triggered.
   * @param string $wildcard
   *   The wildcard for checking appliance.
   * @param array $context
   *   List of key value pairs, used to generate meaningful log messages.
   *
   * @return bool
   *   Returns TRUE if the event passes, FALSE otherwise.
   */
  protected function wildcardApplies(Event $event, string $event_name, string $wildcard, array $context): bool {
    $event_plugin_id = $this->eventPluginManager->getPluginIdForSystemEvent($event_name);
    if (NULL === $event_plugin_id) {
      $this->logger->critical("Missing event plugin for system event %event", $context);
      return FALSE;
    }
    $event_plugin_class = $this->eventPluginManager->getDefinition($event_plugin_id)['class'];
    return call_user_func($event_plugin_class . '::appliesForWildcard', $event, $event_name, $wildcard);
  }

  /**
   * Executes the successors.
   *
   * @param \Drupal\eca\ProcessDebugger $debugger
   *   The current process debugger.
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param \Drupal\eca\Entity\Objects\EcaObject $eca_object
   *   The ECA item that was just executed and looks for its successors.
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The event that was originally triggered.
   * @param array $context
   *   List of key value pairs, used to generate meaningful log messages.
   */
  protected function executeSuccessors(ProcessDebugger $debugger, Eca $eca, EcaObject $eca_object, Event $event, array $context): void {
    $executedSuccessorIds = [];
    try {
      foreach ($eca->getSuccessors($debugger, $eca_object, $event, $context) as $successor) {
        $context['%actionlabel'] = $successor->getLabel();
        $context['%actionid'] = $successor->getId();
        if (in_array($successor->getId(), $executedSuccessorIds, TRUE)) {
          $this->logger->debug('Prevent duplicate execution of %actionlabel (%actionid) from ECA %ecalabel (%ecaid) for event %event.', $context);
          continue;
        }
        $this->logger->info('Execute %actionlabel (%actionid) from ECA %ecalabel (%ecaid) for event %event.', $context);
        if ($successor->execute($debugger, $eca_object, $event, $context)) {
          $executedSuccessorIds[] = $successor->getId();
          $this->executeSuccessors($debugger, $eca, $successor, $event, $context);
        }
      }
    }
    catch (\Exception $ex) {
      throw $ex;
    }
    finally {
      if ($eca_object instanceof ObjectWithPluginInterface) {
        $plugin = $eca_object->getPlugin();
        if ($plugin instanceof CleanupInterface) {
          $plugin->cleanupAfterSuccessors();
        }
      }
    }
  }

  /**
   * Checks the ECA event object whether it surpasses the recursion threshold.
   *
   * The execution history is a stack of "<eca id>:<event id>" entries, one for
   * every execution that is currently nested inside another one. Recursion
   * shows up in that stack as a block of entries that keeps repeating itself,
   * and such a block always begins with the event that is about to be executed
   * next. The threshold is surpassed as soon as that block is directly preceded
   * by more than $this->recursionThreshold identical blocks.
   *
   * Every occurrence of the given event within the stack opens a candidate
   * block, and all of them need to be examined. The most recent occurrence only
   * opens the shortest candidate block, which misses every repetition whose
   * block contains the given event more than once. A stack that grows by the
   * repeating block "A, A, B, B" for example opens the block "A, B, B" at its
   * most recent "A", and that misaligned block never repeats, which leaves the
   * recursion undetected at any threshold.
   *
   * The history is copied into a local, sequentially indexed list which is then
   * walked with integer offsets. Walking the property itself with end() and
   * prev() would make the surrounding repetition count and the block comparison
   * share one hidden array cursor, which is needlessly hard to reason about and
   * to maintain.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param \Drupal\eca\Entity\Objects\EcaEvent $ecaEvent
   *   The ECA event object to check for.
   *
   * @return bool
   *   Returns TRUE when recursion threshold was surpassed, FALSE otherwise.
   */
  protected function recursionThresholdSurpassed(Eca $eca, EcaEvent $ecaEvent): bool {
    $current = $eca->id() . ':' . $ecaEvent->getId();
    $history = array_values($this->executionHistory);
    $history_size = count($history);

    foreach (array_keys($history, $current, TRUE) as $block_start) {
      // The candidate block reaches from this execution of the given event up
      // to the end of the stack, and is about to repeat itself once more.
      $block_size = $history_size - $block_start;
      $repeated_block = array_slice($history, $block_start, $block_size);

      // Count how many identical blocks directly precede that block.
      $recursion_level = 1;
      $offset = $block_start;
      while ($recursion_level <= $this->recursionThreshold && $offset >= $block_size) {
        $offset -= $block_size;
        if (array_slice($history, $offset, $block_size) !== $repeated_block) {
          // The preceding block differs, so this candidate does not repeat.
          continue 2;
        }
        $recursion_level++;
      }

      if ($recursion_level > $this->recursionThreshold) {
        return TRUE;
      }
    }

    // No block of the history repeats often enough to count as recursion.
    return FALSE;
  }

  /**
   * Checks whether the event object already sits on the stack often enough.
   *
   * ::recursionThresholdSurpassed() looks for a block of the stack repeating
   * itself, and that rule does not bound the stack. It blocks pushing an event
   * only when the stack already ends in two identical blocks that begin with
   * that same event, and an infinite sequence can satisfy that condition
   * forever: squares are permitted as long as they do not start with the event
   * being pushed. Two mutually triggering models that also re-trigger
   * themselves are enough to grow the stack without limit.
   *
   * Counting occurrences closes that hole by construction. Once no event may
   * appear more than $this->maxEventOccurrences times, the stack cannot exceed
   * that number multiplied by the count of distinct enabled event objects, no
   * matter what shape the model graph has.
   *
   * This is deliberately a second, independent rule rather than a redefinition
   * of the repetition threshold. The two disagree on stacks such as
   * "A, X, A" pushing "A", which the repetition rule allows because the block
   * "A" is preceded by "X". Since "eca.max_recursion_level" defaults to 1,
   * folding the occurrence count into it would start halting an event nested
   * twice through varying paths on a default install, breaking models that do
   * "A -> X -> A -> X -> A" today. The separate threshold defaults high enough
   * to leave all of that alone while still bounding the stack.
   *
   * Where the rules do coincide they agree: for "A, A, A..." and
   * "A, B, A, B..." the occurrence count and the repetition count are the same
   * number.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param \Drupal\eca\Entity\Objects\EcaEvent $ecaEvent
   *   The ECA event object that is about to be executed.
   *
   * @return bool
   *   TRUE when the event may not be pushed again, FALSE otherwise.
   */
  protected function eventOccurrenceLimitReached(Eca $eca, EcaEvent $ecaEvent): bool {
    $current = $eca->id() . ':' . $ecaEvent->getId();
    $occurrences = 0;
    foreach ($this->executionHistory as $entry) {
      if ($entry === $current) {
        $occurrences++;
      }
    }
    // The event is about to be pushed, so reaching the limit already means the
    // push would exceed it. This caps the stack at exactly
    // $this->maxEventOccurrences entries per distinct event object.
    return $occurrences >= $this->maxEventOccurrences;
  }

}
