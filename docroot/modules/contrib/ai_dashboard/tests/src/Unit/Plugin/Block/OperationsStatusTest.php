<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Unit\Plugin\Block;

use Drupal\ai\AiProviderInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai_dashboard\AiSetupStatusInterface;
use Drupal\ai_dashboard\Plugin\Block\OperationsStatus;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleExtensionList;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests OperationsStatus::checkOperationTypeStatus().
 *
 * @group ai_dashboard
 * @coversDefaultClass \Drupal\ai_dashboard\Plugin\Block\OperationsStatus
 */
class OperationsStatusTest extends TestCase {

  /**
   * The block plugin under test.
   *
   * @var \Drupal\ai_dashboard\Plugin\Block\OperationsStatus
   */
  protected OperationsStatus $block;

  /**
   * The mocked logger channel, used to assert warnings are logged.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerInterface $logger;

  /**
   * Reflection handle for the protected checkOperationTypeStatus() method.
   *
   * @var \ReflectionMethod
   */
  protected \ReflectionMethod $checkOperationTypeStatus;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config = $this->createMock(ImmutableConfig::class);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->willReturn($config);

    // OperationsStatus::checkOperationTypeStatus() no longer touches the AI
    // Provider plugin manager directly (see build()), so an uninitialized
    // instance is enough to satisfy the constructor's typed property.
    // AiProviderPluginManager is a final class and cannot be mocked.
    $provider_manager = (new \ReflectionClass(AiProviderPluginManager::class))
      ->newInstanceWithoutConstructor();

    $module_list = $this->createMock(ModuleExtensionList::class);
    $setup_status = $this->createMock(AiSetupStatusInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->block = new OperationsStatus(
      [],
      'ai_operations_status',
      ['provider' => 'ai_dashboard'],
      $config_factory,
      $provider_manager,
      $module_list,
      $setup_status,
      $this->logger
    );

    $this->checkOperationTypeStatus = new \ReflectionMethod($this->block, 'checkOperationTypeStatus');
  }

  /**
   * Sets the block's real operation types, normally populated in build().
   */
  protected function setRealOperationTypes(array $operation_types): void {
    $property = new \ReflectionProperty($this->block, 'realOperationTypes');
    $property->setValue($this->block, $operation_types);
  }

  /**
   * Invokes the protected method under test.
   */
  protected function invokeCheckOperationTypeStatus(AiProviderInterface $provider, array $operation, array $operation_types = []): bool {
    $this->setRealOperationTypes($operation_types);
    return $this->checkOperationTypeStatus->invoke($this->block, $provider, $operation);
  }

  /**
   * @covers ::checkOperationTypeStatus
   */
  public function testCapabilityFilteredTypeSupportedWhenModelHasCapability(): void {
    $provider = $this->createMock(AiProviderInterface::class);
    $provider->method('isUsable')->willReturn(TRUE);
    $provider->expects($this->once())
      ->method('getConfiguredModels')
      ->with('chat', [AiModelCapability::ChatWithImageVision])
      ->willReturn(['gpt-4o' => 'GPT-4o']);
    // Generic operation-type support must not short-circuit a
    // capability-filtered pseudo type: being able to chat does not imply
    // vision support.
    $provider->expects($this->never())->method('getSupportedOperationTypes');
    $provider->expects($this->never())->method('getSetupData');

    $operation = [
      'id' => 'chat_with_image_vision',
      'actual_type' => 'chat',
      'filter' => [AiModelCapability::ChatWithImageVision],
    ];

    $this->assertTrue($this->invokeCheckOperationTypeStatus($provider, $operation));
  }

  /**
   * @covers ::checkOperationTypeStatus
   */
  public function testCapabilityFilteredTypeNotSupportedWithoutMatchingModel(): void {
    $provider = $this->createMock(AiProviderInterface::class);
    $provider->method('isUsable')->willReturn(TRUE);
    $provider->expects($this->once())
      ->method('getConfiguredModels')
      ->with('chat', [AiModelCapability::ChatTools])
      ->willReturn([]);

    $operation = [
      'id' => 'chat_with_tools',
      'actual_type' => 'chat',
      'filter' => [AiModelCapability::ChatTools],
    ];

    $this->assertFalse($this->invokeCheckOperationTypeStatus($provider, $operation));
  }

  /**
   * @covers ::checkOperationTypeStatus
   */
  public function testRegularOperationTypeSupportedByProvider(): void {
    $provider = $this->createMock(AiProviderInterface::class);
    $provider->method('getSupportedOperationTypes')->willReturn(['chat', 'embeddings']);
    $provider->expects($this->never())->method('getConfiguredModels');

    $operation = ['id' => 'chat', 'actual_type' => 'chat', 'filter' => []];

    $this->assertTrue($this->invokeCheckOperationTypeStatus($provider, $operation));
  }

