<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\ManagedStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\ExportRecipe;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the recipe metadata that a model expresses for its own export.
 */
#[CoversClass(ExportRecipe::class)]
#[Group('modeler_api')]
class ExportRecipeModelMetadataTest extends UnitTestCase {

  /**
   * The config name of the exported model.
   */
  private const string MODEL_CONFIG = 'eca.eca.metadata_test';

  /**
   * The config name of a role the model depends on.
   */
  private const string ROLE_CONFIG = 'user.role.editor';

  /**
   * The config name of an object the model does not depend on.
   */
  private const string EXTRA_CONFIG = 'core.entity_form_display.node.article.default';

  /**
   * The recipe destination directory.
   */
  private string $destination;

  /**
   * The exported files, keyed by their absolute file name.
   */
  private array $written = [];

  /**
   * The warnings the export sent to the messenger.
   */
  private array $warnings = [];

  /**
   * The active configuration the export reads from.
   */
  private array $configs = [];

  /**
   * The recipe metadata the model expresses.
   */
  private array $expressed = [];

  /**
   * The modules present on the site, keyed by name with their path as value.
   */
  private array $modulePaths = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->destination = sys_get_temp_dir() . '/modeler_api_metadata_' . uniqid('', TRUE);
    $this->configs = [
      self::MODEL_CONFIG => [
        'uuid' => '11111111-2222-3333-4444-555555555555',
        'id' => 'metadata_test',
        'label' => 'Metadata test',
        'dependencies' => ['module' => []],
        'third_party_settings' => ['modeler_api' => ['modeler_id' => 'bpmn_io']],
      ],
      self::ROLE_CONFIG => [
        'uuid' => '22222222-3333-4444-5555-666666666666',
        'id' => 'editor',
        'label' => 'Editor',
        'permissions' => ['access content'],
        'dependencies' => ['module' => []],
      ],
      self::EXTRA_CONFIG => [
        'uuid' => '33333333-4444-5555-6666-777777777777',
        'id' => 'node.article.default',
        'dependencies' => ['module' => []],
        'content' => ['field_tags' => ['type' => 'entity_reference_autocomplete']],
      ],
    ];
    $this->expressed = [
      'summary' => '',
      'documentation' => '',
      'recipes' => [],
      'config_actions' => [],
      'export_config' => [],
      'modules' => [],
    ];
    $this->modulePaths = [
      'eca' => 'modules/contrib/eca',
      'http_client_manager' => 'modules/contrib/http_client_manager',
      'eca_tv_demo' => 'modules/contrib/eca_tv_demo',
      'node' => 'core/modules/node',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ([$this->destination . '/config', $this->destination] as $directory) {
      if (!is_dir($directory)) {
        continue;
      }
      foreach (glob($directory . '/*') ?: [] as $file) {
        if (is_file($file)) {
          unlink($file);
        }
      }
      rmdir($directory);
    }
    parent::tearDown();
  }

  /**
   * Tests that an expressed summary becomes the recipe and package description.
   */
  public function testExpressedSummaryDescribesTheRecipe(): void {
    $this->expressed['summary'] = 'Enriches articles on save.';
    $this->expressed['documentation'] = "The long form.\n\nWith several paragraphs.";

    $this->export();

    $this->assertSame('Enriches articles on save.', $this->recipe()['description']);
    $this->assertSame('Enriches articles on save.', $this->composer()['description']);
    // The documentation keeps feeding the README body unchanged.
    $this->assertStringContainsString('The long form.', $this->readme());
    $this->assertStringContainsString('With several paragraphs.', $this->readme());
  }

  /**
   * Tests that a model without a summary falls back to its documentation.
   */
  public function testSummaryFallsBackToTheLeadingParagraph(): void {
    $this->expressed['documentation'] = "If a node page gets requested, a message will be printed.\n\nThis is using the event Controller found to handle request.";

    $this->export();

    $this->assertSame('If a node page gets requested, a message will be printed.', $this->recipe()['description']);
    $this->assertSame('If a node page gets requested, a message will be printed.', $this->composer()['description']);
  }

