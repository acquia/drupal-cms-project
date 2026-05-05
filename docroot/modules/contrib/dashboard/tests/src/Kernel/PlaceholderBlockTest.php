<?php

declare(strict_types=1);

namespace Drupal\Tests\Dashboard\Kernel;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Plugin\PreviewAwarePluginInterface;
use Drupal\dashboard\Plugin\Block\PlaceholderBlock;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the dashboard_placeholder block.
 */
#[Group('dashboard')]
#[CoversClass(PlaceholderBlock::class)]
#[RunTestsInSeparateProcesses]
final class PlaceholderBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dashboard', 'system'];

  /**
   * The test logger to inject into the block manager.
   */
  private TestLogger $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->logger = new TestLogger();
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $container->getDefinition('plugin.manager.block')
      ->setArguments([$this->logger]);
  }

  /**
   * Tests that the block works as expected.
   */
  public function testPlaceholderBlock(): void {
    $block_manager = $this->container->get(BlockManagerInterface::class);
    assert($block_manager instanceof BlockManagerInterface);

    // When we instantiate a placeholder block that decorates a non-existent
    // block, it should not cause any warnings to be logged.
    $block = $block_manager->createInstance('dashboard_placeholder', [
      'decorates' => 'nothing',
    ]);
    $this->assertInstanceOf(PlaceholderBlock::class, $block);
    // Since the decorated block didn't exist, the block should be masquerading
    // as the `broken` block.
    $this->assertSame('broken', $block->getPluginId());
    $this->assertSame([], $block->build());
    $this->assertEmpty($this->logger->records);

    // When decorating the `broken` block, the decorated build()
    // method is never called.
    /** @var \Drupal\Core\Block\BlockPluginInterface&\Drupal\Core\Plugin\PreviewAwarePluginInterface&\PHPUnit\Framework\MockObject\MockObject $decorated */
    $decorated = $this->createMockForIntersectionOfInterfaces([
      BlockPluginInterface::class,
      PreviewAwarePluginInterface::class,
    ]);
    $decorated->expects($this->atLeastOnce())->method('getPluginId')
      ->willReturn('broken');
    $decorated->expects($this->never())->method('build');
    // The decorated block's dependencies should be ignored.
    $decorated->expects($this->never())->method('calculateDependencies');
    // Method calls that aren't in BlockPluginInterface should be passed through
    // to __call().
    $decorated->expects($this->once())->method('setInPreview')->with(TRUE);
    $block = new PlaceholderBlock($decorated);
    $block->setInPreview(TRUE);
    $this->assertSame([], $block->build());
    $this->assertEmpty($block->calculateDependencies());

    // Test decorating an a block that actually exists.
    $block = $block_manager->createInstance('dashboard_placeholder', [
      'decorates' => 'system_powered_by_block',
    ]);
    $this->assertInstanceOf(PlaceholderBlock::class, $block);
    $this->assertSame('system_powered_by_block', $block->getPluginId());
    $this->assertArrayHasKey('#markup', $block->build());
    $this->assertEmpty($this->logger->records);
  }

}
