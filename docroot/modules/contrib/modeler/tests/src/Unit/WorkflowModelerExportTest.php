<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler\Unit;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\modeler\FormToJsonConverter;
use Drupal\modeler\Plugin\ModelerApiModeler\WorkflowModeler;
use Drupal\modeler\YamlSchemaLookup;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Form\Wrapper;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests standalone graph exports from the Workflow Modeler.
 */
#[CoversClass(WorkflowModeler::class)]
#[Group('modeler')]
class WorkflowModelerExportTest extends UnitTestCase {

  /**
   * Tests metadata, graph reconstruction, and coordinate omission.
   */
  public function testExport(): void {
    $model = $this->createStub(ConfigEntityInterface::class);
    $model->method('id')->willReturn('example_model');
    $model->method('isNew')->willReturn(FALSE);

    $owner = $this->createMock(ModelOwnerInterface::class);
    $condition = new Component(
      $owner,
      'condition_1',
      Api::COMPONENT_TYPE_LINK,
      'example_condition',
      'Is example',
      ['value' => 'yes'],
    );
    $event = new Component(
      $owner,
      'event_1',
      Api::COMPONENT_TYPE_START,
      'example_event',
      'Example event',
      [],
      [new ComponentSuccessor('action_1', 'condition_1')],
    );
    $action = new Component(
      $owner,
      'action_1',
      Api::COMPONENT_TYPE_ELEMENT,
      'example_action',
      'Example action',
      ['message' => 'Done'],
    );

    $owner->method('getUsedComponents')->with($model)->willReturn([
      $event,
      $condition,
      $action,
    ]);
    $owner->method('getAnnotations')->with($model)->willReturn([]);
    $owner->method('getLabel')->with($model)->willReturn('Example model');
    $owner->method('getDocumentation')->with($model)->willReturn('Documentation');
    $owner->method('getSummary')->with($model)->willReturn('');
    $owner->method('getRecipes')->with($model)->willReturn([]);
    $owner->method('getConfigActions')->with($model)->willReturn([]);
    $owner->method('getExportConfig')->with($model)->willReturn([]);
    $owner->method('getModules')->with($model)->willReturn(['example_module']);
    $owner->method('getStatus')->with($model)->willReturn(TRUE);
    $owner->method('getTags')->with($model)->willReturn(['example']);
    $owner->method('getChangelog')->with($model)->willReturn('Initial version');
    $owner->method('getVersion')->with($model)->willReturn('1.0.0');
    $owner->method('getStorage')->with($model)->willReturn('none');
    $owner->method('getTemplate')->with($model)->willReturn(FALSE);

    $modeler = $this->createModeler();
    $export = json_decode($modeler->export($owner, $model), TRUE, flags: JSON_THROW_ON_ERROR);
    $convertedGraph = $modeler->convert($owner, $model);

    $this->assertSame('example_model', $export['id']);
    $this->assertSame('1.0.0', $export['version']);
    $this->assertSame([
      'label' => 'Example model',
      'documentation' => 'Documentation',
      'summary' => '',
      'recipes' => [],
      'config_actions' => [],
      'export_config' => [],
      'modules' => ['example_module'],
      'executable' => TRUE,
      'tags' => ['example'],
      'changelog' => 'Initial version',
      'id' => 'example_model',
      'version' => '1.0.0',
      'storage' => 'none',
      'template' => FALSE,
    ], $export['metadata']);
    $this->assertCount(2, $export['nodes']);
    $this->assertArrayNotHasKey('position', $export['nodes'][0]);
    $this->assertArrayNotHasKey('position', $export['nodes'][1]);
    $this->assertSame(['x' => 100, 'y' => 100], $convertedGraph['nodes'][0]['position']);
    $this->assertSame(['x' => 100, 'y' => 100], $convertedGraph['nodes'][1]['position']);
    foreach ($convertedGraph['nodes'] as &$node) {
      unset($node['position']);
    }
    unset($node);
    $this->assertSame($convertedGraph, [
      'nodes' => $export['nodes'],
      'edges' => $export['edges'],
    ]);
    $this->assertSame([
      'id' => 'event_1_action_1',
      'source' => 'event_1',
      'target' => 'action_1',
      'condition' => 'example_condition',
      'conditionConfiguration' => ['value' => 'yes'],
      'conditionLabel' => 'Is example',
      'conditionId' => 'condition_1',
    ], $export['edges'][0]);
    // None of the plugins can be resolved through this owner, so the export
    // carries an empty map rather than failing.
    $this->assertSame([], $export['configForms']);
  }