  /**
   * @covers ::checkOperationTypeStatus
   */
  public function testRegularOperationTypeNotSupportedByPlugin(): void {
    $provider = $this->createMock(AiProviderInterface::class);
    $provider->method('getSupportedOperationTypes')->willReturn(['embeddings']);
    $provider->expects($this->never())->method('getConfiguredModels');
    $provider->expects($this->never())->method('getSetupData');

    $operation = ['id' => 'chat', 'actual_type' => 'chat', 'filter' => []];
    $operation_types = [
      'chat' => $operation,
      'embeddings' => ['id' => 'embeddings', 'actual_type' => 'embeddings', 'filter' => []],
    ];

    $this->assertFalse($this->invokeCheckOperationTypeStatus($provider, $operation, $operation_types));
  }

  /**
   * @covers ::checkOperationTypeStatus
   */
  public function testUnknownOperationTypeSupportedViaDefaultModelsSetupData(): void {
    $provider = $this->createMock(AiProviderInterface::class);
    $provider->method('getSupportedOperationTypes')->willReturn([]);
    $provider->method('getSetupData')->willReturn(['default_models' => ['custom_op' => 'some-model']]);
    $provider->expects($this->never())->method('getConfiguredModels');

    $operation = ['id' => 'custom_op', 'actual_type' => 'custom_op', 'filter' => []];

    $this->assertTrue($this->invokeCheckOperationTypeStatus($provider, $operation, []));
  }

  /**
   * @covers ::checkOperationTypeStatus
   */
  public function testUnknownOperationTypeSupportedViaConfiguredModels(): void {
    $provider = $this->createMock(AiProviderInterface::class);
    $provider->method('getSupportedOperationTypes')->willReturn([]);
    $provider->method('getSetupData')->willReturn([]);
    $provider->method('getConfiguredModels')->with('custom_op')->willReturn(['model-1' => 'Model 1']);

    $operation = ['id' => 'custom_op', 'actual_type' => 'custom_op', 'filter' => []];

    $this->assertTrue($this->invokeCheckOperationTypeStatus($provider, $operation, []));
  }

  /**
   * @covers ::checkOperationTypeStatus
   */
  public function testSetupFailureExceptionIsLoggedAndReturnsFalse(): void {
    $provider = $this->createMock(AiProviderInterface::class);
    $provider->method('getSupportedOperationTypes')->willThrowException(new AiSetupFailureException('not configured'));

    $this->logger->expects($this->once())
      ->method('warning')
      ->with('not configured');

    $operation = ['id' => 'chat', 'actual_type' => 'chat', 'filter' => []];

    $this->assertFalse($this->invokeCheckOperationTypeStatus($provider, $operation));
  }

  /**
   * @covers ::checkOperationTypeStatus
   */
  public function testCapabilityFilteredTypeNotSupportedWhenProviderNotUsable(): void {
    $provider = $this->createMock(AiProviderInterface::class);
    $provider->method('isUsable')->willReturn(FALSE);
    $provider->expects($this->never())->method('getConfiguredModels');

    $operation = [
      'id' => 'chat_with_tools',
      'actual_type' => 'chat',
      'filter' => [AiModelCapability::ChatTools],
    ];

    $this->assertFalse($this->invokeCheckOperationTypeStatus($provider, $operation));
  }

  /**
   * @covers ::checkOperationTypeStatus
   * @covers ::getConfiguredModels
   */
  public function testConfiguredModelsAreMemoizedPerProviderTypeAndCapabilities(): void {
    $provider = $this->createMock(AiProviderInterface::class);
    $provider->method('getPluginId')->willReturn('openai');
    $provider->method('isUsable')->willReturn(TRUE);
    $provider->expects($this->once())
      ->method('getConfiguredModels')
      ->with('chat', [AiModelCapability::ChatTools])
      ->willReturn(['gpt-4o' => 'GPT-4o']);

    $operation = [
      'id' => 'chat_with_tools',
      'actual_type' => 'chat',
      'filter' => [AiModelCapability::ChatTools],
    ];

    $this->assertTrue($this->invokeCheckOperationTypeStatus($provider, $operation));
    $this->assertTrue($this->invokeCheckOperationTypeStatus($provider, $operation));
  }

  /**
   * @covers ::checkOperationTypeStatus
   */
  public function testGenericExceptionFromProviderIsLoggedAndReturnsFalse(): void {
    $provider = $this->createMock(AiProviderInterface::class);
    $provider->method('isUsable')->willReturn(TRUE);
    $provider->method('getConfiguredModels')->willThrowException(new \RuntimeException('remote down'));

    $this->logger->expects($this->once())
      ->method('warning')
      ->with('remote down');

    $operation = [
      'id' => 'chat_with_tools',
      'actual_type' => 'chat',
      'filter' => [AiModelCapability::ChatTools],
    ];

    $this->assertFalse($this->invokeCheckOperationTypeStatus($provider, $operation));
  }

}
