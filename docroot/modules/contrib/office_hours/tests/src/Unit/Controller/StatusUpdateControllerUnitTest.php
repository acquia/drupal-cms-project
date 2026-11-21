<?php

declare(strict_types=1);

namespace Drupal\Tests\office_hours\Unit\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\office_hours\Controller\StatusUpdateController;
use Drupal\office_hours\Plugin\Field\FieldType\OfficeHoursItemListInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Unit tests for StatusUpdateController.
 *
 * @coversDefaultClass \Drupal\office_hours\Controller\StatusUpdateController
 * @group office_hours
 */
class StatusUpdateControllerUnitTest extends UnitTestCase {

  /**
   * The controller under test.
   *
   * @var \Drupal\office_hours\Controller\StatusUpdateController
   */
  protected StatusUpdateController $controller;

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The renderer mock.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->renderer = $this->createMock(RendererInterface::class);

    $this->controller = new StatusUpdateController(
      $this->entityTypeManager,
      $this->renderer
    );
  }

  /**
   * Tests the create method.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('renderer', $this->renderer);

    $controller = StatusUpdateController::create($container);
    $this->assertInstanceOf(StatusUpdateController::class, $controller);
  }

  /**
   * Tests the constructor method.
   *
   * @covers ::__construct
   */
  public function testConstruct(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $renderer = $this->createMock(RendererInterface::class);

    $controller = new StatusUpdateController($entityTypeManager, $renderer);

    // Use reflection to access protected properties and verify they are set correctly.
    $reflection = new \ReflectionClass($controller);

    $entityTypeManagerProperty = $reflection->getProperty('entityTypeManager');
    $entityTypeManagerProperty->setAccessible(TRUE);
    $this->assertSame($entityTypeManager, $entityTypeManagerProperty->getValue($controller));

    $rendererProperty = $reflection->getProperty('renderer');
    $rendererProperty->setAccessible(TRUE);
    $this->assertSame($renderer, $rendererProperty->getValue($controller));
  }

  /**
   * Tests successful status update.
   *
   * @covers ::updateStatus
   */
  public function testUpdateStatusSuccess(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('isTranslatable')->willReturn(FALSE);
    $entity->method('access')->with('view')->willReturn(TRUE);
    $entity->method('hasField')->with('field_office_hours')->willReturn(TRUE);

    $items = $this->createMock(OfficeHoursItemListInterface::class);
    $renderable = ['#markup' => 'Office Hours Content'];
    $items->method('view')->with('default')->willReturn($renderable);

    $entity->method('get')->with('field_office_hours')->willReturn($items);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('123')->willReturn($entity);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $this->renderer->method('render')->with($renderable)->willReturn('Rendered Office Hours');

    $response = $this->controller->updateStatus('node', '123', 'field_office_hours', 'en', 'default');

    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals('Rendered Office Hours', $response->getContent());
  }

  /**
   * Tests status update with translatable entity.
   *
   * @covers ::updateStatus
   */
  public function testUpdateStatusWithTranslation(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $translatedEntity = $this->createMock(ContentEntityInterface::class);

    $entity->method('isTranslatable')->willReturn(TRUE);
    $entity->method('hasTranslation')->with('fr')->willReturn(TRUE);
    $entity->method('getTranslation')->with('fr')->willReturn($translatedEntity);

    $translatedEntity->method('access')->with('view')->willReturn(TRUE);
    $translatedEntity->method('hasField')->with('field_office_hours')->willReturn(TRUE);

    $items = $this->createMock(OfficeHoursItemListInterface::class);
    $renderable = ['#markup' => 'Translated Office Hours'];
    $items->method('view')->with('full')->willReturn($renderable);

    $translatedEntity->method('get')->with('field_office_hours')->willReturn($items);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('456')->willReturn($entity);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $this->renderer->method('render')->with($renderable)->willReturn('Rendered Translated Office Hours');

    $response = $this->controller->updateStatus('node', '456', 'field_office_hours', 'fr', 'full');

    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals('Rendered Translated Office Hours', $response->getContent());
  }

  /**
   * Tests exception when entity type manager fails.
   *
   * @covers ::updateStatus
   */
  public function testUpdateStatusEntityTypeManagerException(): void {
    $this->entityTypeManager->method('getStorage')
      ->with('invalid_type')
      ->willThrowException(new \Exception('Invalid entity type'));

    $this->expectException(BadRequestHttpException::class);
    $this->controller->updateStatus('invalid_type', '123', 'field_office_hours', 'en', 'default');
  }

  /**
   * Tests exception when entity not found.
   *
   * @covers ::updateStatus
   */
  public function testUpdateStatusEntityNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('999')->willReturn(NULL);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $this->expectException(NotFoundHttpException::class);
    $this->controller->updateStatus('node', '999', 'field_office_hours', 'en', 'default');
  }

  /**
   * Tests exception when entity access denied.
   *
   * @covers ::updateStatus
   */
  public function testUpdateStatusAccessDenied(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('isTranslatable')->willReturn(FALSE);
    $entity->method('access')->with('view')->willReturn(FALSE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('123')->willReturn($entity);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $this->expectException(AccessDeniedHttpException::class);
    $this->controller->updateStatus('node', '123', 'field_office_hours', 'en', 'default');
  }

  /**
   * Tests exception when field not found.
   *
   * @covers ::updateStatus
   */
  public function testUpdateStatusFieldNotFound(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('isTranslatable')->willReturn(FALSE);
    $entity->method('access')->with('view')->willReturn(TRUE);
    $entity->method('hasField')->with('nonexistent_field')->willReturn(FALSE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('123')->willReturn($entity);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $this->expectException(NotFoundHttpException::class);
    $this->controller->updateStatus('node', '123', 'nonexistent_field', 'en', 'default');
  }

  /**
   * Tests exception when field is not OfficeHoursItemListInterface.
   *
   * @covers ::updateStatus
   */
  public function testUpdateStatusInvalidFieldType(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('isTranslatable')->willReturn(FALSE);
    $entity->method('access')->with('view')->willReturn(TRUE);
    $entity->method('hasField')->with('field_office_hours')->willReturn(TRUE);

    $invalidItems = $this->createMock(FieldItemListInterface::class);
    $entity->method('get')->with('field_office_hours')->willReturn($invalidItems);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('123')->willReturn($entity);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $this->expectException(AccessDeniedHttpException::class);
    $this->controller->updateStatus('node', '123', 'field_office_hours', 'en', 'default');
  }

  /**
   * Tests the attachStatusUpdate method for anonymous users.
   *
   * @covers ::attachStatusUpdate
   */
  public function testAttachStatusUpdateAnonymousUser(): void {
    $this->setUpContainerForAttachStatusUpdate();

    $items = $this->createMock(OfficeHoursItemListInterface::class);
    $parentEntity = $this->createMock(ContentEntityInterface::class);
    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);

    $items->method('getParent')->willReturn($this->createMock(FieldItemList::class));
    $items->getParent()->method('getEntity')->willReturn($parentEntity);
    $items->method('getFieldDefinition')->willReturn($fieldDefinition);

    $parentEntity->method('getEntityTypeId')->willReturn('node');
    $parentEntity->method('id')->willReturn('123');
    $fieldDefinition->method('getName')->willReturn('field_office_hours');

    $elements = ['#markup' => 'Test content'];
    $thirdPartySettings = [];

    $result = StatusUpdateController::attachStatusUpdate($items, 'en', 'default', $thirdPartySettings, $elements);

    $this->assertArrayHasKey('#attached', $result);
    $this->assertArrayHasKey('library', $result['#attached']);
    $this->assertContains('office_hours/office_hours_formatter_status_update', $result['#attached']['library']);
    $this->assertArrayHasKey('#attributes', $result);
    $this->assertArrayHasKey('js-office-hours-status-data', $result['#attributes']);
  }

  /**
   * Tests the attachStatusUpdate method for authenticated users.
   *
   * @covers ::attachStatusUpdate
   */
  public function testAttachStatusUpdateAuthenticatedUser(): void {
    $this->setUpContainerForAttachStatusUpdate(TRUE);

    $items = $this->createMock(OfficeHoursItemListInterface::class);
    $elements = ['#markup' => 'Test content'];
    $thirdPartySettings = [];

    $result = StatusUpdateController::attachStatusUpdate($items, 'en', 'default', $thirdPartySettings, $elements);

    $this->assertEquals($elements, $result);
  }

  /**
   * Tests the attachStatusUpdate method when page_cache module is disabled.
   *
   * @covers ::attachStatusUpdate
   */
  public function testAttachStatusUpdatePageCacheDisabled(): void {
    $this->setUpContainerForAttachStatusUpdate(FALSE, FALSE);

    $items = $this->createMock(OfficeHoursItemListInterface::class);
    $elements = ['#markup' => 'Test content'];
    $thirdPartySettings = [];

    $result = StatusUpdateController::attachStatusUpdate($items, 'en', 'default', $thirdPartySettings, $elements);

    $this->assertEquals($elements, $result);
  }

  /**
   * Tests the attachStatusUpdate method with layout_builder settings.
   *
   * @covers ::attachStatusUpdate
   */
  public function testAttachStatusUpdateWithLayoutBuilder(): void {
    $this->setUpContainerForAttachStatusUpdate();

    $items = $this->createMock(OfficeHoursItemListInterface::class);
    $elements = ['#markup' => 'Test content'];
    $thirdPartySettings = [
      'layout_builder' => [
        'view_mode' => 'full',
      ],
    ];

    $result = StatusUpdateController::attachStatusUpdate($items, 'en', 'default', $thirdPartySettings, $elements);

    $this->assertEquals($elements, $result);
  }

  /**
   * Sets up the container for attachStatusUpdate tests.
   *
   * @param bool $isAuthenticated
   *   Whether the user is authenticated.
   * @param bool $pageCacheEnabled
   *   Whether the page_cache module is enabled.
   */
  protected function setUpContainerForAttachStatusUpdate(bool $isAuthenticated = FALSE, bool $pageCacheEnabled = TRUE): void {
    $container = new ContainerBuilder();

    $currentUser = $this->createMock(AccountInterface::class);
    $currentUser->method('isAnonymous')->willReturn(!$isAuthenticated);
    $container->set('current_user', $currentUser);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('page_cache')->willReturn($pageCacheEnabled);
    $container->set('module_handler', $moduleHandler);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1234567890);
    $container->set('datetime.time', $time);

    \Drupal::setContainer($container);
  }

}