  /**
   * Tests the shapes of documentation a derived summary has to cope with.
   */
  #[DataProvider('summaryFallbackProvider')]
  public function testSummaryDerivation(string $documentation, string $expected): void {
    $this->expressed['documentation'] = $documentation;

    $this->export();

    $this->assertSame($expected, $this->recipe()['description']);
  }

  /**
   * Provides documentation and the summary that has to be derived from it.
   *
   * @return array<string, array<int, string>>
   *   Test cases of documentation and expected summary.
   */
  public static function summaryFallbackProvider(): array {
    $long = str_repeat('word ', 60);
    return [
      'empty documentation' => ['', ''],
      'single sentence' => ['A single sentence.', 'A single sentence.'],
      'leading paragraph only' => [
        "First paragraph.\n\nSecond paragraph.",
        'First paragraph.',
      ],
      'line breaks inside the paragraph collapse' => [
        "One line\nand another line.\n\nNext.",
        'One line and another line.',
      ],
      'leading and trailing whitespace is trimmed' => [
        "\n  Indented documentation.  \n\nMore.",
        'Indented documentation.',
      ],
      'a long paragraph reduces to its first sentence' => [
        'The first sentence is short. ' . $long,
        'The first sentence is short.',
      ],
    ];
  }

  /**
   * Tests that a long paragraph without a sentence end is truncated.
   */
  public function testSummaryWithoutSentenceEndIsTruncated(): void {
    $paragraph = trim(str_repeat('word ', 60));
    $this->expressed['documentation'] = $paragraph;

    $this->export();

    $description = $this->recipe()['description'];
    $this->assertLessThanOrEqual(255, mb_strlen($description));
    $this->assertStringEndsWith(' ...', $description);
    $this->assertStringStartsWith(mb_substr($description, 0, -4), $paragraph);
  }

  /**
   * Tests that expressed recipes reach the recipe file.
   */
  public function testExpressedRecipesAreEmitted(): void {
    $this->expressed['recipes'] = [
      'core/recipes/article_tags',
      'core/recipes/content_editor_role',
    ];

    $this->export();

    $this->assertSame([
      'core/recipes/article_tags',
      'core/recipes/content_editor_role',
    ], $this->recipe()['recipes']);
  }

  /**
   * Tests that a model without recipes emits no recipes key at all.
   */
  public function testNoRecipesEmitsNoKey(): void {
    $this->export();

    $this->assertArrayNotHasKey('recipes', $this->recipe());
  }

  /**
   * Tests that expressed config actions join the generated role actions.
   */
  public function testConfigActionsAreMergedBesideRoleActions(): void {
    $this->expressed['config_actions'] = [
      [
        'config' => self::EXTRA_CONFIG,
        'actions' => ['setComponents' => [['name' => 'field_summary']]],
      ],
    ];

    $this->export(dependencies: [self::ROLE_CONFIG]);

    $actions = $this->recipe()['config']['actions'];
    $this->assertSame([
      'ensure_exists' => ['label' => 'Editor'],
      'grantPermissions' => ['access content'],
    ], $actions[self::ROLE_CONFIG]);
    $this->assertSame([
      'setComponents' => [['name' => 'field_summary']],
    ], $actions[self::EXTRA_CONFIG]);
  }

  /**
   * Tests that several config actions all reach the recipe.
   */
  public function testSeveralConfigActionsAreAllEmitted(): void {
    $this->expressed['config_actions'] = [
      [
        'config' => self::EXTRA_CONFIG,
        'actions' => ['setComponents' => [['name' => 'field_summary']]],
      ],
      [
        'config' => 'core.entity_view_display.node.article.teaser',
        'actions' => ['hideComponent' => 'links'],
      ],
    ];

    $this->export();

    $actions = $this->recipe()['config']['actions'];
    $this->assertSame(
      ['setComponents' => [['name' => 'field_summary']]],
      $actions[self::EXTRA_CONFIG],
    );
    $this->assertSame(
      ['hideComponent' => 'links'],
      $actions['core.entity_view_display.node.article.teaser'],
    );
  }

