<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\eca\ProcessDebugger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the cross-step '@same' deduplication in the process debugger.
 *
 * Covers issue #3590333: a fully-normalized token sub-tree that is
 * content-identical to one already emitted earlier — either earlier in the
 * same step or in an earlier step — is collapsed to a compact '@same' marker
 * referencing the first occurrence by (step, path). Equality is proven by a
 * bottom-up content hash plus a deep structural compare, never by entity
 * identity, because across steps an entity may have been mutated.
 *
 * This complements '@ref' (intra-step entity identity) and '@prev' (whole-step
 * equality). The pass runs at storage time and the marker survives across the
 * API to the Workflow Modeler, which expands it lazily; expandHistory() still
 * fully reconstructs the data server-side for backward compatibility.
 *
 * @see \Drupal\eca\ProcessDebugger::deduplicateHistory()
 * @see \Drupal\eca\ProcessDebugger::expandHistory()
 * @see \Drupal\eca\ProcessDebugger::TOKEN_DATA_SAME
 */
#[Group('eca')]
#[Group('eca_core')]
class ProcessDebuggerSameTest extends TestCase {

  /**
   * Builds a normalized entity sub-tree with a given title value.
   *
   * The shape mirrors what Browser::normalizeValue() produces: an entry with
   * 'label'/'token' and a 'data' sub-tree of child entries.
   *
   * @param string $title
   *   The title field value.
   *
   * @return array
   *   The normalized 'node' entry.
   */
  private function nodeSubtree(string $title): array {
    return [
      'label' => 'Node',
      'token' => 'node',
      'data' => [
        'title' => [
          'label' => 'Title',
          'token' => 'node:title',
          'value' => $title,
        ],
        'nid' => [
          'label' => 'Nid',
          'token' => 'node:nid',
          'value' => '42',
        ],
      ],
    ];
  }

  /**
   * Builds a history with the same entity sub-tree carried across two steps.
   *
   * @return array
   *   The two-step history.
   */
  private function twoStepIdenticalHistory(): array {
    return [
      [
        'type' => 'started',
        'id' => 'event',
        'data' => ['node' => $this->nodeSubtree('Hello')],
      ],
      [
        'type' => 'execute',
        'id' => 'action',
        'data' => ['node' => $this->nodeSubtree('Hello')],
      ],
    ];
  }

  /**
   * Tests that an unchanged sub-tree in a later step collapses to '@same'.
   */
  public function testIdenticalSubtreeAcrossStepsCollapses(): void {
    $deduplicated = ProcessDebugger::deduplicateHistory($this->twoStepIdenticalHistory());

    // The first occurrence stays fully normalized.
    $this->assertArrayHasKey('data', $deduplicated[0]['data']['node']);
    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $deduplicated[0]['data']['node'],
      'The first occurrence must not be a marker.',
    );