  /**
   * Tests that the export carries a config form for every plugin it uses.
   */
  public function testExportIncludesConfigForms(): void {
    $model = $this->createStub(ConfigEntityInterface::class);
    $model->method('id')->willReturn('example_model');
    $model->method('isNew')->willReturn(FALSE);

    $owner = $this->createMock(ModelOwnerInterface::class);
    $components = [
      new Component($owner, 'event_1', Api::COMPONENT_TYPE_START, 'example_event', 'Example event', [], [new ComponentSuccessor('action_1', 'condition_1')]),
      // An edge condition. The viewer looks these up by plugin ID too.
      new Component($owner, 'condition_1', Api::COMPONENT_TYPE_LINK, 'example_condition', 'Is example', ['value' => 'yes']),
      new Component($owner, 'action_1', Api::COMPONENT_TYPE_ELEMENT, 'example_action', 'Example action', ['message' => 'Done']),
      // Reuses the plugin of action_1 and must not build it a second time.
      new Component($owner, 'action_2', Api::COMPONENT_TYPE_ELEMENT, 'example_action', 'Another action', ['message' => 'Again']),
      // Neither of these two may end up in the export, nor abort it.
      new Component($owner, 'action_3', Api::COMPONENT_TYPE_ELEMENT, 'missing_action', 'Missing action', []),
      new Component($owner, 'action_4', Api::COMPONENT_TYPE_ELEMENT, 'locked_action', 'Locked action', []),
    ];
    $this->stubModelMetadata($owner, $model, $components);

    $eventPlugin = $this->createMock(ConfigurableTestPluginInterface::class);
    $eventPlugin->method('getPluginId')->willReturn('example_event');
    $actionPlugin = $this->createMock(ConfigurableTestPluginInterface::class);
    $actionPlugin->method('getPluginId')->willReturn('example_action');
    // The condition plugin is deliberately not configurable, to cover the
    // default value fallback that the config form endpoint also relies on.
    $conditionPlugin = $this->createStub(PluginInspectionInterface::class);
    $conditionPlugin->method('getPluginId')->willReturn('example_condition');
    $lockedPlugin = $this->createStub(PluginInspectionInterface::class);
    $lockedPlugin->method('getPluginId')->willReturn('locked_action');

    // A shared plugin is configured once, with the first component's values.
    $actionPlugin->expects($this->once())
      ->method('setConfiguration')
      ->with(['message' => 'Done']);
    $eventPlugin->expects($this->once())->method('setConfiguration')->with([]);

    $plugins = [
      'example_event' => $eventPlugin,
      'example_condition' => $conditionPlugin,
      'example_action' => $actionPlugin,
      'locked_action' => $lockedPlugin,
      'missing_action' => NULL,
    ];
    $owner->method('ownerComponent')
      ->willReturnCallback(static fn (int $type, string $id): ?PluginInspectionInterface => $plugins[$id] ?? NULL);
    $owner->method('ownerComponentEditable')
      ->willReturnCallback(static fn (PluginInspectionInterface $plugin): bool => $plugin->getPluginId() !== 'locked_action');

    $builtForms = [];
    $owner->method('buildConfigurationForm')
      ->willReturnCallback(function (PluginInspectionInterface $plugin, ?string $modelId, bool $modelIsNew) use (&$builtForms): array {
        // The plugin form must be built for the model that is exported.
        $this->assertSame('example_model', $modelId);
        $this->assertFalse($modelIsNew);
        $pluginId = $plugin->getPluginId();
        $builtForms[$pluginId] = ($builtForms[$pluginId] ?? 0) + 1;
        return match ($pluginId) {
          'example_event' => ['event_field' => ['#type' => 'textfield']],
          'example_condition' => ['value' => ['#type' => 'textfield']],
          default => ['message' => ['#type' => 'textfield']],
        };
      });
    $owner->method('getPluginSchemaKey')
      ->willReturnCallback(static fn (PluginInspectionInterface $plugin): string => 'schema.' . $plugin->getPluginId());

    $wrapped = [];
    $formBuilder = $this->createStub(FormBuilderInterface::class);
    $formBuilder->method('getForm')
      ->willReturnCallback(function (string $formClass, array $form) use (&$wrapped): array {
        $this->assertSame(Wrapper::class, $formClass);
        $wrapped[] = $form;
        // The wrapper form adds a dummy element that must not be exported.
        return $form + ['ecatemplatedummy' => ['#type' => 'value']];
      });
    $converter = $this->createStub(FormToJsonConverter::class);
    $converter->method('convert')
      ->willReturnCallback(static fn (array $form, string $schemaKey): array => [
        'fields' => array_keys($form),
        'schema' => $schemaKey,
      ]);

    $modeler = $this->createModeler();
    $modeler->setTestServices($formBuilder, $converter, $this->createStub(LoggerChannelInterface::class));
    $export = json_decode($modeler->export($owner, $model), TRUE, flags: JSON_THROW_ON_ERROR);

    // Node plugins and edge condition plugins are both present, keyed by
    // plugin ID, and the dummy wrapper element has been stripped.
    $this->assertSame([
      'example_event' => ['fields' => ['event_field'], 'schema' => 'schema.example_event'],
      'example_condition' => ['fields' => ['value'], 'schema' => 'schema.example_condition'],
      'example_action' => ['fields' => ['message'], 'schema' => 'schema.example_action'],
    ], $export['configForms']);

    // A plugin shared by several components is built exactly once, and the
    // unusable plugins were skipped without aborting the export.
    $this->assertSame([
      'example_event' => 1,
      'example_condition' => 1,
      'example_action' => 1,
    ], $builtForms);
    // Every component but the edge condition is a node, including the two
    // whose plugin could not be turned into a form.
    $this->assertCount(5, $export['nodes']);

    // A non-configurable plugin receives its values as form default values.
    $this->assertSame([
      'value' => ['#type' => 'textfield', '#default_value' => 'yes'],
    ], $wrapped[1]);
  }

