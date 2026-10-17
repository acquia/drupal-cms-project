<?php

namespace Drupal\eca;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eca\Token\Browser;

/**
 * Next generation debugger.
 */
class ProcessDebugger {

  /**
   * Whether the debugger is enabled.
   *
   * @var bool
   */
  public static bool $debug = FALSE;

  /**
   * Whether the debugger has been started.
   *
   * @var bool
   */
  protected bool $started = FALSE;

  /**
   * The label of the ECA model.
   *
   * @var string
   */
  protected string $ecaLabel = '';

  /**
   * The label of the event being processed.
   *
   * @var string
   */
  protected string $eventLabel = '';

  /**
   * Marker value for deduplicated token data.
   *
   * When consecutive debug entries have identical token data, this marker
   * is stored instead of the full data to reduce storage volume.
   */
  public const string TOKEN_DATA_PREV = '@prev';

  /**
   * Key for entity reference markers in token data.
   *
   * When the same content entity appears under multiple token names,
   * only the first occurrence stores the full data. Subsequent occurrences
   * store a reference marker pointing to the first token key.
   */
  public const string TOKEN_DATA_REF = '@ref';

  /**
   * Key for cross-step content-identity markers in token data.
   *
   * Where '@ref' deduplicates the same entity (by UUID) WITHIN a single step,
   * and '@prev' deduplicates an entire step against the previous one, '@same'
   * deduplicates a fully-normalized sub-tree that is CONTENT-IDENTICAL to a
   * sub-tree already emitted earlier in the history — either earlier in the
   * SAME step or in an EARLIER step. Equality is proven by a bottom-up
   * (Merkle-style) hash of the fully-normalized sub-tree plus a deep
   * structural compare, never by entity identity: across steps an entity may
   * have been mutated, so identity is not equality. '@same' is therefore
   * orthogonal to '@ref' (intra-step identity) and '@prev' (whole-step
   * equality).
   *
   * Marker shape on a token entry:
   * @code
   *   [
   *     'label' => <string>,
   *     'token' => <string>,
   *     '@same' => ['step' => <int>, 'path' => <string>],
   *   ]
   * @endcode
   * The marker REPLACES the 'data' (or 'value') the entry would otherwise
   * carry.
   *
   * Path scheme: a slash-separated locator of the sub-tree within the FIRST
   * occurrence's normalized token data, expressed as the exact sequence of
   * array keys to index from that step's token-data root. Top-level entries
   * use just their token key (e.g. 'entity'). Nested entries interleave the
   * literal 'data' segment for every level of descent, mirroring the
   * normalized array structure, e.g. 'entity/data/user_picture/data/entity'
   * resolves to $step['data']['entity']['data']['user_picture']['data']
   * ['entity']. The 'step' value is the zero-based index into the history
   * (or steps) array of that first occurrence. The reference is fully
   * self-contained within the history array, so a consumer (e.g. the
   * Workflow Modeler frontend) can resolve it without any additional context.
   */
  public const string TOKEN_DATA_SAME = '@same';

  /**
   * The history of debug entries for this process.
   *
   * @var array
   */
  protected array $history = [];

  /**
   * Hash of the last stored token data for deduplication.
   *
   * @var string
   */
  protected string $lastTokenDataHash = '';

  /**
   * The event name of this process.
   *
   * @var string
   */
  protected string $eventName;

  /**
   * Constructs a debugger object.
   *
   * @param \Drupal\eca\Token\Browser $tokenBrowser
   *   The token browser.
   * @param string $ecaId
   *   The ID of the ECA model.
   * @param string $eventId
   *   The ID of the event being processed.
   */
  public function __construct(
    protected Browser $tokenBrowser,
    protected string $ecaId,
    protected string $eventId,
  ) {}

  /**
   * Checks if the debugger has been started.
   *
   * @return bool
   *   TRUE if the debugger has been started, FALSE otherwise.
   */
  public function isStarted(): bool {
    return $this->started;
  }

  /**
   * Gets the ECA model ID.
   *
   * @return string
   *   The ECA model ID.
   */
  public function getEcaId(): string {
    return $this->ecaId;
  }

  /**
   * Gets the event ID.
   *
   * @return string
   *   The event ID.
   */
  public function getEventId(): string {
    return $this->eventId;
  }

  /**
   * Gets the ECA model label.
   *
   * @return string
   *   The ECA model label.
   */
  public function getEcaLabel(): string {
    return $this->ecaLabel;
  }

