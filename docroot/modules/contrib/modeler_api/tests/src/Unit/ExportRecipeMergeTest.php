<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Unit;

use Drupal\modeler_api\ExportRecipe;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests preservation of hand-crafted recipe metadata during re-export.
 */
#[CoversClass(ExportRecipe::class)]
#[Group('modeler_api')]
class ExportRecipeMergeTest extends UnitTestCase {

  /**
   * The export service exposing its merge helpers for focused unit tests.
   */
  private TestableExportRecipe $exportRecipe;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->exportRecipe = new TestableExportRecipe();
  }

  /**
   * Tests that a first export uses generated metadata unchanged.
   */
  public function testFirstExportUsesGeneratedMetadata(): void {
    $generated = [
      'name' => 'Generated',
      'description' => 'Generated description',
    ];

    $this->assertSame($generated, $this->exportRecipe->testMergeComposer([], $generated));
    $this->assertSame($generated, $this->exportRecipe->testMergeRecipe([], $generated));
  }

  /**
   * Tests that composer identity and dependencies refresh without data loss.
   */
  public function testComposerMergePreservesCuratedMetadata(): void {
    $existing = [
      'name' => 'old/package',
      'type' => 'drupal-recipe',
      'description' => 'Curated summary',
      'license' => 'GPL-2.0-or-later',
      'require' => ['drupal/old' => '*'],
      'extra' => ['installer-name' => 'example'],
    ];
    $generated = [
      'name' => 'new/package',
      'type' => 'drupal-recipe',
      'description' => 'Long generated documentation',
      'license' => 'GPL-2.0-or-later',
      'require' => ['drupal/new' => '*'],
    ];

    $actual = $this->exportRecipe->testMergeComposer($existing, $generated);

    $this->assertSame('new/package', $actual['name']);
    $this->assertSame('Curated summary', $actual['description']);
    $this->assertSame(['drupal/new' => '*'], $actual['require']);
    $this->assertSame(['installer-name' => 'example'], $actual['extra']);
  }

  /**
   * Tests that recipe composition survives while derived values refresh.
   */
  public function testRecipeMergePreservesCompositionMetadata(): void {
    $existing = [
      'name' => 'Old name',
      'description' => 'Curated summary',
      'type' => 'Workflow',
      'recipes' => ['core/recipes/article_tags'],
      'install' => ['old_module'],
      'config' => [
        'strict' => TRUE,
        'import' => ['old_module' => ['old.config']],
        'actions' => [
          'core.entity_form_display.node.article.default' => [
            'setComponents' => [['name' => 'field_summary']],
          ],
          'user.role.editor' => [
            'grantPermissions' => ['old permission'],
          ],
        ],
      ],
    ];
    $generated = [
      'name' => 'New name',
      'description' => 'Long generated documentation',
      'type' => 'Workflow',
      'install' => ['new_module'],
      'config' => [
        'strict' => FALSE,
        'import' => ['new_module' => ['new.config']],
        'actions' => [
          'user.role.editor' => [
            'grantPermissions' => ['new permission'],
          ],
        ],
      ],
    ];

    $actual = $this->exportRecipe->testMergeRecipe($existing, $generated);

    $this->assertSame('New name', $actual['name']);
    $this->assertSame('Curated summary', $actual['description']);
    $this->assertSame(['core/recipes/article_tags'], $actual['recipes']);
    $this->assertSame(['new_module'], $actual['install']);
    $this->assertTrue($actual['config']['strict']);
    $this->assertSame(['new_module' => ['new.config']], $actual['config']['import']);
    $this->assertArrayHasKey('core.entity_form_display.node.article.default', $actual['config']['actions']);
    $this->assertSame(['new permission'], $actual['config']['actions']['user.role.editor']['grantPermissions']);
  }

  /**
   * Tests that stale generated values are removed on re-export.
   */
  public function testRecipeMergeRemovesStaleDerivedValues(): void {
    $existing = [
      'name' => 'Old name',
      'description' => 'Curated summary',
      'type' => 'Workflow',
      'install' => ['old_module'],
      'config' => [
        'strict' => FALSE,
        'import' => ['old_module' => ['old.config']],
        'actions' => [
          'user.role.editor' => ['grantPermissions' => ['old permission']],
        ],
      ],
    ];
    $generated = [
      'name' => 'New name',
      'description' => 'Generated documentation',
      'type' => 'Workflow',
      'config' => ['strict' => FALSE],
    ];

    $actual = $this->exportRecipe->testMergeRecipe($existing, $generated);

    $this->assertArrayNotHasKey('install', $actual);
    $this->assertArrayNotHasKey('import', $actual['config']);
    $this->assertArrayNotHasKey('actions', $actual['config']);
  }

}

/**
 * Test double exposing the recipe metadata merge helpers.
 */
class TestableExportRecipe extends ExportRecipe {

  /**
   * Constructs the test service without dependencies unused by merging.
   */
  public function __construct() {}

  /**
   * Exposes the composer metadata merge helper.
   */
  public function testMergeComposer(array $existing, array $generated): array {
    return $this->mergeComposer($existing, $generated);
  }

  /**
   * Exposes the recipe metadata merge helper.
   */
  public function testMergeRecipe(array $existing, array $generated): array {
    return $this->mergeRecipe($existing, $generated);
  }

}