  /**
   * Tests that a config action entry without a name cannot reach the recipe.
   *
   * An action under an empty key is not something a recipe could apply, so
   * such an entry is dropped rather than written out.
   */
  public function testConfigActionWithoutConfigNameIsSkipped(): void {
    $this->expressed['config_actions'] = [
      ['actions' => ['setComponents' => []]],
      ['config' => '', 'actions' => ['setComponents' => []]],
      ['config' => self::EXTRA_CONFIG, 'actions' => ['setComponents' => []]],
    ];

    $this->export();

    $actions = $this->recipe()['config']['actions'];
    $this->assertSame([self::EXTRA_CONFIG], array_keys($actions));
  }

  /**
   * Tests that a config action entry without actions yields an empty map.
   */
  public function testConfigActionWithoutActionsYieldsEmptyActions(): void {
    $this->expressed['config_actions'] = [
      ['config' => self::EXTRA_CONFIG],
    ];

    $this->export();

    $this->assertSame([], $this->recipe()['config']['actions'][self::EXTRA_CONFIG]);
  }

  /**
   * Tests that a declared config object is written into the recipe.
   */
  public function testDeclaredConfigIsExported(): void {
    $this->expressed['export_config'] = [self::EXTRA_CONFIG];

    $this->export();

    $exported = $this->exportedConfig(self::EXTRA_CONFIG);
    $this->assertSame(
      ['field_tags' => ['type' => 'entity_reference_autocomplete']],
      $exported['content'],
    );
    // The export writes what active configuration holds, never a copy that
    // the model carries, so the identifiers are stripped as everywhere else.
    $this->assertArrayNotHasKey('uuid', $exported);
    $this->assertSame([], $this->warnings);
  }

  /**
   * Tests that a config object declared twice is exported once.
   */
  public function testDeclaredConfigAlreadyDependedOnIsNotDuplicated(): void {
    $this->expressed['export_config'] = [self::MODEL_CONFIG];

    $this->export();

    $this->assertSame([], $this->warnings);
    $this->assertArrayHasKey($this->fileName(self::MODEL_CONFIG), $this->written);
  }

  /**
   * Tests that a declared config object that does not exist only warns.
   */
  public function testDeclaredConfigThatDoesNotExistIsSkippedWithWarning(): void {
    $this->expressed['export_config'] = [
      'core.entity_form_display.node.gone.default',
      self::EXTRA_CONFIG,
    ];

    $this->export();

    $this->assertCount(1, $this->warnings);
    $this->assertStringContainsString('core.entity_form_display.node.gone.default', $this->warnings[0]);
    // The rest of the export is unaffected.
    $this->assertArrayHasKey($this->fileName(self::EXTRA_CONFIG), $this->written);
    $this->assertArrayHasKey($this->fileName(self::MODEL_CONFIG), $this->written);
  }

  /**
   * Tests that a config file about to be deleted is reported first.
   */
  public function testUnexpectedConfigFileIsReportedBeforeDeletion(): void {
    $this->givenExistingConfigFile('core.entity_view_display.node.article.teaser.yml');

    $this->export();

    $this->assertCount(1, $this->warnings);
    $this->assertStringContainsString('core.entity_view_display.node.article.teaser.yml', $this->warnings[0]);
  }

  /**
   * Tests that a config file the model now declares is no longer reported.
   */
  public function testDeclaredConfigFileIsNotReportedAsUnexpected(): void {
    $this->givenExistingConfigFile(self::EXTRA_CONFIG . '.yml');
    $this->expressed['export_config'] = [self::EXTRA_CONFIG];

    $this->export();

    $this->assertSame([], $this->warnings);
  }

