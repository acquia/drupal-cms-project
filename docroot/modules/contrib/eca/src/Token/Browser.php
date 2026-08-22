<?php

namespace Drupal\eca\Token;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Attribute\Token;
use Drupal\eca\EcaEvents;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\PluginManager\Event;
use Drupal\eca\ProcessDebugger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides services for normalizing and browsing tokens.
 */
final class Browser {

  /**
   * The private temp store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  private PrivateTempStore $privateTempStoreInstance;

  /**
   * The shared temp store.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  private SharedTempStore $sharedTempStoreInstance;

  /**
   * The main request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private Request $request;

  /**
   * The token info.
   *
   * @var array|null
   */
  private ?array $tokenInfo = NULL;

  /**
   * Prefix for compressed data to distinguish it from other strings.
   */
  private const string COMPRESS_PREFIX = "\x1f\x9d";

  /**
   * Making sure that we don't run this twice.
   *
   * @var bool
   */
  private bool $isRunning = FALSE;

  /**
   * The event classes indexed by event name.
   *
   * @var array
   */
  private array $eventClasses = [];

  /**
   * List of normalized values plus a hash of the original value.
   *
   * @var array
   */
  private array $processedValues = [];

  /**
   * Max depth for token data.
   *
   * @var int
   */
  private int $depth;

  /**
   * Max number of cases for history data.
   *
   * @var int
   */
  private int $cases;