  /**
   * Tests that a plugin blowing up does not take the whole export with it.
   */
  public function testExportSurvivesFailingPlugin(): void {
    $model = $this->createStub(ConfigEntityInterface::class);
    $model->method('id')->willReturn('example_model');
    $model->method('isNew')->willReturn(FALSE);

    $owner = $this->createMock(ModelOwnerInterface::class);
    $components = [
      new Component($owner, 'action_1', Api::COMPONENT_TYPE_ELEMENT, 'broken_action', 'Broken action', []),
      new Component($owner, 'action_2', Api::COMPONENT_TYPE_ELEMENT, 'example_action', 'Example action', []),
    ];
    $this->stubModelMetadata($owner, $model, $components);

    $owner->method('ownerComponent')
      ->willReturnCallback(function (int $type, string $id): PluginInspectionInterface {
        $plugin = $this->createStub(PluginInspectionInterface::class);
        $plugin->method('getPluginId')->willReturn($id);
        return $plugin;
      });
    $owner->method('ownerComponentEditable')->willReturn(TRUE);
    $owner->method('buildConfigurationForm')
      ->willReturnCallback(static function (PluginInspectionInterface $plugin): array {
        if ($plugin->getPluginId() === 'broken_action') {
          throw new \RuntimeException('Plugin form exploded.');
        }
        return ['message' => ['#type' => 'textfield']];
      });
    $owner->method('getPluginSchemaKey')->willReturn('');

    $formBuilder = $this->createStub(FormBuilderInterface::class);
    $formBuilder->method('getForm')
      ->willReturnCallback(static fn (string $formClass, array $form): array => $form);
    $converter = $this->createStub(FormToJsonConverter::class);
    $converter->method('convert')
      ->willReturnCallback(static fn (array $form): array => ['fields' => array_keys($form)]);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())->method('warning');