  /**
   * Tests that a regenerated config file is not reported as unexpected.
   */
  public function testRegeneratedConfigFileIsNotReportedAsUnexpected(): void {
    $this->givenExistingConfigFile(self::MODEL_CONFIG . '.yml');

    $this->export();

    $this->assertSame([], $this->warnings);
  }

  /**
   * Tests that curated file values survive while the model expresses nothing.
   */
  public function testPublishedRecipeKeepsItsCuratedValues(): void {
    $this->givenExistingRecipe([
      'name' => 'Old name',
      'description' => 'Curated summary',
      'type' => 'Workflow',
      'recipes' => ['core/recipes/article_tags'],
    ]);
    $this->givenExistingComposer([
      'name' => 'old/package',
      'description' => 'Curated package description',
    ]);
    $this->expressed['documentation'] = 'Documentation that is not the curated summary.';

    $this->export();

    $this->assertSame('Curated summary', $this->recipe()['description']);
    $this->assertSame(['core/recipes/article_tags'], $this->recipe()['recipes']);
    $this->assertSame('Curated package description', $this->composer()['description']);
  }

  /**
   * Tests that the model wins once it expresses what the file already holds.
   */
  public function testModelExpressedValuesWinOverTheExistingFile(): void {
    $this->givenExistingRecipe([
      'name' => 'Old name',
      'description' => 'Curated summary',
      'type' => 'Workflow',
      'recipes' => ['core/recipes/article_tags'],
      'config' => [
        'actions' => [
          self::EXTRA_CONFIG => ['setComponents' => [['name' => 'field_old']]],
        ],
      ],
    ]);
    $this->givenExistingComposer([
      'name' => 'old/package',
      'description' => 'Curated package description',
      'extra' => ['branch-alias' => ['dev-main' => '1.x-dev']],
    ]);
    $this->expressed['summary'] = 'The model now says this.';
    $this->expressed['recipes'] = ['core/recipes/content_editor_role'];
    $this->expressed['config_actions'] = [
      [
        'config' => self::EXTRA_CONFIG,
        'actions' => ['setComponents' => [['name' => 'field_new']]],
      ],
    ];

    $this->export();

    $recipe = $this->recipe();
    $this->assertSame('The model now says this.', $recipe['description']);
    $this->assertSame(['core/recipes/content_editor_role'], $recipe['recipes']);
    $this->assertSame(
      ['setComponents' => [['name' => 'field_new']]],
      $recipe['config']['actions'][self::EXTRA_CONFIG],
    );
    $this->assertSame('The model now says this.', $this->composer()['description']);
    // Package metadata the exporter does not own is still preserved.
    $this->assertSame(
      ['branch-alias' => ['dev-main' => '1.x-dev']],
      $this->composer()['extra'],
    );
  }

  /**
   * Tests that a hand-written action stays until the model expresses it.
   */
  public function testActionOnlyInTheFileIsStillPreserved(): void {
    $this->givenExistingRecipe([
      'name' => 'Old name',
      'description' => 'Curated summary',
      'type' => 'Workflow',
      'config' => [
        'actions' => [
          self::EXTRA_CONFIG => ['setComponents' => [['name' => 'field_old']]],
        ],
      ],
    ]);

    $this->export();

    $this->assertSame(
      ['setComponents' => [['name' => 'field_old']]],
      $this->recipe()['config']['actions'][self::EXTRA_CONFIG],
    );
  }

  /**
   * Tests that a declared module reaches both generated files.
   *
   * The two halves solve different problems: the composer requirement gets
   * the code onto the site, the install list enables it.
   */
  public function testDeclaredModuleReachesComposerAndInstall(): void {
    $this->expressed['modules'] = ['eca_tv_demo'];

    $this->export(computedModules: ['eca', 'http_client_manager']);

    $this->assertContains('eca_tv_demo', $this->recipe()['install']);
    $this->assertArrayHasKey('drupal/eca_tv_demo', $this->composer()['require']);
    $this->assertSame('*', $this->composer()['require']['drupal/eca_tv_demo']);
  }