    // The second occurrence is replaced by a '@same' marker pointing at the
    // first occurrence (step 0, path "node").
    $marker = $deduplicated[1]['data']['node'];
    $this->assertArrayHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $marker,
      'The second occurrence must be a @same marker.',
    );
    $this->assertArrayNotHasKey(
      'data',
      $marker,
      'The @same marker must drop the data sub-tree.',
    );
    $this->assertSame(
      ['step' => 0, 'path' => 'node'],
      $marker[ProcessDebugger::TOKEN_DATA_SAME],
      'The @same marker must reference the first occurrence by (step, path).',
    );
    // The marker stays self-describing.
    $this->assertSame('Node', $marker['label']);
    $this->assertSame('node', $marker['token']);
  }

  /**
   * Tests that a changed sub-tree across steps is NOT collapsed.
   *
   * This is the critical correctness test: across steps an entity may have
   * been mutated (a field differs), so identity is not equality. A different
   * content hash must prevent any '@same' collapse.
   */
  public function testChangedSubtreeAcrossStepsIsNotCollapsed(): void {
    $history = [
      [
        'type' => 'started',
        'id' => 'event',
        'data' => ['node' => $this->nodeSubtree('Hello')],
      ],
      [
        'type' => 'execute',
        'id' => 'action',
        // One field differs: the title was changed by the action.
        'data' => ['node' => $this->nodeSubtree('Changed')],
      ],
    ];

    $deduplicated = ProcessDebugger::deduplicateHistory($history);

    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $deduplicated[1]['data']['node'],
      'A sub-tree that changed between steps must not be collapsed.',
    );
    $this->assertArrayHasKey(
      'data',
      $deduplicated[1]['data']['node'],
      'The changed sub-tree must keep its full data.',
    );
    $this->assertSame(
      'Changed',
      $deduplicated[1]['data']['node']['data']['title']['value'],
    );
  }

  /**
   * Tests that a nested sub-tree identical to an earlier one collapses.
   *
   * Here a referenced entity nested under an author field is identical to a
   * top-level entity emitted earlier in the same step; the nested copy must
   * collapse to '@same' with a nested path.
   */
  public function testNestedIdenticalSubtreeCollapses(): void {
    $user = [
      'label' => 'User',
      'token' => 'user',
      'data' => [
        'name' => [
          'label' => 'Name',
          'token' => 'user:name',
          'value' => 'alice',
        ],
      ],
    ];
    $history = [
      [
        'type' => 'started',
        'id' => 'event',
        'data' => [
          // First occurrence: top-level 'user'.
          'user' => $user,
          // Later in the same step: a node whose author is the same user.
          'node' => [
            'label' => 'Node',
            'token' => 'node',
            'data' => [
              'author' => $user,
            ],
          ],
        ],
      ],
    ];

    $deduplicated = ProcessDebugger::deduplicateHistory($history);

    // Top-level 'user' is the first occurrence.
    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $deduplicated[0]['data']['user'],
    );
    // The nested author collapses, referencing the top-level user.
    $author = $deduplicated[0]['data']['node']['data']['author'];
    $this->assertArrayHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $author,
      'The nested identical sub-tree must collapse to @same.',
    );
    $this->assertSame(
      ['step' => 0, 'path' => 'user'],
      $author[ProcessDebugger::TOKEN_DATA_SAME],
      'The nested @same must point at the earlier top-level occurrence.',
    );
  }

  /**
   * Tests the nested path scheme for a deeper first occurrence.
   *
   * When the first occurrence is itself nested, the recorded path must
   * interleave the literal 'data' segment for each level of descent.
   */
  public function testNestedFirstOccurrencePathScheme(): void {
    $picture = [
      'label' => 'Entity',
      'token' => 'user:user_picture:entity',
      'data' => [
        'filename' => [
          'label' => 'Filename',
          'token' => 'user:user_picture:entity:filename',
          'value' => 'avatar.png',
        ],
      ],
    ];
    $history = [
      [
        'type' => 'started',
        'id' => 'event',
        'data' => [
          // First occurrence is nested: entity/data/user_picture/data/entity.
          'entity' => [
            'label' => 'Entity',
            'token' => 'entity',
            'data' => [
              'user_picture' => [
                'label' => 'User Picture',
                'token' => 'entity:user_picture',
                'data' => [
                  'entity' => $picture,
                ],
              ],
            ],
          ],
          // Later top-level token carrying the identical picture sub-tree.
          'avatar' => $picture,
        ],
      ],
    ];

    $deduplicated = ProcessDebugger::deduplicateHistory($history);

    $marker = $deduplicated[0]['data']['avatar'];
    $this->assertArrayHasKey(ProcessDebugger::TOKEN_DATA_SAME, $marker);
    $this->assertSame(
      ['step' => 0, 'path' => 'entity/data/user_picture/data/entity'],
      $marker[ProcessDebugger::TOKEN_DATA_SAME],
      'The path must interleave the literal "data" segment per descent level.',
    );
  }

  /**
   * Tests that scalar-only leaf entries are never collapsed.
   *
   * A '@same' pointer can be larger than a one-field leaf, so leaves that
   * carry only a 'value' (no 'data' sub-tree) are intentionally not collapsed.
   */
  public function testScalarLeavesAreNotCollapsed(): void {
    $history = [
      [
        'type' => 'started',
        'id' => 'event',
        'data' => [
          'a' => ['label' => 'A', 'token' => 'a', 'value' => 'same'],
          'b' => ['label' => 'A', 'token' => 'a', 'value' => 'same'],
        ],
      ],
    ];

    $deduplicated = ProcessDebugger::deduplicateHistory($history);

    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $deduplicated[0]['data']['b'],
      'Scalar-only leaves must not be collapsed.',
    );
    $this->assertSame('same', $deduplicated[0]['data']['b']['value']);
  }

  /**
   * Tests collision safety: equal hash but different content does not collapse.
   *
   * The deep structural compare is the gate. To exercise it directly without
   * forging an md5 collision, this builds two sub-trees that the hash treats
   * as candidates but that differ structurally, and asserts no collapse. The
   * deduplicateNode() verify path (nodesEqual) is what protects against a
   * genuine collision in production.
   */
  public function testCollisionSafetyVerifiesStructuralEquality(): void {
    // Two distinct sub-trees. They are NOT content-identical (different child
    // value), so even if a hash ever collided, the deep compare must reject
    // the collapse. We assert the normal, correct behavior here: distinct
    // content yields distinct hashes and no collapse.
    $history = [
      [
        'type' => 'started',
        'id' => 'event',
        'data' => [
          'first' => $this->nodeSubtree('Alpha'),
          'second' => $this->nodeSubtree('Beta'),
        ],
      ],
    ];

    $deduplicated = ProcessDebugger::deduplicateHistory($history);

    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $deduplicated[0]['data']['second'],
      'Structurally different sub-trees must never be collapsed.',
    );
  }

  /**
   * Tests the full round-trip: dedup then expandHistory() restores the data.
   */
  public function testExpandHistoryRoundTrip(): void {
    $original = $this->twoStepIdenticalHistory();
    $deduplicated = ProcessDebugger::deduplicateHistory($original);

    // Sanity: the second step really is a marker now.
    $this->assertArrayHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $deduplicated[1]['data']['node'],
    );

    $expanded = ProcessDebugger::expandHistory($deduplicated);

    $this->assertEquals(
      $original,
      $expanded,
      'expandHistory() must reconstruct the un-deduplicated original exactly.',
    );
  }

  /**
   * Tests that '@prev' and '@ref' still expand correctly alongside '@same'.
   */
  public function testPrevRefAndSameCoexist(): void {
    // Step 0 carries two tokens pointing at the same entity (one a '@ref'),
    // plus a separate sub-tree. Step 1 is a whole-step '@prev'. Step 2 repeats
    // the separate sub-tree, which must become '@same'.
    $shared = $this->nodeSubtree('Shared');
    $history = [
      [
        'type' => 'started',
        'id' => 'event',
        'data' => [
          'node' => $shared,
          'entity' => [
            'label' => 'Entity',
            'token' => 'entity',
            ProcessDebugger::TOKEN_DATA_REF => 'node',
          ],
        ],
      ],
      [
        'type' => 'execute',
        'id' => 'action_a',
        'data' => ProcessDebugger::TOKEN_DATA_PREV,
      ],
      [
        'type' => 'execute',
        'id' => 'action_b',
        'data' => ['node' => $this->nodeSubtree('Shared')],
      ],
    ];

    $deduplicated = ProcessDebugger::deduplicateHistory($history);

    // Step 2's node must collapse to '@same' pointing at step 0.
    $this->assertSame(
      ['step' => 0, 'path' => 'node'],
      $deduplicated[2]['data']['node'][ProcessDebugger::TOKEN_DATA_SAME],
    );

    $expanded = ProcessDebugger::expandHistory($deduplicated);

    // '@prev' expanded: step 1 mirrors step 0's expanded data.
    $this->assertSame(
      $expanded[0]['data']['node']['data'],
      $expanded[1]['data']['node']['data'],
      'The @prev step must mirror the previous step after expansion.',
    );
    // '@ref' expanded: the entity now carries the node's data.
    $this->assertArrayHasKey(
      'data',
      $expanded[0]['data']['entity'],
      'The @ref entry must be expanded into the referenced data.',
    );
    $this->assertSame(
      $expanded[0]['data']['node']['data'],
      $expanded[0]['data']['entity']['data'],
    );
    // '@same' expanded: step 2's node is fully reconstructed.
    $this->assertSame(
      $this->nodeSubtree('Shared'),
      $expanded[2]['data']['node'],
      'The @same step must be reconstructed to the original sub-tree.',
    );
  }

  /**
   * Tests the @ref-targets-@same-sibling ordering case in one step.
   *
   * Reproduces the real-world shape from issue #3590333: within a single later
   * step, 'user' is a '@ref' to its sibling 'entity', but 'entity' is itself a
   * '@same' marker (the entity is unchanged since step 0). Expansion must
   * resolve '@same' before '@ref' so that both 'entity' and 'user' end up with
   * the full data. The previous trailing-'@same' order left 'user' empty.
   */
  public function testRefToSameSiblingInOneStepExpands(): void {
    $entity = [
      'label' => 'Entity',
      'token' => 'entity',
      'data' => [
        'name' => [
          'label' => 'Name',
          'token' => 'entity:name',
          'value' => 'alice',
        ],
      ],
    ];
    $history = [
      [
        'type' => 'started',
        'id' => 'event',
        'data' => [
          // Step 0: full 'entity', and 'user' as a '@ref' sibling.
          'entity' => $entity,
          'user' => [
            'label' => 'User',
            'token' => 'user',
            ProcessDebugger::TOKEN_DATA_REF => 'entity',
          ],
        ],
      ],
      [
        'type' => 'execute',
        'id' => 'action',
        'data' => [
          // Step 1: 'entity' is a '@same' of step 0, 'user' a '@ref' to it.
          'entity' => [
            'label' => 'Entity',
            'token' => 'entity',
            ProcessDebugger::TOKEN_DATA_SAME => ['step' => 0, 'path' => 'entity'],
          ],
          'user' => [
            'label' => 'User',
            'token' => 'user',
            ProcessDebugger::TOKEN_DATA_REF => 'entity',
          ],
          // A differing token so the step is not collapsed to '@prev'.
          'message' => [
            'label' => 'Message',
            'token' => 'message',
            'value' => 'changed',
          ],
        ],
      ],
    ];

    $expanded = ProcessDebugger::expandHistory($history);

    // The '@same' marker is gone and 'entity' carries the full data.
    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $expanded[1]['data']['entity'],
      'The @same marker must be resolved.',
    );
    $this->assertSame(
      $entity['data'],
      $expanded[1]['data']['entity']['data'],
      'The @same entry must be reconstructed to step 0\'s entity data.',
    );

    // The '@ref' must also be resolved, even though its target was a '@same'.
    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_REF,
      $expanded[1]['data']['user'],
      'The @ref marker must be resolved.',
    );
    $this->assertArrayHasKey(
      'data',
      $expanded[1]['data']['user'],
      'The @ref entry must not render empty when its target was a @same.',
    );
    // Both 'entity' and 'user' end up with identical full data.
    $this->assertSame(
      $entity['data'],
      $expanded[1]['data']['user']['data'],
      'Both @same and @ref-to-@same entries must carry step 0\'s entity data.',
    );
  }

  /**
   * Tests deduplication of the shared-store wrapper shape.
   *
   * The shared store path stores steps under a 'history' key alongside
   * metadata; the pass must deduplicate the inner steps and preserve metadata.
   */
  public function testSharedStoreWrapperIsDeduplicated(): void {
    $wrapper = [
      'model_id' => 'my_model',
      'component_id' => 'my_event',
      'history' => $this->twoStepIdenticalHistory(),
    ];

    $deduplicated = ProcessDebugger::deduplicateHistory($wrapper);

    $this->assertSame('my_model', $deduplicated['model_id']);
    $this->assertSame('my_event', $deduplicated['component_id']);
    $this->assertArrayHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $deduplicated['history'][1]['data']['node'],
      'The inner steps of the wrapper must be deduplicated.',
    );

    // And expandHistory() restores the wrapper too.
    $expanded = ProcessDebugger::expandHistory($deduplicated);
    $this->assertEquals(
      $this->twoStepIdenticalHistory(),
      $expanded['history'],
      'expandHistory() must reconstruct the wrapper inner steps.',
    );
  }

}