  /**
   * Gets the event label.
   *
   * @return string
   *   The event label.
   */
  public function getEventLabel(): string {
    return $this->eventLabel;
  }

  /**
   * Gets the MD5 hash of the debug history.
   *
   * Stores the history in Drupal's temp store for later retrieval.
   *
   * @return string
   *   The MD5 hash of the compressed history data.
   */
  public function getHistoryHash(): string {
    return $this->tokenBrowser->storeHistory($this->history);
  }

  /**
   * Stores the history data in Drupal's shared temp store by event.
   */
  public function storeHistoryByEvent(): void {
    if (!self::$debug) {
      return;
    }
    $this->tokenBrowser->storeHistory([
      'model_id' => $this->ecaId,
      'component_id' => $this->eventId,
      'history' => $this->history,
    ], implode('::', [$this->ecaId, $this->eventId]));
  }

  /**
   * Sets the ECA model label.
   *
   * @param string $label
   *   The ECA model label.
   */
  public function setEcaLabel(string $label): void {
    $this->ecaLabel = $label;
  }

  /**
   * Sets the event label.
   *
   * @param string $label
   *   The event label.
   */
  public function setEventLabel(string $label): void {
    $this->eventLabel = $label;
  }

  /**
   * Records that the event does not apply to the ECA model configuration.
   */
  public function doesNotApply(): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = ['type' => 'does not apply'];
  }

  /**
   * Records that the ECA model or the event in the model does not exist.
   */
  public function doesNotExist(): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = ['type' => 'does not exist'];
  }

  /**
   * Records that the event does not execute.
   */
  public function doesNotExecute(): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = ['type' => 'does not execute'];
  }

  /**
   * Records that recursion has been detected during execution.
   */
  public function recursionDetected(): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = ['type' => 'recursion detected'];
  }

  /**
   * Records that the event of this debugger has started.
   *
   * Initializes the token services and records the starting state including
   * current token data.
   *
   * @param string $eventName
   *   The event name.
   */
  public function started(string $eventName): void {
    $this->started = TRUE;
    $this->eventName = $eventName;
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'started',
      'id' => $this->eventId,
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Records that a successor has been added to the execution queue.
   *
   * @param string $id
   *   The ID of the current component.
   * @param string $successorId
   *   The ID of the successor being added.
   * @param string|bool|null $conditionId
   *   The ID of the condition that caused the successor to be added.
   * @param int $type
   *   The type of the successor, action or gateway.
   */
  public function addSuccessor(string $id, string $successorId, string|bool|null $conditionId, int $type): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'add successor',
      'id' => $id,
      'successorId' => $successorId,
      'conditionId' => $conditionId,
      'successorType' => $type,
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Records that a successor has been ignored during execution.
   *
   * This happens when the condition attached to the connection to the
   * successor doesn't return TRUE.
   *
   * @param string $id
   *   The ID of the current component.
   * @param string $successorId
   *   The ID of the successor being ignored.
   * @param string|bool|null $conditionId
   *   The ID of the condition that caused the successor to be ignored.
   * @param int $type
   *   The type of the successor, action or gateway.
   */
  public function ignoreSuccessor(string $id, string $successorId, string|bool|null $conditionId, int $type): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'ignore successor',
      'id' => $id,
      'successorId' => $successorId,
      'conditionId' => $conditionId,
      'successorType' => $type,
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Records that access was denied for a specific action.
   *
   * @param string $id
   *   The ID of the action that was denied access.
   */
  public function accessDenied(string $id): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'access denied',
      'id' => $id,
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Records the execution of an action.
   *
   * The operating object is reduced to a JSON-safe descriptor (see
   * ::describeObject()) because the history is later JSON-encoded by
   * Browser::compress(), which cannot encode entities or other objects.
   *
   * @param string $id
   *   The ID of the action being executed.
   * @param mixed $object
   *   The object being passed to the action for execution.
   */
  public function execute(string $id, mixed $object): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'execute',
      'id' => $id,
      'object' => $this->describeObject($object),
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Records an exception that occurred during execution.
   *
   * The operating object is reduced to a JSON-safe descriptor (see
   * ::describeObject()) because the history is later JSON-encoded by
   * Browser::compress(), which cannot encode entities or other objects.
   *
   * @param string $id
   *   The ID of the action where the exception occurred.
   * @param mixed $object
   *   The object being executed when the exception occurred.
   * @param \Exception $ex
   *   The exception that was thrown.
   */
  public function exception(string $id, mixed $object, \Exception $ex): void {
    if (!self::$debug) {
      return;
    }
    $this->history[] = [
      'type' => 'exception',
      'id' => $id,
      'object' => $this->describeObject($object),
      'exception' => [
        'class' => get_class($ex),
        'code' => $ex->getCode(),
        'message' => $ex->getMessage(),
        'file' => $ex->getFile() . ':' . $ex->getLine(),
        'trace' => $ex->getTraceAsString(),
      ],
      'data' => $this->getTokenData(),
    ];
  }

  /**
   * Reduces an operating object to a JSON-safe descriptor.
   *
   * The history array is JSON-encoded by Browser::compress(). Storing the raw
   * operating object there is unsafe: entities and other objects with circular
   * references make json_encode() throw, causing compress() to silently
   * discard the entire run's replay history. The descriptor is never read by
   * any replay consumer; it exists only as a human-readable hint about what
   * the action operated on, so a compact scalar representation is sufficient.
   *
   * @param mixed $object
   *   The operating object, which may be a scalar, an array, an entity or any
   *   other object.
   *
   * @return string|int|float|bool|null
   *   A JSON-safe descriptor: scalars and NULL are returned unchanged; content
   *   entities become '[Entity: <type>:<id>]'; other objects become
   *   '[Object: <class>]'; arrays become '[Array: <count> items]'.
   */
  protected function describeObject(mixed $object): string|int|float|bool|null {
    if ($object === NULL || is_scalar($object)) {
      return $object;
    }
    if (is_array($object)) {
      return '[Array: ' . count($object) . ' items]';
    }
    if (is_object($object)) {
      if ($object instanceof ContentEntityInterface) {
        return '[Entity: ' . $object->getEntityTypeId() . ':' . ($object->id() ?? 'new') . ']';
      }
      return '[Object: ' . get_class($object) . ']';
    }
    return '[Unknown type: ' . gettype($object) . ']';
  }

  /**
   * Gets the current token data, with deduplication.
   *
   * If the token data has not changed since the last call, the marker
   * string '@prev' is returned instead of the full data array. This
   * significantly reduces storage volume for history entries where actions
   * do not modify token state.
   *
   * @return array|string
   *   The token data array, or the marker string '@prev' if unchanged.
   */
  protected function getTokenData(): array|string {
    $data = $this->tokenBrowser->normalizedTokenData($this->eventName);
    $hash = md5(serialize($data));
    if ($hash === $this->lastTokenDataHash) {
      return self::TOKEN_DATA_PREV;
    }
    $this->lastTokenDataHash = $hash;
    return $data;
  }

  /**
   * Expands deduplicated token data markers in a history array.
   *
   * Walks the history in order and fully reconstructs each step's token data,
   * resolving all three deduplication markers. Per step the markers are
   * resolved in strict dependency order: '@prev' (whole-step) first, then
   * '@same' (cross-step sub-tree), then '@ref' (intra-step sibling). This
   * order is required because a '@ref' may target a sibling that is itself a
   * '@same' marker, so '@same' must be resolved before '@ref' copies from it
   * (issue #3590333). Earlier steps are fully expanded before later steps, so
   * a '@same' marker can always resolve against already-expanded data.
   *
   * @param array $history
   *   The history array, potentially containing '@prev', '@same' and '@ref'
   *   markers.
   *
   * @return array
   *   The history array with all deduplication markers expanded.
   */
  public static function expandHistory(array $history): array {
    // Bare shared-store wrapper: the steps live under a top-level 'history'
    // key alongside metadata (model_id, component_id). Expand the inner steps
    // and preserve the metadata.
    if (isset($history['history']) && is_array($history['history'])) {
      $history['history'] = self::expandHistory($history['history']);
      return $history;
    }

    // First expand any nested 'history' sub-arrays (the shared store path
    // stores a list of such wrappers), so the top-level expansion below
    // operates on a single, flat list of steps.
    foreach ($history as &$nestedEntry) {
      if (is_array($nestedEntry) && isset($nestedEntry['history']) && is_array($nestedEntry['history'])) {
        $nestedEntry['history'] = self::expandHistory($nestedEntry['history']);
      }
    }
    unset($nestedEntry);

    // Resolve the markers in a single in-order pass over the steps, applying
    // the three markers PER STEP in strict dependency order:
    // 1. '@prev' (whole-step): reuse the previous step's fully expanded data.
    // 2. '@same' (cross-step): resolve sub-trees that referenced an earlier
    // (step, path); earlier steps are already fully expanded by the time this
    // step is processed.
    // 3. '@ref' (intra-step): copy a sibling's data; this MUST run after
    // '@same' so that a sibling which was itself a '@same' marker already
    // carries its real 'data'.
    //
    // The order matters: a step can legitimately contain a '@ref' that points
    // at a sibling which is itself a '@same' marker (e.g. 'user' -> @ref
    // 'entity', while 'entity' is @same of step 0). Expanding '@ref' before
    // '@same' would leave the '@ref' entry empty, because its target has no
    // 'data' yet. See issue #3590333.
    $lastData = [];
    foreach ($history as &$entry) {
      if (!is_array($entry)) {
        continue;
      }
      if (array_key_exists('data', $entry)) {
        if ($entry['data'] === self::TOKEN_DATA_PREV) {
          // The previous step's data is already fully expanded.
          $entry['data'] = $lastData;
        }
        elseif (is_array($entry['data'])) {
          // Resolve cross-step '@same' markers first, against the history
          // processed so far (earlier steps are already fully expanded).
          self::expandSameInData($entry['data'], $history);
          // Then resolve intra-step '@ref' markers: every sibling now carries
          // real 'data', including ones that were '@same'.
          self::expandRefs($entry['data']);
          $lastData = $entry['data'];
        }
      }
    }
    unset($entry);

    return $history;
  }

  /**
   * Expands entity reference markers within token data.
   *
   * When the same entity appears under multiple token names, only the first
   * occurrence stores the full data. Subsequent occurrences store a '@ref'
   * marker pointing to the first token key. This method replaces those
   * markers with the actual data from the referenced entry.
   *
   * Defensive: a '@ref' is only resolved when its target sibling already
   * carries a real 'data' sub-tree. With the per-step expansion order
   * ('@prev' -> '@same' -> '@ref') the target is always fully expanded by the
   * time this runs, but should a target still hold an unresolved marker (and
   * thus no 'data'), the '@ref' entry is left as-is rather than copying
   * garbage or crashing.
   *
   * @param array $data
   *   The token data array, potentially containing '@ref' markers.
   */
  private static function expandRefs(array &$data): void {
    foreach ($data as &$value) {
      if (!is_array($value) || !isset($value[self::TOKEN_DATA_REF])) {
        continue;
      }
      $target = $value[self::TOKEN_DATA_REF];
      if (isset($data[$target]['data'])) {
        $value['data'] = $data[$target]['data'];
        unset($value[self::TOKEN_DATA_REF]);
      }
    }
  }

  /**
   * Deduplicates content-identical sub-trees across a whole history payload.
   *
   * This is the cross-step '@same' pass. It walks the FULL, already-normalized
   * history in order (per-step '@ref'/'@prev' markers may already be present)
   * and replaces every fully-normalized sub-tree that is content-identical to
   * one emitted earlier — either earlier in the same step or in an earlier
   * step — with a compact '@same' marker referencing the FIRST occurrence by
   * (step, path). See the TOKEN_DATA_SAME constant for the marker shape and
   * the path scheme.
   *
   * Equality is established in two stages: a bottom-up (Merkle-style) hash of
   * the fully-normalized sub-tree narrows candidates, and a deep structural
   * compare against the recorded first occurrence confirms it before any
   * collapse. A hash collision therefore never produces a false '@same'.
   *
   * Existing markers are handled gracefully: a step whose 'data' is the
   * '@prev' string carries no sub-tree and is skipped; an entry that is itself
   * a '@ref' or '@same' marker carries no 'data' sub-tree to hash and is left
   * untouched. The largest matching sub-tree is collapsed: once a node is
   * replaced by '@same', its children are not visited.
   *
   * The pass is idempotent on data that already contains '@same' markers and
   * never expands them.
   *
   * @param array $history
   *   The normalized history array. May be a flat list of step entries or the
   *   shared-store wrapper that carries the steps under a 'history' key.
   *
   * @return array
   *   The history array with content-identical sub-trees collapsed to '@same'.
   */
  public static function deduplicateHistory(array $history): array {
    // Shared-store wrapper: the steps live under a 'history' key alongside
    // metadata (model_id, component_id). Deduplicate the inner steps only.
    if (isset($history['history']) && is_array($history['history'])) {
      $history['history'] = self::deduplicateHistory($history['history']);
      return $history;
    }

    // First-occurrence index: contentHash => ['step' => int, 'path' => string,
    // 'subtree' => array]. The recorded sub-tree is kept for the collision-safe
    // deep compare before any collapse.
    $index = [];
    foreach ($history as $step => &$entry) {
      if (!is_array($entry) || !array_key_exists('data', $entry)) {
        continue;
      }
      // A '@prev' step carries no sub-tree of its own to hash.
      if (!is_array($entry['data'])) {
        continue;
      }
      foreach ($entry['data'] as $key => &$node) {
        if (is_array($node)) {
          self::deduplicateNode($node, (int) $step, (string) $key, $index);
        }
      }
      unset($node);
    }
    unset($entry);
    return $history;
  }

  /**
   * Deduplicates a single normalized node and, recursively, its children.
   *
   * Implements the pre-order, on-demand-hash strategy: the node's full
   * sub-tree hash is computed first; if an identical (and structurally equal)
   * sub-tree was recorded at an earlier document position, the node is
   * collapsed to a '@same' marker and its children are NOT visited (the
   * largest matching sub-tree wins). Otherwise the node is registered as a
   * first occurrence and its children are visited in order.
   *
   * @param array $node
   *   The normalized node, passed by reference so it can be collapsed.
   * @param int $step
   *   The zero-based index of the current step within the history.
   * @param string $path
   *   The slash-separated path locating this node within the step (see the
   *   TOKEN_DATA_SAME constant).
   * @param array $index
   *   The first-occurrence index, passed by reference and shared across the
   *   whole history.
   */
  private static function deduplicateNode(array &$node, int $step, string $path, array &$index): void {
    // Marker entries carry no 'data' sub-tree to compare: '@ref' points at a
    // sibling, '@same' is already collapsed. Leave them untouched.
    if (isset($node[self::TOKEN_DATA_REF]) || isset($node[self::TOKEN_DATA_SAME])) {
      return;
    }
    // Only nodes that own a 'data' sub-tree are candidates for '@same'. A
    // scalar leaf (a node with just 'value') is never collapsed: a '@same'
    // pointer would be larger than the leaf it replaces.
    if (!isset($node['data']) || !is_array($node['data'])) {
      return;
    }

    $hash = self::hashNode($node);
    if (isset($index[$hash]) && self::nodesEqual($node, $index[$hash]['subtree'])) {
      $first = $index[$hash];
      // Replace the sub-tree with a compact marker referencing the first
      // occurrence. 'label' and 'token' are retained so the entry stays
      // self-describing; 'data' is dropped.
      unset($node['data']);
      $node[self::TOKEN_DATA_SAME] = [
        'step' => $first['step'],
        'path' => $first['path'],
      ];
      return;
    }

    // First occurrence: record it (with a snapshot for the deep compare) and
    // descend into the children.
    if (!isset($index[$hash])) {
      $index[$hash] = [
        'step' => $step,
        'path' => $path,
        'subtree' => $node,
      ];
    }
    foreach ($node['data'] as $childKey => &$child) {
      if (is_array($child)) {
        self::deduplicateNode($child, $step, $path . '/data/' . $childKey, $index);
      }
    }
    unset($child);
  }

  /**
   * Computes a stable, bottom-up content hash of a normalized node.
   *
   * The hash folds in the node's own scalar fields (label, token, value) and,
   * Merkle-style, the ordered hashes of its child sub-trees. Two nodes share a
   * hash only when their whole sub-trees are content-identical. A marker node
   * ('@ref'/'@same') hashes its own scalar fields plus the marker, so it never
   * collides with an expanded sub-tree.
   *
   * @param array $node
   *   The normalized node.
   *
   * @return string
   *   The md5 content hash.
   */
  private static function hashNode(array $node): string {
    $canonical = [
      'label' => $node['label'] ?? NULL,
      'token' => $node['token'] ?? NULL,
    ];
    if (array_key_exists('value', $node)) {
      $canonical['value'] = $node['value'];
    }
    if (isset($node[self::TOKEN_DATA_REF])) {
      $canonical[self::TOKEN_DATA_REF] = $node[self::TOKEN_DATA_REF];
    }
    if (isset($node[self::TOKEN_DATA_SAME])) {
      $canonical[self::TOKEN_DATA_SAME] = $node[self::TOKEN_DATA_SAME];
    }
    if (isset($node['data']) && is_array($node['data'])) {
      $childHashes = [];
      foreach ($node['data'] as $childKey => $child) {
        $childHashes[$childKey] = is_array($child)
          ? self::hashNode($child)
          : md5('scalar:' . serialize($child));
      }
      $canonical['data'] = $childHashes;
    }
    return md5(json_encode($canonical, JSON_THROW_ON_ERROR));
  }

  /**
   * Deeply compares two normalized nodes for exact structural equality.
   *
   * Used as the collision-safety gate before collapsing a node to '@same': a
   * hash match is necessary but not sufficient. PHP's strict array comparison
   * (===) is order-sensitive and type-sensitive, which is exactly what is
   * needed here because normalized sub-trees are built deterministically.
   *
   * @param array $a
   *   The candidate node.
   * @param array $b
   *   The recorded first-occurrence node.
   *
   * @return bool
   *   TRUE when the two sub-trees are identical, FALSE otherwise.
   */
  private static function nodesEqual(array $a, array $b): bool {
    return $a === $b;
  }

  /**
   * Resolves the '@same' markers within a single step's token data.
   *
   * Mirrors expandRefs()/the '@prev' expansion for backward compatibility: a
   * consumer that calls expandHistory() server-side still receives fully
   * reconstructed data, with each '@same' marker replaced by a copy of the
   * sub-tree found at the referenced (step, path). The lookup is purely over
   * the supplied history array, so it is self-contained.
   *
   * This runs PER STEP, interleaved between the '@prev' and '@ref' expansion
   * (see expandHistory()): it must run before '@ref' so that a sibling which
   * was itself a '@same' marker already carries its real 'data' when
   * expandRefs() copies from it. Because expandHistory() processes steps in
   * order, every step a '@same' marker can reference (an earlier step, or an
   * earlier first-occurrence within the same step) is already fully expanded
   * by the time this method reads from $history.
   *
   * @param array $data
   *   The current step's token data, passed by reference so markers can be
   *   replaced in place.
   * @param array $history
   *   The history array processed so far, used to resolve the (step, path)
   *   reference. Earlier steps are already fully expanded.
   */
  private static function expandSameInData(array &$data, array $history): void {
    foreach ($data as &$node) {
      if (is_array($node)) {
        self::expandSameNode($node, $history);
      }
    }
    unset($node);
  }

  /**
   * Resolves a single node's '@same' marker and recurses into its children.
   *
   * @param array $node
   *   The normalized node, passed by reference so the marker can be replaced.
   * @param array $history
   *   The full history array, used to resolve the (step, path) reference.
   */
  private static function expandSameNode(array &$node, array $history): void {
    if (isset($node[self::TOKEN_DATA_SAME])) {
      $reference = $node[self::TOKEN_DATA_SAME];
      $resolved = self::resolveSamePath($history, (int) ($reference['step'] ?? -1), (string) ($reference['path'] ?? ''));
      if ($resolved !== NULL && isset($resolved['data'])) {
        $node['data'] = $resolved['data'];
      }
      unset($node[self::TOKEN_DATA_SAME]);
    }
    if (isset($node['data']) && is_array($node['data'])) {
      foreach ($node['data'] as &$child) {
        if (is_array($child)) {
          self::expandSameNode($child, $history);
        }
      }
      unset($child);
    }
  }

  /**
   * Locates the node referenced by a '@same' (step, path) within the history.
   *
   * The path is the slash-separated sequence of array keys to index from the
   * referenced step's token-data root (interleaving the literal 'data'
   * segment for each level of descent — see the TOKEN_DATA_SAME constant).
   *
   * @param array $history
   *   The full history array (flat list of steps).
   * @param int $step
   *   The zero-based step index of the first occurrence.
   * @param string $path
   *   The slash-separated locator within that step.
   *
   * @return array|null
   *   The referenced node, or NULL when it cannot be resolved.
   */
  private static function resolveSamePath(array $history, int $step, string $path): ?array {
    if ($path === '' || !isset($history[$step]['data']) || !is_array($history[$step]['data'])) {
      return NULL;
    }
    $current = $history[$step]['data'];
    foreach (explode('/', $path) as $segment) {
      if (!is_array($current) || !array_key_exists($segment, $current)) {
        return NULL;
      }
      $current = $current[$segment];
    }
    return is_array($current) ? $current : NULL;
  }

}
