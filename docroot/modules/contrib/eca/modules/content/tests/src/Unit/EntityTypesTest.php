<?php

namespace Drupal\Tests\eca_content\Unit;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eca\Service\ContentEntityTypes;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the entity type trait.
 */
#[Group('eca')]
#[Group('eca_content')]
class EntityTypesTest extends TestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle info mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createStub(EntityTypeManagerInterface::class);
    $this->entityTypeBundleInfo = $this->createStub(EntityTypeBundleInfoInterface::class);
  }

  /**
   * Tests the method getTypesAndBundles without content entity types.
   */
  public function testGetTypesAndBundlesWithoutTypes(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->once())
      ->method('getDefinitions')->willReturn([]);
    $entityTypeHelper = new ContentEntityTypes($entityTypeManager, $this->entityTypeBundleInfo);
    $this->assertEquals([], $entityTypeHelper->getTypesAndBundles());
  }

  /**
   * Tests getTypesAndBundles without content entity types and include any.
   */
  public function testGetTypesAndBundlesWithoutTypesIncludeAny(): void {
    $expected = [
      ContentEntityTypes::ALL => '- any -',
    ];
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->once())
      ->method('getDefinitions')->willReturn([]);
    $entityTypeHelper = new ContentEntityTypes($entityTypeManager, $this->entityTypeBundleInfo);
    $this->assertEquals($expected, $entityTypeHelper->getTypesAndBundles(TRUE));
  }

  /**
   * Tests getTypesAndBundles with entity types and include any bundles.
   */
  public function testGetTypesAndBundlesWithTypesIncludeAnyBundles(): void {
    $expected = [
      'Comment ' . ContentEntityTypes::ALL => 'Comment: - any -',
      'Comment bundleKey2' => 'Comment: Article',
      'Comment bundleKey1' => 'Comment: Node',
      'Content ' . ContentEntityTypes::ALL => 'Content: - any -',
      'Content bundleKey2' => 'Content: Article',
      'Content bundleKey1' => 'Content: Node',
    ];
    $labels = [
      'Content' => 'Content',
      'Comment' => 'Comment',
    ];

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn($this->getContentEntityTypesByLabels($labels));
    $entityTypeHelper = new ContentEntityTypes($entityTypeManager, $this->entityTypeBundleInfo);
    $this->assertEquals($expected, $entityTypeHelper->getTypesAndBundles());
  }

  /**
   * Tests the method getTypesAndBundles.
   *
   * <p>Include content entity types and without the flag any bundles.</p>
   */
  public function testGetTypesAndBundlesWithoutAnyBundlesFlag(): void {
    $expected = [
      'Comment bundleKey2' => 'Comment: Article',
      'Comment bundleKey1' => 'Comment: Node',
      'Content bundleKey2' => 'Content: Article',
      'Content bundleKey1' => 'Content: Node',
    ];
    $labels = [
      'Content' => 'Content',
      'Comment' => 'Comment',
    ];

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn($this->getContentEntityTypesByLabels($labels));
    $entityTypeHelper = new ContentEntityTypes($entityTypeManager, $this->entityTypeBundleInfo);
    $this->assertEquals($expected, $entityTypeHelper->getTypesAndBundles(FALSE, FALSE));
  }

  /**
   * Tests the method getTypesAndBundles.
   *
   * <p>Include content entity types and without the flag any bundles.</p>
   */
  public function testGetTypesAndBundlesNoBundles(): void {
    $expected = [
      'Comment ' . ContentEntityTypes::ALL => 'Comment: - any -',
      'Content ' . ContentEntityTypes::ALL => 'Content: - any -',
    ];
    $labels = [
      'Content' => 'Content',
      'Comment' => 'Comment',
    ];

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn($this->getContentEntityTypesByLabels($labels, FALSE));
    $entityTypeHelper = new ContentEntityTypes($entityTypeManager, $this->entityTypeBundleInfo);
    $this->assertEquals($expected, $entityTypeHelper->getTypesAndBundles());
  }

  /**
   * Gets the content types.
   *
   * @param array $labels
   *   The labels.
   * @param bool $includeBundleKey
   *   The key.
   *
   * @return array
   *   The content entity types.
   */
  private function getContentEntityTypesByLabels(array $labels, bool $includeBundleKey = TRUE): array {
    $bundles = [
      'bundleKey1' => [
        'label' => 'Node',
      ],
      'bundleKey2' => [
        'label' => 'Article',
      ],
    ];
    $this->entityTypeBundleInfo->method('getBundleInfo')
      ->willReturn($bundles);

    $entityTypes = [];
    foreach ($labels as $key => $label) {
      $entityType = $this->createStub(ContentEntityTypeInterface::class);
      $entityType->method('id')->willReturn($key);
      $entityType->method('getLabel')->willReturn($label);
      $entityKeys = [];
      if ($includeBundleKey) {
        $entityKeys = ['bundle' => 'test'];
      }
      $entityType->method('get')
        ->willReturn($entityKeys);

      $entityTypes[] = $entityType;
    }
    return $entityTypes;
  }

  /**
   * Tests method with all types.
   */
  public function testBundleFieldAppliesAllTypes(): void {
    $entityTypeHelper = new ContentEntityTypes($this->entityTypeManager, $this->entityTypeBundleInfo);
    $entityMock = $this->createStub(EntityInterface::class);
    $entityMock->method('getEntityTypeId')->willReturn('node');
    $entityMock->method('bundle')->willReturn('article');
    $this->assertTrue($entityTypeHelper->bundleFieldApplies(
      $entityMock,
      ContentEntityTypes::ALL));
  }

  /**
   * Tests method with all bundles.
   */
  public function testBundleFieldAppliesAllBundle(): void {
    $entityTypeHelper = new ContentEntityTypes($this->entityTypeManager, $this->entityTypeBundleInfo);
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())->method('getEntityTypeId')
      ->willReturn('test');
    $entity->method('bundle')->willReturn('testbundle');
    $this->assertTrue($entityTypeHelper->bundleFieldApplies($entity, 'test ' . ContentEntityTypes::ALL));
  }

  /**
   * Tests method with equal types and bundle.
   */
  public function testBundleFieldApplies(): void {
    $entityTypeHelper = new ContentEntityTypes($this->entityTypeManager, $this->entityTypeBundleInfo);
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())->method('getEntityTypeId')
      ->willReturn('test_id');
    $entity->expects($this->once())->method('bundle')
      ->willReturn('test_bundle');
    $this->assertTrue($entityTypeHelper->bundleFieldApplies($entity, 'test_id test_bundle'));
  }

  /**
   * Tests method with non-equal bundle.
   */
  public function testBundleFieldAppliesFalse(): void {
    $entityTypeHelper = new ContentEntityTypes($this->entityTypeManager, $this->entityTypeBundleInfo);
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())->method('getEntityTypeId')
      ->willReturn('test_id');
    $entity->expects($this->once())->method('bundle')
      ->willReturn('bundle');
    $this->assertFalse($entityTypeHelper->bundleFieldApplies($entity, 'test_id test_bundle'));
  }

}