    $modeler = $this->createModeler();
    $modeler->setTestServices($formBuilder, $converter, $logger);
    $export = json_decode($modeler->export($owner, $model), TRUE, flags: JSON_THROW_ON_ERROR);

    $this->assertSame([
      'example_action' => ['fields' => ['message']],
    ], $export['configForms']);
    $this->assertCount(2, $export['nodes']);
  }

  /**
   * Tests that the recipe metadata getters distinguish absent from empty.
   *
   * Returning NULL is what stops `Api::prepareModelFromData()` from calling the
   * owner's setter, so a model whose raw data never carried these keys keeps
   * whatever a recipe or the API put there. An explicitly empty value, on the
   * other hand, is the user clearing the field and has to reach the setter.
   */
  public function testRecipeMetadataGettersDistinguishAbsentFromEmpty(): void {
    $owner = $this->createStub(ModelOwnerInterface::class);
    $modeler = $this->createModeler();

    $modeler->parseData($owner, json_encode([
      'id' => 'example_model',
      'metadata' => ['label' => 'Example model'],
    ], JSON_THROW_ON_ERROR));

    $this->assertNull($modeler->getSummary());
    $this->assertNull($modeler->getRecipes());
    $this->assertNull($modeler->getConfigActions());
    $this->assertNull($modeler->getExportConfig());
    $this->assertNull($modeler->getModules());

    $modeler->parseData($owner, json_encode([
      'id' => 'example_model',
      'metadata' => [
        'label' => 'Example model',
        'summary' => '',
        'recipes' => [],
        'config_actions' => [],
        'export_config' => [],
        'modules' => [],
      ],
    ], JSON_THROW_ON_ERROR));

    $this->assertSame('', $modeler->getSummary());
    $this->assertSame([], $modeler->getRecipes());
    $this->assertSame([], $modeler->getConfigActions());
    $this->assertSame([], $modeler->getExportConfig());
    $this->assertSame([], $modeler->getModules());

    $modeler->parseData($owner, json_encode([
      'id' => 'example_model',
      'metadata' => [
        'label' => 'Example model',
        'summary' => 'A one-line description.',
        'recipes' => ['core/recipes/article_tags'],
        'config_actions' => [
          ['config' => 'system.site', 'actions' => ['simple_config_update' => ['slogan' => 'Hi']]],
        ],
        'export_config' => ['system.site'],
        'modules' => ['my_module'],
      ],
    ], JSON_THROW_ON_ERROR));

    $this->assertSame('A one-line description.', $modeler->getSummary());
    $this->assertSame(['core/recipes/article_tags'], $modeler->getRecipes());
    $this->assertSame([
      ['config' => 'system.site', 'actions' => ['simple_config_update' => ['slogan' => 'Hi']]],
    ], $modeler->getConfigActions());
    $this->assertSame(['system.site'], $modeler->getExportConfig());
    $this->assertSame(['my_module'], $modeler->getModules());
  }

  /**
   * Tests that the export carries the model's own configuration form.
   *
   * A consumer outside of Drupal renders the metadata dialog from this form,
   * exactly like it renders a component's configuration form.
   */
  public function testExportIncludesMetadataForm(): void {
    $model = $this->createStub(ConfigEntityInterface::class);
    $model->method('id')->willReturn('example_model');
    $model->method('isNew')->willReturn(FALSE);

    $owner = $this->createMock(ModelOwnerInterface::class);
    $this->stubModelMetadata($owner, $model, []);
    $owner->method('supportsStatus')->willReturn(TRUE);
    $owner->method('supportsTemplate')->willReturn(TRUE);
    $owner->method('modelIdExistsCallback')->willReturn(['\Drupal\modeler_api\Api', 'modelExists']);
    $owner->method('enforceDefaultStorageMethod')->willReturn(FALSE);

    $modeler = $this->createModeler();
    $export = json_decode($modeler->export($owner, $model), TRUE, flags: JSON_THROW_ON_ERROR);
    $form = $export['metadataForm'];

    $this->assertSame([
      'label',
      'model_id',
      'executable',
      'template',
      'documentation',
      'tags',
      'recipe_export',
      'advanced',
    ], array_column($form, 'key'));

    $fields = array_column($form, NULL, 'key');
    // Both groups start collapsed, so the dialog opens on the fields that
    // every model needs.
    $this->assertFalse($fields['recipe_export']['open']);
    $this->assertFalse($fields['advanced']['open']);

    // The machine name derives from the label and cannot be changed once the
    // model exists.
    $this->assertSame('label', $fields['model_id']['source']);
    $this->assertTrue($fields['model_id']['disabled']);

    $recipeFields = array_column($fields['recipe_export']['children'], NULL, 'key');
    $this->assertSame([
      'summary',
      'recipes',
      'export_config',
      'modules',
      'config_actions',
    ], array_keys($recipeFields));
    $this->assertSame(255, $recipeFields['summary']['maxlength']);
    // The config actions textarea holds structured data the user edits as
    // YAML; nothing in the form array itself says so.
    $this->assertSame('yaml', $recipeFields['config_actions']['format']);

    $this->assertSame(
      ['version', 'storage', 'changelog'],
      array_column($fields['advanced']['children'], 'key'),
    );
  }

  /**
   * Tests that the editor page ships the metadata form to the React UI.
   *
   * The dialog cannot render without it, and drupalSettings is the only
   * channel the React app reads it from.
   */
  public function testEditAttachesMetadataForm(): void {
    $owner = $this->createStub(ModelOwnerInterface::class);
    $owner->method('supportedOwnerComponentTypes')->willReturn([]);
    $owner->method('modelIdExistsCallback')->willReturn(['\Drupal\modeler_api\Api', 'modelExists']);

    $reflection = new \ReflectionClass(RenderingWorkflowModeler::class);
    /** @var \Drupal\Tests\modeler\Unit\RenderingWorkflowModeler $modeler */
    $modeler = $reflection->newInstanceWithoutConstructor();
    $modeler->setStringTranslation($this->getStringTranslationStub());
    $modeler->setTestServices(
      $this->createStub(FormBuilderInterface::class),
      new FormToJsonConverter(
        $this->createStub(YamlSchemaLookup::class),
        $this->createStub(TypedConfigManagerInterface::class),
      ),
      $this->createStub(LoggerChannelInterface::class),
    );
    $modeler->setRequest(Request::create('/admin/modeler/example_model'));

    $build = $modeler->edit($owner, 'example_model', '{}', TRUE);
    $metadataForm = $build['#attached']['drupalSettings']['modeler']['metadataForm'];

    // This owner supports neither status nor templates, so the form it takes
    // part in building is what reaches the UI, not a hard-coded field list.
    $this->assertSame(
      ['label', 'model_id', 'documentation', 'tags', 'recipe_export', 'advanced'],
      array_column($metadataForm, 'key'),
    );
    // A new model may still choose its machine name.
    $this->assertArrayNotHasKey('disabled', $metadataForm[1]);
  }

  /**
   * Stubs the model metadata the export reads from the owner.
   *
   * @param \PHPUnit\Framework\MockObject\MockObject $owner
   *   The mocked model owner.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model entity.
   * @param \Drupal\modeler_api\Component[] $components
   *   The components the model is built from.
   */
  private function stubModelMetadata($owner, ConfigEntityInterface $model, array $components): void {
    $owner->method('getUsedComponents')->with($model)->willReturn($components);
    $owner->method('getAnnotations')->with($model)->willReturn([]);
    $owner->method('getLabel')->with($model)->willReturn('Example model');
    $owner->method('getDocumentation')->with($model)->willReturn('Documentation');
    $owner->method('getSummary')->with($model)->willReturn('');
    $owner->method('getRecipes')->with($model)->willReturn([]);
    $owner->method('getConfigActions')->with($model)->willReturn([]);
    $owner->method('getExportConfig')->with($model)->willReturn([]);
    $owner->method('getModules')->with($model)->willReturn(['example_module']);
    $owner->method('getStatus')->with($model)->willReturn(TRUE);
    $owner->method('getTags')->with($model)->willReturn(['example']);
    $owner->method('getChangelog')->with($model)->willReturn('Initial version');
    $owner->method('getVersion')->with($model)->willReturn('1.0.0');
    $owner->method('getStorage')->with($model)->willReturn('none');
    $owner->method('getTemplate')->with($model)->willReturn(FALSE);
  }

  /**
   * Instantiates the modeler without running the base class constructor.
   *
   * @return \Drupal\Tests\modeler\Unit\TestableWorkflowModeler
   *   The modeler under test.
   */
  private function createModeler(): TestableWorkflowModeler {
    $reflection = new \ReflectionClass(TestableWorkflowModeler::class);
    /** @var \Drupal\Tests\modeler\Unit\TestableWorkflowModeler $modeler */
    $modeler = $reflection->newInstanceWithoutConstructor();
    // Every export builds the shared model configuration form, which needs a
    // translator and a converter even when the test is not looking at it.
    $modeler->setStringTranslation($this->getStringTranslationStub());
    $modeler->setTestServices(
      $this->createStub(FormBuilderInterface::class),
      new FormToJsonConverter(
        $this->createStub(YamlSchemaLookup::class),
        $this->createStub(TypedConfigManagerInterface::class),
      ),
      $this->createStub(LoggerChannelInterface::class),
    );
    return $modeler;
  }

}