  /**
   * Tests that declared modules join the computed ones instead of replacing.
   */
  public function testDeclaredModulesAreMergedWithComputedDependencies(): void {
    $this->expressed['modules'] = ['eca_tv_demo'];

    $this->export(computedModules: ['eca', 'http_client_manager']);

    $this->assertSame([
      'eca',
      'http_client_manager',
      'eca_tv_demo',
    ], $this->recipe()['install']);
    $this->assertSame([
      'drupal/eca' => '*',
      'drupal/http_client_manager' => '*',
      'drupal/eca_tv_demo' => '*',
    ], $this->composer()['require']);
  }

  /**
   * Tests that a declared module already computed is not listed twice.
   */
  public function testDeclaredModuleAlreadyComputedIsNotDuplicated(): void {
    $this->expressed['modules'] = ['http_client_manager', 'eca_tv_demo'];

    $this->export(computedModules: ['eca', 'http_client_manager']);

    $this->assertSame([
      'eca',
      'http_client_manager',
      'eca_tv_demo',
    ], $this->recipe()['install']);
    $this->assertCount(3, $this->composer()['require']);
  }

  /**
   * Tests that a model declaring nothing produces the computed lists as is.
   *
   * Re-exporting a published recipe whose model declares no extra module has
   * to leave both files byte for byte as they were.
   */
  public function testNoDeclaredModulesLeavesTheComputedListsUntouched(): void {
    $this->export(computedModules: ['eca', 'http_client_manager']);

    $this->assertSame(['eca', 'http_client_manager'], $this->recipe()['install']);
    $this->assertSame([
      'drupal/eca' => '*',
      'drupal/http_client_manager' => '*',
    ], $this->composer()['require']);
  }

  /**
   * Tests that a declared core module is installed but not required.
   *
   * A core module ships with Drupal, so there is nothing for composer to
   * resolve, but the recipe still has to enable it. This is the same
   * treatment a computed core dependency gets.
   */
  public function testDeclaredCoreModuleIsInstalledButNotRequired(): void {
    $this->expressed['modules'] = ['node'];

    $this->export(computedModules: ['eca']);

    $this->assertSame(['eca', 'node'], $this->recipe()['install']);
    $this->assertSame(['drupal/eca' => '*'], $this->composer()['require']);
  }

  /**
   * Tests that a declared module which does not exist only warns.
   */
  public function testDeclaredModuleThatDoesNotExistIsSkippedWithWarning(): void {
    $this->expressed['modules'] = ['no_such_module', 'eca_tv_demo'];

    $this->export(computedModules: ['eca']);

    $this->assertCount(1, $this->warnings);
    $this->assertStringContainsString('no_such_module', $this->warnings[0]);
    // The rest of the export is unaffected.
    $this->assertSame(['eca', 'eca_tv_demo'], $this->recipe()['install']);
  }

  /**
   * Tests that a declared module survives a re-export over existing files.
   *
   * This is the symptom the issue reported: a module added to composer.json by
   * hand disappears on the next export, because require and install are both
   * refreshed wholesale. Expressing it on the model is what makes it stick.
   */
  public function testDeclaredModuleSurvivesReExport(): void {
    $this->givenExistingRecipe([
      'name' => 'Old name',
      'description' => 'Curated summary',
      'type' => 'Workflow',
      'install' => ['eca', 'added_by_hand'],
    ]);
    $this->givenExistingComposer([
      'name' => 'old/package',
      'description' => 'Curated package description',
      'require' => ['drupal/eca' => '*', 'drupal/added_by_hand' => '*'],
    ]);
    $this->expressed['modules'] = ['eca_tv_demo'];

    $this->export(computedModules: ['eca']);

    // What the model expresses is there.
    $this->assertSame(['eca', 'eca_tv_demo'], $this->recipe()['install']);
    $this->assertSame([
      'drupal/eca' => '*',
      'drupal/eca_tv_demo' => '*',
    ], $this->composer()['require']);
    // What was only ever in the file still does not survive, which is why it
    // has to be expressed on the model instead.
    $this->assertNotContains('added_by_hand', $this->recipe()['install']);
    $this->assertArrayNotHasKey('drupal/added_by_hand', $this->composer()['require']);
  }

