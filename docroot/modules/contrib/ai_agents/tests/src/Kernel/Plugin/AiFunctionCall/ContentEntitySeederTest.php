<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the ContentEntitySeeder class.
 *
 * Focuses on the write path access checks: entity level create/update access
 * as well as the per-field 'edit' access check that prevents a low privileged
 * user from writing protected fields they may not edit.
 *
 * @group ai_agents
 */
#[RunTestsInSeparateProcesses]
final class ContentEntitySeederTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   */
  protected $functionCallManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The admin user account (uid 1, bypasses access checks).
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * A user that may create and edit articles but is subject to field access.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $contentEditor;

  /**
   * A user without any article create or edit permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $lowPrivUser;

  /**
   * An existing article content entity ID.
   *
   * @var int
   */
  protected $articleOne;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'ai',
    'ai_agents',
    'ai_agents_tools_test',
    'system',
    'field',
    'text',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install the necessary entity schemas.
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // Set up the dependencies.
    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Create an admin user with a role that bypasses all access checks.
    $this->adminUser = $this->createUser([], 'admin', TRUE);

    // Create an article content type. This must exist before the editor role
    // is saved, otherwise the dynamic 'create/edit article content'
    // permissions are not yet defined and would be dropped on role save.
    $this->entityTypeManager->getStorage('node_type')->create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'An article content type for testing.',
    ])->save();

    $field_storage = $this->entityTypeManager->getStorage('field_storage_config');
    $field_instance = $this->entityTypeManager->getStorage('field_config');

    // A plain, editable string field used to exercise the happy write path.
    $field_storage->create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    $field_instance->create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Text',
      'required' => FALSE,
    ])->save();

    // A secret field that the test module forbids editing access to, so the
    // per-field write access check can be exercised.
    $field_storage->create([
      'field_name' => 'field_secret',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    $field_instance->create([
      'field_name' => 'field_secret',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Secret',
      'required' => FALSE,
    ])->save();

    // Content editor: has entity-level create/edit access but is still subject
    // to field-level access checks. The article node type already exists, so
    // the dynamic node permissions are defined when the role is saved.
    $this->contentEditor = $this->createUser([
      'access content',
      'create article content',
      'edit any article content',
    ], 'content_editor');

    // Low privilege user: no create or edit permissions, to exercise the
    // entity-level access check.
    $this->lowPrivUser = $this->createUser([], 'low_priv');

    // Create an existing article to exercise the edit path. The secret value is
    // set programmatically, which bypasses field access.
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'article',
      'title' => 'Existing Article',
      'field_text' => 'Original text.',
      'field_secret' => 'Original secret.',
      'status' => 1,
    ]);
    $node->save();
    $this->articleOne = $node->id();
  }

  /**
   * Helper to build a single entity_array field item.
   *
   * @param string $field_name
   *   The field machine name.
   * @param string $value
   *   The value to set on the field's 'value' property.
   *
   * @return array
   *   A single entity_array list item.
   */
  protected function fieldItem(string $field_name, string $value): array {
    return [
      'field_name' => $field_name,
      'field_values' => [
        [
          'value_name' => 'value',
          'values' => [$value],
        ],
      ],
    ];
  }

  /**
   * Tests creating an entity as the super user (happy path).
   */
  public function testCreateEntityAsAdmin(): void {
    $this->setCurrentUser($this->adminUser);

    $tool = $this->functionCallManager->createInstance('ai_agent:content_entity_seeder');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('label', 'A brand new article');
    $tool->setContextValue('entity_array', [
      $this->fieldItem('field_text', 'Some new text.'),
    ]);

    $tool->execute();
    $result = $tool->getReadableOutput();

    $this->assertIsString($result);
    $this->assertStringContainsString('created', $result);

    // The new article should exist with the supplied values.
    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['title' => 'A brand new article']);
    $this->assertCount(1, $nodes);
    $node = reset($nodes);
    $this->assertSame('Some new text.', $node->get('field_text')->value);
  }

  /**
   * Tests editing an entity as the super user (happy path).
   */
  public function testEditEntityAsAdmin(): void {
    $this->setCurrentUser($this->adminUser);

    $tool = $this->functionCallManager->createInstance('ai_agent:content_entity_seeder');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('entity_id', $this->articleOne);
    $tool->setContextValue('entity_array', [
      $this->fieldItem('field_text', 'Edited text.'),
    ]);

    $tool->execute();
    $result = $tool->getReadableOutput();

    $this->assertIsString($result);
    $this->assertStringContainsString('edited', $result);

    $node = $this->reloadArticle();
    $this->assertSame('Edited text.', $node->get('field_text')->value);
  }

  /**
   * Tests that a content editor can write fields they are allowed to edit.
   *
   * This proves the per-field guard is field level and not a blanket denial:
   * the same user that is blocked from field_secret can still write field_text.
   */
  public function testEditAllowedFieldAsContentEditor(): void {
    $this->setCurrentUser($this->contentEditor);

    $tool = $this->functionCallManager->createInstance('ai_agent:content_entity_seeder');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('entity_id', $this->articleOne);
    $tool->setContextValue('entity_array', [
      $this->fieldItem('field_text', 'Editor updated text.'),
    ]);

    $tool->execute();
    $result = $tool->getReadableOutput();

    $this->assertIsString($result);
    $this->assertStringContainsString('edited', $result);

    $node = $this->reloadArticle();
    $this->assertSame('Editor updated text.', $node->get('field_text')->value);
  }

  /**
   * Tests that field level edit access is enforced on the create path.
   *
   * The content editor has entity level 'create' access, but the test module
   * forbids editing field_secret, so the write must be denied and nothing
   * should be persisted.
   */
  public function testCreateEntityFieldAccessDenied(): void {
    $this->setCurrentUser($this->contentEditor);

    $tool = $this->functionCallManager->createInstance('ai_agent:content_entity_seeder');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('label', 'Injected article');
    $tool->setContextValue('entity_array', [
      $this->fieldItem('field_secret', 'Injected secret.'),
    ]);

    $tool->execute();
    $result = $tool->getReadableOutput();

    // The write is rejected at field level, even though entity creation is
    // allowed for this user.
    $this->assertSame('Access denied to field: field_secret', $result);

    // Nothing should have been persisted.
    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['title' => 'Injected article']);
    $this->assertCount(0, $nodes);
  }

  /**
   * Tests that field level edit access is enforced on the edit path.
   *
   * The content editor has entity level 'update' access, but must not be able
   * to overwrite field_secret, and the stored value must remain unchanged.
   */
  public function testEditEntityFieldAccessDenied(): void {
    $this->setCurrentUser($this->contentEditor);

    $tool = $this->functionCallManager->createInstance('ai_agent:content_entity_seeder');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('entity_id', $this->articleOne);
    $tool->setContextValue('entity_array', [
      $this->fieldItem('field_secret', 'Hacked secret.'),
    ]);

    $tool->execute();
    $result = $tool->getReadableOutput();

    $this->assertSame('Access denied to field: field_secret', $result);

    // The protected field must be untouched.
    $node = $this->reloadArticle();
    $this->assertSame('Original secret.', $node->get('field_secret')->value);
  }

  /**
   * Tests that a mixed update is rejected wholesale when one field is denied.
   */
  public function testEditEntityMixedFieldAccessDenied(): void {
    $this->setCurrentUser($this->contentEditor);

    $tool = $this->functionCallManager->createInstance('ai_agent:content_entity_seeder');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('entity_id', $this->articleOne);
    // field_text is intentionally listed before the forbidden field_secret.
    $tool->setContextValue('entity_array', [
      $this->fieldItem('field_text', 'Smuggled text.'),
      $this->fieldItem('field_secret', 'Hacked secret.'),
    ]);

    $tool->execute();
    $result = $tool->getReadableOutput();
    $this->assertSame('Access denied to field: field_secret', $result);
    // Neither field may change: the allowed field must NOT be partially applied
    // just because it was processed before the denial.
    $node = $this->reloadArticle();
    $this->assertSame('Original text.', $node->get('field_text')->value);
    $this->assertSame('Original secret.', $node->get('field_secret')->value);
  }

  /**
   * Tests that entity level access is still enforced before any field write.
   */
  public function testCreateEntityEntityLevelAccessDenied(): void {
    $this->setCurrentUser($this->lowPrivUser);

    $tool = $this->functionCallManager->createInstance('ai_agent:content_entity_seeder');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('entity_type', 'node');
    $tool->setContextValue('bundle', 'article');
    $tool->setContextValue('label', 'Unauthorized article');
    $tool->setContextValue('entity_array', [
      $this->fieldItem('field_text', 'Should not be saved.'),
    ]);

    $tool->execute();
    $result = $tool->getReadableOutput();

    $this->assertSame('Access denied.', $result);

    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['title' => 'Unauthorized article']);
    $this->assertCount(0, $nodes);
  }

  /**
   * Reloads the existing article from storage, bypassing the static cache.
   *
   * @return \Drupal\node\NodeInterface
   *   The freshly loaded article.
   */
  protected function reloadArticle() {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$this->articleOne]);
    return $storage->load($this->articleOne);
  }

}
