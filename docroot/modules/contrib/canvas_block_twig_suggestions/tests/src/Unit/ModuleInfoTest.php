<?php

namespace Drupal\Tests\canvas_block_twig_suggestions\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for canvas_block_twig_suggestions module metadata.
 *
 * @group canvas_block_twig_suggestions
 */
class ModuleInfoTest extends TestCase {

  /**
   * Path to the module's .info.yml file.
   */
  private const INFO_FILE = __DIR__ . '/../../../canvas_block_twig_suggestions.info.yml';

  /**
   * Parsed info.yml contents.
   *
   * @var array
   */
  private array $info;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->assertFileExists(self::INFO_FILE);
    $this->info = Yaml::parseFile(self::INFO_FILE);
  }

  /**
   * Test that the module declares itself as type 'module'.
   */
  public function testTypeIsModule(): void {
    $this->assertSame('module', $this->info['type']);
  }

  /**
   * Test that the module requires Drupal 11.
   */
  public function testCoreVersionRequirement(): void {
    $this->assertArrayHasKey('core_version_requirement', $this->info);
    $this->assertStringContainsString('11', $this->info['core_version_requirement']);
  }

  /**
   * Test that the module depends on canvas:canvas.
   */
  public function testDependsOnCanvas(): void {
    $this->assertArrayHasKey('dependencies', $this->info);
    $this->assertContains('canvas:canvas', $this->info['dependencies']);
  }

  /**
   * Test that the module has a name.
   */
  public function testHasName(): void {
    $this->assertArrayHasKey('name', $this->info);
    $this->assertNotEmpty($this->info['name']);
  }

}