  /**
   * Tests that a model with only declared modules still lists them.
   */
  public function testDeclaredModulesWithoutComputedOnesAreStillEmitted(): void {
    $this->expressed['modules'] = ['eca_tv_demo'];

    $this->export();

    $this->assertSame(['eca_tv_demo'], $this->recipe()['install']);
    $this->assertSame(['drupal/eca_tv_demo' => '*'], $this->composer()['require']);
  }

  /**
   * Writes an existing config file into the recipe destination.
   *
   * @param string $fileName
   *   The file name inside the recipe's config directory.
   */
  private function givenExistingConfigFile(string $fileName): void {
    if (!is_dir($this->destination . '/config')) {
      mkdir($this->destination . '/config', 0777, TRUE);
    }
    file_put_contents($this->destination . '/config/' . $fileName, Yaml::encode(['id' => 'existing']));
  }

  /**
   * Writes an existing recipe file into the recipe destination.
   *
   * @param array $recipe
   *   The recipe metadata.
   */
  private function givenExistingRecipe(array $recipe): void {
    if (!is_dir($this->destination)) {
      mkdir($this->destination, 0777, TRUE);
    }
    file_put_contents($this->destination . '/recipe.yml', Yaml::encode($recipe));
  }

  /**
   * Writes an existing composer file into the recipe destination.
   *
   * @param array $composer
   *   The composer metadata.
   */
  private function givenExistingComposer(array $composer): void {
    if (!is_dir($this->destination)) {
      mkdir($this->destination, 0777, TRUE);
    }
    file_put_contents($this->destination . '/composer.json', json_encode($composer));
  }