  /**
   * Constructs the browser object.
   */
  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly TokenServices $token,
    private readonly Event $eventPluginManager,
    private readonly PrivateTempStoreFactory $privateTempStoreFactory,
    private readonly SharedTempStoreFactory $sharedTempStoreFactory,
    private readonly AccountProxyInterface $currentUser,
    private readonly RequestStack $requestStack,
    private readonly TimeInterface $time,
    private readonly StateInterface $state,
  ) {}

  /**
   * Returns the private temp store, lazily initialized.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   *   The private temp store.
   */
  private function privateTempStore(): PrivateTempStore {
    if (!isset($this->privateTempStoreInstance)) {
      $this->privateTempStoreInstance = $this->privateTempStoreFactory->get('eca_process_debugger');
      $this->privateTempStoreInstance->delete('testing::');
    }
    return $this->privateTempStoreInstance;
  }

  /**
   * Returns the shared temp store, lazily initialized.
   *
   * @return \Drupal\Core\TempStore\SharedTempStore
   *   The shared temp store.
   */
  private function sharedTempStore(): SharedTempStore {
    if (!isset($this->sharedTempStoreInstance)) {
      $this->sharedTempStoreInstance = $this->sharedTempStoreFactory->get('eca_process_debugger');
    }
    return $this->sharedTempStoreInstance;
  }

  /**
   * Returns the current request, lazily initialized.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The current request.
   */
  private function request(): Request {
    if (!isset($this->request)) {
      $this->request = $this->requestStack->getCurrentRequest();
    }
    return $this->request;
  }

  /**
   * Returns the max depth for token data, lazily initialized.
   *
   * @return int
   *   The max depth.
   */
  private function depth(): int {
    if (!isset($this->depth)) {
      $this->depth = $this->state->get('_eca_internal_debug_data_depth', 5) ?? 5;
    }
    return $this->depth;
  }

  /**
   * Returns the max number of cases for history data, lazily initialized.
   *
   * @return int
   *   The max number of cases.
   */
  private function cases(): int {
    if (!isset($this->cases)) {
      $this->cases = $this->state->get('_eca_internal_debug_data_cases', 10) ?? 10;
    }
    return $this->cases;
  }

  /**
   * Store history data for debugging purposes.
   *
   * @param array $history
   *   The history data.
   * @param string|null $event
   *   The event or NULL. If no event is given, the data gets hashed
   *   and stored privately for the current user. If the event is
   *   provided, the data is stored together with request data in the shared
   *   temp store.
   *
   * @return string|null
   *   The hash or NULL.
   */
  public function storeHistory(array $history, ?string $event = NULL): ?string {
    // Collapse content-identical sub-trees across steps and nested levels into
    // compact '@same' markers before the data is compressed and persisted.
    // This runs at storage time — the same conceptual point as compression —
    // so the markers survive into the temp store and across the API to the
    // Workflow Modeler, which expands them lazily at render time. The pass is
    // marker-aware: it leaves existing '@prev'/'@ref'/'@same' markers intact.
    $history = ProcessDebugger::deduplicateHistory($history);
    if ($event === NULL) {
      $compressed = $this->compress($history);
      $hash = md5($compressed);
      try {
        $this->privateTempStore()->set($hash, $compressed);
      }
      catch (\Exception) {
        // Silently fail if temp store is not available.
        // This prevents breaking the debugger if temp store has issues.
      }
      return $hash;
    }
    $testingKey = 'testing::' . $event;
    $testingData = $this->sharedTempStore()->get($testingKey);
    if (is_string($testingData) && !str_starts_with($testingData, self::COMPRESS_PREFIX)) {
      try {
        $this->sharedTempStore()->set($testingKey, $this->compress($history));
      }
      catch (\Exception) {
        // Silently fail if temp store is not available.
      }
    }
    $history += [
      'timestamp' => $this->time->getRequestTime(),
      'user' => [
        'uid' => $this->currentUser->id(),
        'name' => $this->currentUser->getAccountName(),
      ],
      'ip' => $this->request()->getClientIp(),
      'url' => $this->request()->getRequestUri(),
    ];
    $data = $this->getRawHistoryByEvent($event);
    $data[] = $history;
    // Only store a number latest cases of that event.
    if (count($data) > $this->cases()) {
      $data = array_slice($data, -1 * $this->cases());
    }
    try {
      $this->sharedTempStore()->set($event, $this->compress($data));
    }
    catch (\Exception) {
      // Silently fail if temp store is not available.
      // This prevents breaking the debugger if temp store has issues.
    }
    return NULL;
  }

  /**
   * Get the history data by hash.
   *
   * @param string $hash
   *   The hash.
   *
   * @return array
   *   The history data.
   */
  public function getHistoryByHash(string $hash): array {
    // Return the raw, compact data with deduplication markers (@ref / @prev)
    // intact. The Workflow Modeler frontend expands these markers lazily, so
    // expanding them here would re-inflate the payload to its full size.
    return $this->decompress($this->privateTempStore()->get($hash));
  }

  /**
   * Get the history data by event.
   *
   * @param string $event
   *   The event.
   *
   * @return array
   *   The history data.
   */
  public function getHistoryByEvent(string $event): array {
    // Return the raw, compact data with deduplication markers (@ref / @prev)
    // intact. The Workflow Modeler frontend expands these markers lazily, so
    // expanding them here would re-inflate the payload to its full size.
    return $this->getRawHistoryByEvent($event);
  }

  /**
   * Get the raw history data by event without expanding deduplication markers.
   *
   * Used internally when appending new entries to avoid unnecessary
   * expansion of data that will be re-compressed and stored again.
   *
   * @param string $event
   *   The event.
   *
   * @return array
   *   The raw history data.
   */
  private function getRawHistoryByEvent(string $event): array {
    return $this->decompress($this->sharedTempStore()->get($event));
  }

  /**
   * Initializes the testing mode for an event.
   *
   * @param string $event
   *   The event.
   *
   * @return string
   *   The job ID.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function initTesting(string $event): string {
    $jobId = (new Random())->string();
    $this->sharedTempStore()->set('testing::' . $event, $jobId);
    $this->sharedTempStore()->set('jobid::testing::' . $jobId, $event);
    if (!($this->state->get('_eca_internal_debug_mode', FALSE) ?? FALSE)) {
      $this->state->set('_eca_internal_debug_mode', TRUE);
      $this->state->set('_eca_internal_debug_test_started', $this->time->getRequestTime());
      $this->sharedTempStore()->set('jobid::reset_debug::' . $jobId, TRUE);
    }
    return $jobId;
  }

  /**
   * Cancels a running test and cleans up associated resources.
   *
   * @param string $jobId
   *   The job ID.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function cancelTesting(string $jobId): void {
    $event = $this->sharedTempStore()->get('jobid::testing::' . $jobId);
    if ($event) {
      $this->sharedTempStore()->delete('testing::' . $event);
      $this->sharedTempStore()->delete('jobid::testing::' . $jobId);
    }
    if ($this->sharedTempStore()->get('jobid::reset_debug::' . $jobId)) {
      $this->state->set('_eca_internal_debug_mode', FALSE);
      $this->state->delete('_eca_internal_debug_test_started');
      $this->sharedTempStore()->delete('jobid::reset_debug::' . $jobId);
    }
  }

  /**
   * Polls the testing mode for the history data.
   *
   * @param string $jobId
   *   The job ID.
   *
   * @return array|null
   *   The history data, if the test has been completed. NULL otherwise.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function pollTesting(string $jobId): ?array {
    $event = $this->sharedTempStore()->get('jobid::testing::' . $jobId);
    $raw = $this->sharedTempStore()->get('testing::' . $event);
    // Distinguish between the jobId string (test pending) and compressed
    // history data (test completed) by checking for the compression prefix.
    if (is_string($raw) && $raw !== $jobId && str_starts_with($raw, self::COMPRESS_PREFIX)) {
      $data = $this->decompress($raw);
      $this->sharedTempStore()->delete('testing::' . $event);
      $this->sharedTempStore()->delete('jobid::testing::' . $jobId);
      if ($this->sharedTempStore()->get('jobid::reset_debug::' . $jobId)) {
        $this->state->set('_eca_internal_debug_mode', FALSE);
        $this->state->delete('_eca_internal_debug_test_started');
        $this->sharedTempStore()->delete('jobid::reset_debug::' . $jobId);
      }
      // Return the raw, compact data with deduplication markers (@ref / @prev)
      // intact. The Workflow Modeler frontend expands these markers lazily, so
      // expanding them here would re-inflate the payload to its full size.
      return $data['history'] ?? [];
    }
    return NULL;
  }

  /**
   * Checks if test-triggered debug mode has expired and disables it.
   *
   * When the test/run button auto-enables debug mode, a timestamp is stored.
   * This method checks whether the configured timeout has elapsed and, if so,
   * automatically disables debug mode to prevent lingering performance impact.
   */
  public function checkTestingTimeout(): void {
    $started = $this->state->get('_eca_internal_debug_test_started');
    if ($started !== NULL) {
      $timeout = $this->state->get('_eca_internal_debug_test_timeout', 300) ?? 300;
      if ($timeout > 0 && $this->time->getRequestTime() - $started >= $timeout) {
        $this->state->set('_eca_internal_debug_mode', FALSE);
        $this->state->delete('_eca_internal_debug_test_started');
      }
    }
  }

  /**
   * Normalizes all current token data.
   *
   * @param string $eventName
   *   The event name.
   *
   * @return array
   *   The normalized token data.
   */
  public function normalizedTokenData(string $eventName): array {
    if ($this->isRunning) {
      return [];
    }
    $this->isRunning = TRUE;
    if ($this->tokenInfo === NULL) {
      $this->tokenInfo = $this->token->getInfo();
    }
    $data = $this->token->getTokenData();
    if (!isset($this->eventClasses[$eventName])) {
      $eventPluginId = $this->eventPluginManager->getPluginIdForSystemEvent($eventName);
      try {
        $definition = $this->eventPluginManager->getDefinition($eventPluginId);
      }
      catch (PluginNotFoundException) {
        $definition = [
          'class' => $eventName,
          'event_class' => self::class,
        ];
      }
      $this->eventClasses[$eventName] = [
        'class' => $definition['class'],
        'eventClass' => $definition['event_class'],
      ];
    }
    /** @var \Drupal\eca\Attribute\Token $supportedToken */
    foreach ($this->getSupportedTokens($this->eventClasses[$eventName]['class'], $this->eventClasses[$eventName]['eventClass']) as $supportedToken) {
      // Make sure this is not overriding an existing token. Try to find an
      // alias, or ignore the token if none is found.
      $key = $supportedToken->name;
      if (isset($data[$key])) {
        $key = NULL;
        foreach ($supportedToken->aliases as $alias) {
          if (!isset($data[$alias])) {
            $key = $alias;
            break;
          }
        }
        if ($key === NULL) {
          continue;
        }
      }
      // Only add the token if it actually exists. An example of a declared but
      // often not existing token is the session_user.
      if ($this->token->hasTokenData($key)) {
        $data[$key] = $supportedToken;
      }
    }
    // Query dynamic data providers for their available keys. This covers
    // tokens that cannot be declared via #[Token] attributes because the
    // keys are determined at runtime (e.g. user-configured queue tokens).
    foreach ($this->token->getDataProviders() as $dataProvider) {
      if ($dataProvider instanceof DynamicDataProviderInterface) {
        foreach ($dataProvider->getAvailableKeys() as $key) {
          if (!isset($data[$key]) && $this->token->hasTokenData($key)) {
            $data[$key] = $this->token->getTokenData($key);
          }
        }
      }
    }
    $normalizedData = [];
    $entityIndex = [];
    foreach ($data as $key => $value) {
      // Suppress the spurious empty-string token key. It carries no usable
      // token name but serializes a full token data tree (a ~1.47MB user
      // snapshot in practice), so it is skipped entirely during
      // normalization.
      if ($key === '') {
        continue;
      }
      // Check for entity deduplication: when the same content entity
      // appears under multiple token names (e.g. 'entity' and
      // 'node'), only serialize it once and store a reference
      // for duplicates.
      //
      // Dedup is keyed on "entityType:UUID" rather than "entityType:id".
      // Content entities receive their UUID at object construction, before
      // they are saved, so the UUID is populated even for new/unsaved
      // entities (e.g. the anonymous user on the registration form, exposed
      // as both 'entity' and 'user'). Keying on the id would skip those,
      // because a new entity has no id yet. The UUID is also globally
      // unique, so it cannot mis-collapse two distinct new entities the way
      // an empty id ("user:") would.
      //
      // This dedup is intentionally per-step: $entityIndex is reset on every
      // call, so it only asserts identity within a single synchronous
      // snapshot of token data (same object, same instant), which UUID
      // equality guarantees. It must not be extended across steps.
      $entity = $this->resolveEntity($key, $value);
      if ($entity instanceof ContentEntityInterface) {
        $uuid = $entity->uuid();
        // Only dedup when a non-empty UUID is available. If it is missing
        // (extremely unusual for content entities), fall through to normal
        // normalization rather than keying on the id.
        if (is_string($uuid) && $uuid !== '') {
          $entityKey = $entity->getEntityTypeId() . ':' . $uuid;
          if (isset($entityIndex[$entityKey])) {
            $keyParts = explode(':', $key);
            $normalizedData[$key] = [
              'label' => ucwords(str_replace(['_', '-'], ' ', end($keyParts))),
              'token' => $key,
              ProcessDebugger::TOKEN_DATA_REF => $entityIndex[$entityKey],
            ];
            continue;
          }
          $entityIndex[$entityKey] = $key;
        }
      }
      try {
        $hash = md5(serialize($value));
      }
      catch (\Throwable) {
        // If serialization fails (e.g. closures or resources in the data),
        // use a unique hash so the value is always re-normalized.
        $hash = md5((new Random())->string(16));
      }
      if (!isset($this->processedValues[$key]) || $this->processedValues[$key]['hash'] !== $hash) {
        $this->processedValues[$key] = [
          'data' => $this->normalizeValue($key, $value),
          'hash' => $hash,
        ];
      }
      $normalizedData[$key] = $this->processedValues[$key]['data'];
    }
    uasort($normalizedData, static fn(array $a, array $b) => strnatcasecmp($a['label'], $b['label']));
    $this->isRunning = FALSE;
    return $normalizedData;
  }

  /**
   * Normalizes a single token value.
   *
   * @param string $token
   *   The token key.
   * @param mixed $value
   *   The token value.
   * @param int $depth
   *   The current recursion depth.
   * @param array $visited
   *   A per-path set of already expanded entities, keyed by
   *   "entityType:id". Used to stop self-referencing entity-reference
   *   chains from expanding the same entity twice along one path. This is
   *   intentionally passed by value so that sibling branches each get their
   *   own copy and a single entity may still appear under two distinct
   *   branches. It must not be confused with the global top-level
   *   deduplication in normalizedTokenData().
   *
   * @return array
   *   The normalized data for this value.
   */
  private function normalizeValue(string $token, mixed $value, int $depth = 1, array $visited = []): array {
    $keyParts = explode(':', $token);
    $key = end($keyParts);
    $normalized = [
      'label' => ucwords(str_replace(['_', '-'], ' ', $key)),
      'token' => $token,
    ];
    if ($value instanceof EntityAdapter) {
      $value = $value->getEntity();
    }
    elseif ($value instanceof DataTransferObject) {
      try {
        $properties = $value->getProperties(TRUE);
        if (!empty($properties)) {
          $value = $properties;
        }
      }
      catch (MissingDataException) {
        // Ignore for now.
      }
    }
    elseif ($value instanceof Token && $value->type !== '') {
      $type = $value->type;
      $value = $this->token->getTokenData($key);
      if ($value instanceof ContentEntityInterface) {
        $key = $value->getEntityTypeId();
      }
      else {
        $key = $type;
      }
    }

    if (isset($this->tokenInfo['types'][$key])) {
      $dataNeeded = $this->tokenInfo['types'][$key]['needs-data'] ?? NULL;
      if ($dataNeeded && isset($this->tokenInfo['tokens'][$dataNeeded])) {
        // When the token type resolves to a concrete content entity, honor
        // the per-path cycle guard and count this expansion towards the
        // depth budget so that the referenced entity's own fields cannot
        // re-open a fresh, unbounded expansion.
        if ($value instanceof ContentEntityInterface && !$this->canExpandEntity($value, $depth, $visited)) {
          return $normalized;
        }
        $childVisited = $this->markVisited($value, $visited);
        $normalized['data'] = $this->normalizeRecursive($token, $dataNeeded, [$key => $value], $depth, $childVisited);
      }
    }
    elseif (is_scalar($value)) {
      $normalized['value'] = $value;
    }
    elseif (is_array($value)) {
      if ($depth < $this->depth()) {
        $normalized['data'] = [];
        foreach ($value as $subKey => $subValue) {
          $normalized['data'][$subKey] = $this->normalizeValue($token . ':' . $subKey, $subValue, $depth + 1, $visited);
        }
      }
    }
    elseif ($value instanceof Token) {
      if ($value->properties) {
        $normalized['data'] = [];
        foreach ($value->properties as $property) {
          $normalized['data'][$property->name] = $this->normalizeValue($token . ':' . $property->name, $property, $depth + 1, $visited);
        }
      }
      else {
        $normalized['value'] = $this->token->replaceClear('[' . $token . ']');
      }
    }
    elseif ($value instanceof PrimitiveInterface) {
      $normalized['value'] = $value->getValue();
    }
    elseif ($value instanceof ContentEntityInterface) {
      // Stop self-referencing entity-reference chains: if this exact entity
      // has already been expanded along the current path, or the depth
      // budget is exhausted, do not recurse into its fields again.
      if ($this->canExpandEntity($value, $depth, $visited)) {
        $entityTypeId = $value->getEntityTypeId();
        $childVisited = $this->markVisited($value, $visited);
        $normalized['data'] = $this->normalizeRecursive($token, $entityTypeId, [$entityTypeId => $value], $depth, $childVisited);
      }
    }
    elseif ($value instanceof DataTransferObject) {
      // For complex DTOs, we've already converted its properties to an
      // array above.
      $normalized['value'] = $value->getString();
    }
    elseif ($value instanceof TypedDataInterface) {
      $normalized['value'] = $value->getDataDefinition()->getDataType();
    }
    else {
      $normalized['value'] = is_object($value) ?
        '[Object: ' . get_class($value) . ']' :
        '[Unknown type: ' . gettype($value) . ']';
    }
    return $normalized;
  }

  /**
   * Recursively normalizes token data.
   *
   * @param string $tokenPath
   *   The current token path (e.g. 'node').
   * @param string $tokenType
   *   The token type to normalize (e.g. 'node').
   * @param array $data
   *   The data for token replacement.
   * @param int $depth
   *   The current recursion depth.
   * @param array $visited
   *   A per-path set of already expanded entities, keyed by
   *   "entityType:id". See normalizeValue() for the semantics.
   *
   * @return array
   *   The normalized data for this level.
   */
  private function normalizeRecursive(string $tokenPath, string $tokenType, array $data, int $depth = 1, array $visited = []): array {
    $normalized = [];
    if ($depth > $this->depth() || !isset($this->tokenInfo['tokens'][$tokenType])) {
      return $normalized;
    }

    foreach ($this->tokenInfo['tokens'][$tokenType] as $dataKey => $dataValue) {
      // Skip 'original' tokens below the first depth level. The pre-save
      // entity snapshot creates exponential self-referencing chains that
      // are not useful for debug/replay purposes.
      if ($dataKey === 'original' && $depth > 1) {
        continue;
      }
      $currentTokenPath = $tokenPath . ':' . $dataKey;
      $normalized[$dataKey] = [
        'label' => $dataValue['name'],
        'token' => $currentTokenPath,
      ];

      $value = reset($data);
      // Flatten the 'original' pre-save snapshot to scalar field values only.
      // The snapshot is an entity copy whose own fields would otherwise be
      // expanded into a nested entity tree, re-inflating the debug/replay
      // payload. Issue #3585592 already stopped 'original' from recursing
      // below level 1; here its level-1 content is reduced to scalar field
      // values, dropping any nested entity expansion.
      if ($dataKey === 'original' && $depth === 1 && isset($dataValue['type']) && isset($this->tokenInfo['tokens'][$dataValue['type']])) {
        $flattened = $this->normalizeScalarFields($currentTokenPath, $dataValue['type'], $data);
        if (!empty($flattened)) {
          $normalized[$dataKey]['data'] = $flattened;
        }
      }
      elseif ($depth === 1 && ($dataValue['type'] ?? '') === 'url' && $value instanceof ContentEntityInterface && $value->isNew()) {
        // On the top level, we could have a new entity which doesn't have an ID
        // yet, and hence there wouldn't be a URL either.
        $normalized[$dataKey]['value'] = '';
      }
      elseif (isset($dataValue['type']) && isset($this->tokenInfo['types'][$dataValue['type']])) {
        $subType = $dataValue['type'];
        // Per-path cycle guard for entity-reference chains. When the current
        // sub-token resolves to a concrete content entity (e.g. an
        // entity-reference field such as employer_id, owner or
        // user_picture), expand it only if that exact entity has not already
        // been expanded earlier along this path. This stops self-referencing
        // loops (employer_id -> employer_id) and back-references
        // (owner -> user_picture -> owner) while still allowing distinct
        // entities of the same type to be expanded (user A -> user B).
        //
        // The lookup is restricted to a single resolved entity and is
        // therefore cheap; non-entity sub-tokens (dates, plain references)
        // are unaffected. The $visited set is passed by value, so sibling
        // branches keep their own view.
        $referenced = $this->resolveReferencedEntity($currentTokenPath, $data);
        if ($referenced instanceof ContentEntityInterface && !$this->canExpandEntity($referenced, $depth + 1, $visited)) {
          continue;
        }
        $childVisited = $this->markVisited($referenced ?? $value, $visited);
        // If we have a subsequent level, we process that recursively and
        // don't replace the token itself, because that could be very
        // expensive, i.e. if the current token is an entity that would be
        // fully rendered when used in token replacement.
        $nestedData = $this->normalizeRecursive($currentTokenPath, $subType, $data, $depth + 1, $childVisited);
        if (!empty($nestedData)) {
          $normalized[$dataKey]['data'] = $nestedData;
        }
      }
      else {
        try {
          $normalized[$dataKey]['value'] = $this->token->replaceClear('[' . $currentTokenPath . ']', $data);
        }
        catch (\Throwable) {
          // Ignore exceptions during token replacement.
        }
      }
    }
    return $normalized;
  }

  /**
   * Normalizes only the scalar leaf fields of a token type.
   *
   * Used to flatten the 'original' pre-save snapshot: it iterates the tokens
   * of the given type and keeps only the scalar leaf values, deliberately
   * dropping any sub-token that resolves to another token type (i.e. a nested
   * entity expansion). This keeps the snapshot compact while still exposing
   * the field values that are useful for debug/replay purposes.
   *
   * @param string $tokenPath
   *   The current token path (e.g. 'node:original').
   * @param string $tokenType
   *   The token type to normalize (e.g. 'node').
   * @param array $data
   *   The data for token replacement.
   *
   * @return array
   *   The normalized scalar field values for this type.
   */
  private function normalizeScalarFields(string $tokenPath, string $tokenType, array $data): array {
    $normalized = [];
    if (!isset($this->tokenInfo['tokens'][$tokenType])) {
      return $normalized;
    }
    foreach ($this->tokenInfo['tokens'][$tokenType] as $dataKey => $dataValue) {
      // Skip a nested 'original' token to avoid re-introducing the snapshot
      // chain that this flattening is meant to eliminate.
      if ($dataKey === 'original') {
        continue;
      }
      // Drop sub-tokens that resolve to another token type: those would be
      // nested entity expansions, which we intentionally do not include.
      if (isset($dataValue['type']) && isset($this->tokenInfo['types'][$dataValue['type']])) {
        continue;
      }
      $currentTokenPath = $tokenPath . ':' . $dataKey;
      $normalized[$dataKey] = [
        'label' => $dataValue['name'],
        'token' => $currentTokenPath,
      ];
      try {
        $normalized[$dataKey]['value'] = $this->token->replaceClear('[' . $currentTokenPath . ']', $data);
      }
      catch (\Throwable) {
        // Ignore exceptions during token replacement.
      }
    }
    return $normalized;
  }

  /**
   * Builds the per-path visited key for a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return string|null
   *   The "entityType:id" key, or NULL for new entities that have no ID
   *   yet and therefore cannot meaningfully participate in cycle detection.
   */
  private function entityVisitKey(ContentEntityInterface $entity): ?string {
    if ($entity->isNew()) {
      return NULL;
    }
    $id = (string) $entity->id();
    if ($id === '') {
      return NULL;
    }
    return $entity->getEntityTypeId() . ':' . $id;
  }

  /**
   * Determines whether an entity may still be expanded along the current path.
   *
   * Expansion is denied when the depth budget is exhausted or when the same
   * entity has already been expanded earlier on this path (cycle guard).
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param int $depth
   *   The current recursion depth.
   * @param array $visited
   *   The per-path visited set, keyed by "entityType:id".
   *
   * @return bool
   *   TRUE if the entity may be expanded, FALSE otherwise.
   */
  private function canExpandEntity(ContentEntityInterface $entity, int $depth, array $visited): bool {
    if ($depth > $this->depth()) {
      return FALSE;
    }
    $key = $this->entityVisitKey($entity);
    return $key === NULL || !isset($visited[$key]);
  }

  /**
   * Returns a copy of the visited set with the given entity marked.
   *
   * The set is copied (not mutated in place) so that sibling branches each
   * receive their own per-path view: an entity may legitimately appear under
   * two different branches, but never twice along the same path.
   *
   * @param mixed $value
   *   The value being expanded; only content entities are tracked.
   * @param array $visited
   *   The current per-path visited set.
   *
   * @return array
   *   The visited set, with the entity added when applicable.
   */
  private function markVisited(mixed $value, array $visited): array {
    if ($value instanceof ContentEntityInterface) {
      $key = $this->entityVisitKey($value);
      if ($key !== NULL) {
        $visited[$key] = TRUE;
      }
    }
    return $visited;
  }

  /**
   * Resolves the content entity a sub-token refers to, if any.
   *
   * This inspects the source entity in the current token data context and,
   * when the trailing token segment maps to an entity-reference field,
   * returns the first referenced content entity. It is intentionally cheap:
   * it only ever loads a single referenced entity and never triggers token
   * replacement or rendering. It is used purely for per-path cycle
   * detection; callers must treat a NULL result as "not a tracked entity
   * reference" and continue normally.
   *
   * @param string $tokenPath
   *   The current token path (e.g. 'node:author' or 'node:employer_id').
   * @param array $data
   *   The token data context for the current level. The source entity is the
   *   first element.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The referenced content entity, or NULL when the segment does not
   *   resolve to a loadable entity reference.
   */
  private function resolveReferencedEntity(string $tokenPath, array $data): ?ContentEntityInterface {
    $source = reset($data);
    if (!($source instanceof ContentEntityInterface)) {
      return NULL;
    }
    $segments = explode(':', $tokenPath);
    $fieldName = end($segments);
    if (!$source->hasField($fieldName)) {
      return NULL;
    }
    try {
      $itemList = $source->get($fieldName);
    }
    catch (\Throwable) {
      return NULL;
    }
    if (!($itemList instanceof EntityReferenceFieldItemListInterface)) {
      return NULL;
    }
    try {
      $referenced = $itemList->referencedEntities();
    }
    catch (\Throwable) {
      return NULL;
    }
    $first = $referenced[0] ?? NULL;
    return $first instanceof ContentEntityInterface ? $first : NULL;
  }

  /**
   * Resolves a token value to its underlying content entity, if any.
   *
   * Used for deduplication: when the same entity appears under multiple
   * token names, this method extracts the entity so it can be compared.
   *
   * @param string $key
   *   The token key.
   * @param mixed $value
   *   The token value.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The content entity, or NULL if the value is not an entity.
   */
  private function resolveEntity(string $key, mixed $value): ?ContentEntityInterface {
    if ($value instanceof EntityAdapter) {
      $value = $value->getEntity();
    }
    elseif ($value instanceof Token && $value->type !== '') {
      $value = $this->token->getTokenData($key);
    }
    return $value instanceof ContentEntityInterface ? $value : NULL;
  }

  /**
   * Helper function to get token info.
   */
  public function getSupportedTokens(string $class, string $eventClass): array {
    // @todo Make sure that all possible sources have the token attributes
    //   defined.
    $sources = $this->eventDispatcher->getListeners(EcaEvents::BEFORE_INITIAL_EXECUTION);
    array_unshift($sources, [$class, 'buildEventData']);
    array_unshift($sources, [$class, 'getData']);
    foreach ($this->token->getDataProviders() as $dataProvider) {
      array_unshift($sources, [$dataProvider::class, 'buildEventData']);
      array_unshift($sources, [$dataProvider::class, 'getData']);
    }

    $tokens = [];
    foreach ($sources as $source) {
      if (!is_array($source)) {
        // Some tests define a listener as a closure, which is not an array.
        // Those can be ignored.
        continue;
      }
      [$class, $methodName] = $source;
      try {
        $reflection = new \ReflectionMethod($class, $methodName);
      }
      catch (\ReflectionException) {
        continue;
      }
      do {
        foreach ($reflection->getAttributes() as $attribute) {
          if ($attribute->getName() === 'Drupal\eca\Attribute\Token') {
            /** @var \Drupal\eca\Attribute\Token $token */
            $token = $attribute->newInstance();
            if ($this->getSupportedProperties($eventClass, $token)) {
              $tokens[] = $token;
            }
          }
        }
        try {
          $reflection = $reflection->getPrototype();
        }
        catch (\ReflectionException) {
          $reflection = NULL;
        }
      } while ($reflection !== NULL);
    }
    return $tokens;
  }

  /**
   * Recursive helper function to get token property info.
   *
   * @param string $eventClass
   *   The event class.
   * @param \Drupal\eca\Attribute\Token $token
   *   The token.
   *
   * @return bool
   *   TRUE, if the token has any attributes, FALSE otherwise.
   */
  private function getSupportedProperties(string $eventClass, Token $token): bool {
    if ($this->isClassSupported($eventClass, $token->classes)) {
      $properties = [];
      foreach ($token->properties as $property) {
        if ($this->getSupportedProperties($eventClass, $property)) {
          $properties[] = $property;
        }
      }
      $token->properties = $properties;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Determines if the event class is covered by the list of classes.
   *
   * @param string $eventClass
   *   The event class.
   * @param array $classes
   *   The list of classes.
   *
   * @return bool
   *   TRUE, if either the list of classes if empty, or if the event class is
   *   an instance of one of the given classes; FALSE otherwise.
   */
  private function isClassSupported(string $eventClass, array $classes): bool {
    if (empty($classes)) {
      return TRUE;
    }
    foreach ($classes as $class) {
      if (is_a($eventClass, $class, TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Compresses data for storage in the temp store.
   *
   * @param array $data
   *   The data to compress.
   *
   * @return string
   *   The compressed data string, prefixed with a marker.
   */
  private function compress(array $data): string {
    try {
      $encoded = json_encode($data, JSON_THROW_ON_ERROR);
      return self::COMPRESS_PREFIX . gzcompress($encoded);
    }
    catch (\Throwable) {
      return '';
    }
  }

  /**
   * Decompresses data retrieved from the temp store.
   *
   * @param mixed $data
   *   The data from the temp store.
   *
   * @return array
   *   The decompressed data array.
   */
  private function decompress(mixed $data): array {
    if (is_string($data) && str_starts_with($data, self::COMPRESS_PREFIX)) {
      try {
        $decompressed = gzuncompress(substr($data, strlen(self::COMPRESS_PREFIX)));
        if ($decompressed === FALSE) {
          return [];
        }
        $decoded = json_decode($decompressed, TRUE, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
      }
      catch (\Throwable) {
        return [];
      }
    }
    return [];
  }

}