/**
 * A plugin that is configurable, as most owner components are.
 */
interface ConfigurableTestPluginInterface extends PluginInspectionInterface, ConfigurableInterface {
}

/**
 * Exposes the converted graph without constructing an editor render array.
 */
class TestableWorkflowModeler extends WorkflowModeler {

  /**
   * {@inheritdoc}
   */
  public function edit(ModelOwnerInterface $owner, string $id, string $data, bool $isNew = FALSE, bool $readOnly = FALSE): array {
    return json_decode($data, TRUE, flags: JSON_THROW_ON_ERROR);
  }

  /**
   * Injects the request the base class constructor would normally provide.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function setRequest(Request $request): void {
    $this->request = $request;
  }

  /**
   * Injects the services the base class constructor would normally provide.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   * @param \Drupal\modeler\FormToJsonConverter $formToJsonConverter
   *   The form-to-JSON converter.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function setTestServices(FormBuilderInterface $formBuilder, FormToJsonConverter $formToJsonConverter, LoggerChannelInterface $logger): void {
    $this->formBuilder = $formBuilder;
    $this->formToJsonConverter = $formToJsonConverter;
    $this->logger = $logger;
  }

}

/**
 * Runs the real edit() implementation that the graph double replaces.
 */
class RenderingWorkflowModeler extends TestableWorkflowModeler {

  /**
   * {@inheritdoc}
   */
  public function edit(ModelOwnerInterface $owner, string $id, string $data, bool $isNew = FALSE, bool $readOnly = FALSE): array {
    return WorkflowModeler::edit($owner, $id, $data, $isNew, $readOnly);
  }

}
