<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Service\DependencyCalculation;
use Drupal\eca\Token\TokenInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the DependencyCalculation service.
 */
#[Group('eca')]
#[Group('eca_core')]
class DependencyCalculationTest extends EcaUnitTestBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The token service.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $token;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The dependency calculation service under test.
   *
   * @var \Drupal\eca\Service\DependencyCalculation
   */
  protected DependencyCalculation $dependencyCalculation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeBundleInfo = $this->createStub(EntityTypeBundleInfoInterface::class);
    $this->entityFieldManager = $this->createStub(EntityFieldManagerInterface::class);
    $this->token = $this->createStub(TokenInterface::class);

    // Set up config factory to enable bundle dependency calculation.
    $config = $this->createStub(ImmutableConfig::class);
    $config->method('get')
      ->with('dependency_calculation')
      ->willReturn(['bundle', 'field_storage', 'field_config']);
    $this->configFactory = $this->createStub(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('eca.settings')
      ->willReturn($config);

    $this->dependencyCalculation = new DependencyCalculation(
      $this->entityTypeManager,
      $this->entityTypeBundleInfo,
      $this->entityFieldManager,
      $this->token,
      $this->configFactory,
    );

    // Reset the static property for enabled calculations between tests.
    $reflection = new \ReflectionClass(DependencyCalculation::class);
    $prop = $reflection->getProperty('enabledCalculations');
    $prop->setValue(NULL, NULL);
  }

  /**
   * Tests that field names containing "type" trigger entity type detection.
   *
   * The method addDependenciesFromFields() should detect field names like
   * "entity_type", "node_type", or "content_type" as type-related fields
   * and correctly parse the value as "entity_type_id [bundle]".
   *
   * This test verifies the fix for the reversed mb_strpos() arguments at
   * line 182 of DependencyCalculation.php. The original code had:
   *   mb_strpos('type', $name)
   * which searched for $name inside 'type', instead of:
   *   mb_strpos($name, 'type')
   * which searches for 'type' inside $name.
   */
  public function testEntityTypeFieldDependencyDetection(): void {
    // Set up the token service to map 'node' to 'node' entity type.
    $this->token->method('getEntityTypeForTokenType')
      ->willReturnCallback(function (string $token_type): ?string {
        return $token_type === 'node' ? 'node' : NULL;
      });

    // Set up entity type manager to know about 'node'.
    $entityType = $this->createStub(EntityTypeInterface::class);
    $entityType->method('getBundleConfigDependency')
      ->with('article')
      ->willReturn([
        'type' => 'config',
        'name' => 'node.type.article',
      ]);
    $this->entityTypeManager->method('hasDefinition')
      ->willReturnCallback(function (string $entity_type_id): bool {
        return $entity_type_id === 'node';
      });
    $this->entityTypeManager->method('getDefinition')
      ->with('node')
      ->willReturn($entityType);

    // The entity type for 'node' does implement FieldableEntityInterface.
    $entityType->method('entityClassImplements')
      ->willReturn(TRUE);
    $this->entityFieldManager->method('getFieldStorageDefinitions')
      ->willReturn([]);

    // Create a stub ECA entity with configuration that has an "entity_type"
    // field set to "node article" (entity type + bundle).
    $eca = $this->createStub(Eca::class);
    $eca->method('get')
      ->willReturnCallback(function (string $property): mixed {
        if ($property === 'events') {
          return [
            'event_1' => [
              'plugin' => 'some_event',
              'configuration' => [
                'entity_type' => 'node article',
              ],
              'successors' => [],
            ],
          ];
        }
        return [];
      });

    $dependencies = $this->dependencyCalculation->calculateDependencies($eca);

    // With the bug, 'entity_type' does NOT match the type check because
    // mb_strpos('type', 'entity_type') returns FALSE (searching for
    // 'entity_type' in 'type'). The fix should detect 'entity_type' as a
    // type field and parse the value as 'node article' -> entity type 'node',
    // bundle 'article'. This should produce a bundle config dependency.
    $this->assertArrayHasKey('config', $dependencies, 'Dependencies should include config entries for entity type bundle references.');
    $this->assertContains('node.type.article', $dependencies['config'], 'The bundle config dependency for node.type.article should be detected.');
  }

  /**
   * Tests that the literal field name "type" still works correctly.
   *
   * Even with the bug, a field literally named "type" would match because
   * mb_strpos('type', 'type') returns 0. This test ensures the fix does not
   * break that existing behavior.
   */
  public function testLiteralTypeFieldStillWorks(): void {
    // Set up the token service to map 'node' to 'node' entity type.
    $this->token->method('getEntityTypeForTokenType')
      ->willReturnCallback(function (string $token_type): ?string {
        return $token_type === 'node' ? 'node' : NULL;
      });

    // Set up entity type manager to know about 'node'.
    $entityType = $this->createStub(EntityTypeInterface::class);
    $entityType->method('getBundleConfigDependency')
      ->with('page')
      ->willReturn([
        'type' => 'config',
        'name' => 'node.type.page',
      ]);
    $this->entityTypeManager->method('hasDefinition')
      ->willReturnCallback(function (string $entity_type_id): bool {
        return $entity_type_id === 'node';
      });
    $this->entityTypeManager->method('getDefinition')
      ->with('node')
      ->willReturn($entityType);
    $entityType->method('entityClassImplements')
      ->willReturn(TRUE);
    $this->entityFieldManager->method('getFieldStorageDefinitions')
      ->willReturn([]);

    // Create a stub ECA entity with a field literally named "type".
    $eca = $this->createStub(Eca::class);
    $eca->method('get')
      ->willReturnCallback(function (string $property): mixed {
        if ($property === 'events') {
          return [
            'event_1' => [
              'plugin' => 'some_event',
              'configuration' => [
                'type' => 'node page',
              ],
              'successors' => [],
            ],
          ];
        }
        return [];
      });

    $dependencies = $this->dependencyCalculation->calculateDependencies($eca);

    $this->assertArrayHasKey('config', $dependencies, 'Dependencies should include config entries for type field.');
    $this->assertContains('node.type.page', $dependencies['config'], 'The bundle config dependency for node.type.page should be detected.');
  }

  /**
   * Tests that field names without "type" do not trigger type detection.
   *
   * Field names like "some_field" that do not contain "type" should go through
   * the regular variable/token parsing path, not the entity type detection.
   */
  public function testNonTypeFieldNameSkipsTypeDetection(): void {
    // The token service maps 'node' to 'node' entity type.
    $this->token->method('getEntityTypeForTokenType')
      ->willReturn(NULL);

    $this->entityTypeManager->method('hasDefinition')
      ->willReturn(FALSE);

    // Create a stub ECA entity with a field that does NOT contain "type".
    $eca = $this->createStub(Eca::class);
    $eca->method('get')
      ->willReturnCallback(function (string $property): mixed {
        if ($property === 'events') {
          return [
            'event_1' => [
              'plugin' => 'some_event',
              'configuration' => [
                'some_field' => 'some_value',
              ],
              'successors' => [],
            ],
          ];
        }
        return [];
      });

    $dependencies = $this->dependencyCalculation->calculateDependencies($eca);

    // No config dependencies should be found for non-type fields with
    // non-entity values.
    $this->assertEmpty($dependencies, 'No dependencies should be calculated for fields without type references.');
  }

}