  /**
   * Runs an export of the model.
   *
   * @param array $dependencies
   *   Additional config names the model depends on.
   * @param array $computedModules
   *   The module names Drupal computes as dependencies of the model.
   */
  private function export(array $dependencies = [], array $computedModules = []): void {
    // ManagedStorage is final, so wrap a stubbed storage in a real instance.
    $storage = $this->createStub(StorageInterface::class);
    $storage->method('read')
      ->willReturnCallback(fn (string $name): array|false => $this->configs[$name] ?? FALSE);
    $storageManager = $this->createStub(StorageManagerInterface::class);
    $storageManager->method('getStorage')->willReturn($storage);

    $fileSystem = $this->createStub(FileSystemInterface::class);
    $fileSystem->method('mkdir')
      ->willReturnCallback(static fn (string $uri): bool => is_dir($uri) || mkdir($uri, 0777, TRUE));
    $fileSystem->method('prepareDirectory')->willReturn(TRUE);
    $fileSystem->method('deleteRecursive')->willReturn(TRUE);
    $fileSystem->method('unlink')->willReturn(TRUE);
    $fileSystem->method('dirName')->willReturn('');
    $fileSystem->method('saveData')
      ->willReturnCallback(function (string $data, string $destination): string {
        $this->written[$destination] = $data;
        return $destination;
      });
    // Mirrors what the real service returns: one object per matching file,
    // carrying the base name in its filename property.
    $fileSystem->method('scanDirectory')
      ->willReturnCallback(static function (string $dir, string $mask): array {
        $result = [];
        foreach (scandir($dir) ?: [] as $entry) {
          if ($entry !== '.' && $entry !== '..' && preg_match($mask, $entry) === 1) {
            $result[$dir . '/' . $entry] = (object) [
              'uri' => $dir . '/' . $entry,
              'filename' => $entry,
              'name' => pathinfo($entry, PATHINFO_FILENAME),
            ];
          }
        }
        return $result;
      });

    $messenger = $this->createStub(MessengerInterface::class);
    $messenger->method('addWarning')
      ->willReturnCallback(function (mixed $message) use ($messenger): MessengerInterface {
        $this->warnings[] = (string) $message;
        return $messenger;
      });

    $api = $this->createStub(Api::class);
    $api->method('getNestedDependencies')
      ->willReturnCallback(static function (array &$allDependencies) use ($dependencies, $computedModules): void {
        foreach ($dependencies as $configName) {
          $allDependencies['config'][] = $configName;
        }
        foreach ($computedModules as $module) {
          $allDependencies['module'][] = $module;
        }
      });

    $moduleExtensionList = $this->createStub(ModuleExtensionList::class);
    $moduleExtensionList->method('exists')
      ->willReturnCallback(fn (string $module): bool => isset($this->modulePaths[$module]));
    $moduleExtensionList->method('getPath')
      ->willReturnCallback(fn (string $module): string => $this->modulePaths[$module] ?? '');
    $moduleExtensionList->method('getList')->willReturn([]);

    $owner = $this->createStub(ModelOwnerInterface::class);
    $owner->method('configEntityProviderId')->willReturn('eca');
    $owner->method('configEntityTypeId')->willReturn('eca');
    $owner->method('storageMethod')->willReturn('none');
    $owner->method('docBaseUrl')->willReturn(NULL);
    $owner->method('getDocumentation')->willReturn($this->expressed['documentation']);
    $owner->method('getSummary')->willReturn($this->expressed['summary']);
    $owner->method('getRecipes')->willReturn($this->expressed['recipes']);
    $owner->method('getConfigActions')->willReturn($this->expressed['config_actions']);
    $owner->method('getExportConfig')->willReturn($this->expressed['export_config']);
    $owner->method('getModules')->willReturn($this->expressed['modules']);

    $entity = $this->createStub(ConfigEntityInterface::class);
    $entity->method('id')->willReturn('metadata_test');
    $entity->method('label')->willReturn('Metadata test');
    $entity->method('getDependencies')->willReturn(['module' => []]);

    $exportRecipe = new ExportRecipe(
      new ManagedStorage($storageManager),
      $fileSystem,
      $moduleExtensionList,
      $messenger,
      $api,
    );
    $exportRecipe->setStringTranslation($this->getStringTranslationStub());
    $exportRecipe->doExport($owner, $entity, 'Metadata test recipe', ExportRecipe::DEFAULT_NAMESPACE, $this->destination);
  }

  /**
   * Builds the file name a config entity is exported to.
   *
   * @param string $configName
   *   The config name.
   *
   * @return string
   *   The absolute file name inside the recipe destination.
   */
  private function fileName(string $configName): string {
    return $this->destination . '/config/' . $configName . '.yml';
  }

  /**
   * Reads an exported config entity back from the recipe.
   *
   * @param string $configName
   *   The config name.
   *
   * @return array
   *   The decoded config data.
   */
  private function exportedConfig(string $configName): array {
    $fileName = $this->fileName($configName);
    $this->assertArrayHasKey($fileName, $this->written);
    return Yaml::decode($this->written[$fileName]);
  }

  /**
   * Reads the generated recipe metadata.
   *
   * @return array
   *   The decoded recipe metadata.
   */
  private function recipe(): array {
    $fileName = $this->destination . '/recipe.yml';
    $this->assertArrayHasKey($fileName, $this->written);
    return Yaml::decode($this->written[$fileName]);
  }

  /**
   * Reads the generated composer metadata.
   *
   * @return array
   *   The decoded composer metadata.
   */
  private function composer(): array {
    $fileName = $this->destination . '/composer.json';
    $this->assertArrayHasKey($fileName, $this->written);
    return json_decode($this->written[$fileName], TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Reads the generated readme.
   *
   * @return string
   *   The readme content.
   */
  private function readme(): string {
    $fileName = $this->destination . '/README.md';
    $this->assertArrayHasKey($fileName, $this->written);
    return $this->written[$fileName];
  }

}
