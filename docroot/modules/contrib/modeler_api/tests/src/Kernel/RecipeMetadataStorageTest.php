<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Kernel;

use Drupal\Core\Config\ConfigValueException;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api_test\Entity\TestModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the recipe metadata settings survive a real config save.
 *
 * The unit tests drive the exporter against a mocked model owner, so nothing
 * there ever saves a config entity. That is how a name-keyed config_actions
 * map reached a release candidate even though config storage rejects a dot in
 * any array key, at any depth, and a config name always contains dots. These
 * tests therefore go through the real accessors and a real save.
 *
 * @see \Drupal\Core\Config\ConfigBase::validateKeys()
 */
#[Group('modeler_api')]
#[RunTestsInSeparateProcesses]
class RecipeMetadataStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'modeler_api',
    'modeler_api_test',
  ];

  /**
   * The model owner under test.
   */
  private ModelOwnerInterface $owner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner */
    $owner = $this->container
      ->get('plugin.manager.modeler_api.model_owner')
      ->createInstance('modeler_api_test');
    $this->owner = $owner;
  }

  /**
   * Tests that config actions for a dotted config name save and round-trip.
   */
  public function testConfigActionsSurviveSave(): void {
    $configActions = [
      [
        'config' => 'core.entity_form_display.node.article.default',
        'actions' => [
          'setComponents' => [
            [
              'name' => 'field_meta_description',
              'options' => ['type' => 'string_textfield', 'weight' => 20],
            ],
          ],
        ],
      ],
    ];

    $model = $this->saveModel(static function (ModelOwnerInterface $owner, ConfigEntityInterface $model) use ($configActions): void {
      $owner->setConfigActions($model, $configActions);
    });

    $this->assertSame($configActions, $this->owner->getConfigActions($model));
  }

  /**
   * Tests that several config actions survive a save.
   */
  public function testSeveralConfigActionsSurviveSave(): void {
    $configActions = [
      [
        'config' => 'core.entity_form_display.node.article.default',
        'actions' => ['setComponents' => [['name' => 'field_one']]],
      ],
      [
        'config' => 'core.entity_view_display.node.article.teaser',
        'actions' => ['hideComponent' => 'links'],
      ],
    ];

    $model = $this->saveModel(static function (ModelOwnerInterface $owner, ConfigEntityInterface $model) use ($configActions): void {
      $owner->setConfigActions($model, $configActions);
    });

    $this->assertSame($configActions, $this->owner->getConfigActions($model));
  }

  /**
   * Tests that the name-keyed shape is the one config storage rejects.
   *
   * This is the defect of #3588518. It is asserted directly, rather than only
   * relying on the new shape working, so that the constraint which forced the
   * shape is recorded and cannot be reintroduced unnoticed.
   */
  public function testNameKeyedConfigActionsAreRejectedByStorage(): void {
    $model = TestModel::create(['id' => 'dotted', 'label' => 'Dotted']);
    // Set the raw third-party value, because the accessor deliberately
    // normalizes to a list and would no longer produce this shape.
    $model->setThirdPartySetting('modeler_api', 'config_actions', [
      'core.entity_form_display.node.article.default' => [
        'setComponents' => [['name' => 'field_meta_description']],
      ],
    ]);

    $this->expectException(ConfigValueException::class);
    $this->expectExceptionMessage('core.entity_form_display.node.article.default key contains a dot which is not supported.');
    $model->save();
  }

  /**
   * Tests that the config names in export_config are values, not keys.
   *
   * The export_config setting stores config names too, so it is worth proving
   * that it does not share the defect: a list of dotted strings is fine,
   * because only keys are constrained.
   */
  public function testExportConfigSurvivesSave(): void {
    $exportConfig = [
      'core.entity_form_display.node.article.default',
      'core.entity_view_display.node.article.teaser',
    ];

    $model = $this->saveModel(static function (ModelOwnerInterface $owner, ConfigEntityInterface $model) use ($exportConfig): void {
      $owner->setExportConfig($model, $exportConfig);
    });

    $this->assertSame($exportConfig, $this->owner->getExportConfig($model));
  }

  /**
   * Tests that the remaining recipe metadata settings survive a save.
   */
  public function testSummaryAndRecipesSurviveSave(): void {
    $model = $this->saveModel(static function (ModelOwnerInterface $owner, ConfigEntityInterface $model): void {
      $owner
        ->setSummary($model, 'Enriches articles on save.')
        ->setRecipes($model, ['core/recipes/article_tags'])
        ->setDocumentation($model, 'The long form documentation.');
    });

    $this->assertSame('Enriches articles on save.', $this->owner->getSummary($model));
    $this->assertSame(['core/recipes/article_tags'], $this->owner->getRecipes($model));
    $this->assertSame('The long form documentation.', $this->owner->getDocumentation($model));
  }

  /**
   * Tests that all four settings save together on one model.
   */
  public function testAllRecipeMetadataSettingsSaveTogether(): void {
    $model = $this->saveModel(static function (ModelOwnerInterface $owner, ConfigEntityInterface $model): void {
      $owner
        ->setSummary($model, 'A one-line description.')
        ->setRecipes($model, ['core/recipes/article_tags'])
        ->setExportConfig($model, ['core.entity_form_display.node.article.default'])
        ->setConfigActions($model, [
          [
            'config' => 'core.entity_form_display.node.article.default',
            'actions' => ['setComponents' => [['name' => 'field_one']]],
          ],
        ]);
    });

    // The key order is not part of the contract: config storage normalizes it
    // to the order the schema declares, not the order the setters ran in.
    $this->assertEqualsCanonicalizing([
      'summary' => 'A one-line description.',
      'recipes' => ['core/recipes/article_tags'],
      'export_config' => ['core.entity_form_display.node.article.default'],
      'config_actions' => [
        [
          'config' => 'core.entity_form_display.node.article.default',
          'actions' => ['setComponents' => [['name' => 'field_one']]],
        ],
      ],
    ], $model->getThirdPartySettings('modeler_api'));
  }

  /**
   * Creates a model, applies the given settings and saves it.
   *
   * @param callable $apply
   *   Receives the model owner and the model, and applies the settings.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The model, reloaded from storage.
   */
  private function saveModel(callable $apply): ConfigEntityInterface {
    $model = TestModel::create(['id' => 'metadata', 'label' => 'Metadata']);
    $apply($this->owner, $model);
    $model->save();

    $reloaded = $this->container
      ->get('entity_type.manager')
      ->getStorage('modeler_api_test_model')
      ->loadUnchanged('metadata');
    $this->assertInstanceOf(ConfigEntityInterface::class, $reloaded);
    return $reloaded;
  }

}
