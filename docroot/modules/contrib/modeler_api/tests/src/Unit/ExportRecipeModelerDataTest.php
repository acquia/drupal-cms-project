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
use Drupal\modeler_api\Form\Settings;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that recipe export omits the modeler specific payload.
 */
#[CoversClass(ExportRecipe::class)]
#[Group('modeler_api')]
class ExportRecipeModelerDataTest extends UnitTestCase {

  /**
   * The config name of the exported model.
   */
  private const string MODEL_CONFIG = 'eca.eca.modeler_api_test';

  /**
   * The config name of the separately stored raw model data.
   */
  private const string DATA_MODEL_CONFIG = 'modeler_api.data_model.eca_bpmn_io_modeler_api_test';

  /**
   * The config name of an unrelated model in the dependency chain.
   */
  private const string OTHER_MODEL_CONFIG = 'eca.eca.modeler_api_other';

  /**
   * The recipe destination directory.
   */
  private string $destination;

  /**
   * The exported files, keyed by their absolute file name.
   */
  private array $written = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->destination = sys_get_temp_dir() . '/modeler_api_recipe_' . uniqid('', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Only mkdir() reaches the real file system, because the exported files
    // are captured from the mocked file system service instead of written.
    foreach ([$this->destination . '/config', $this->destination] as $directory) {
      if (is_dir($directory)) {
        rmdir($directory);
      }
    }
    parent::tearDown();
  }

  /**
   * Tests that the modeler specific payload never reaches the recipe.
   */
  public function testExportStripsModelerPayloadFromModelConfig(): void {
    $this->export(Settings::STORAGE_OPTION_THIRD_PARTY, [
      'modeler_id' => 'bpmn_io',
      'storage' => Settings::STORAGE_OPTION_THIRD_PARTY,
      'data' => '<?xml version="1.0" encoding="UTF-8"?><bpmn:definitions/>',
      'label' => 'Human readable model name',
      'documentation' => 'What this model does.',
      'tags' => ['eca-library', 'content'],
      'version' => '1.0.3',
      'changelog' => 'Initial release.',
    ]);

    $settings = $this->exportedConfig(self::MODEL_CONFIG)['third_party_settings']['modeler_api'];

    $this->assertArrayNotHasKey('data', $settings);
    $this->assertArrayNotHasKey('modeler_id', $settings);
    $this->assertSame(Settings::STORAGE_OPTION_NONE, $settings['storage']);
  }

  /**
   * Tests that the model metadata sharing the map survives the export.
   */
  public function testExportPreservesModelMetadata(): void {
    $this->export(Settings::STORAGE_OPTION_THIRD_PARTY, [
      'modeler_id' => 'bpmn_io',
      'data' => '<?xml version="1.0" encoding="UTF-8"?><bpmn:definitions/>',
      'label' => 'Human readable model name',
      'documentation' => 'What this model does.',
      'tags' => ['eca-library', 'content'],
      'version' => '1.0.3',
      'changelog' => 'Initial release.',
    ]);

    $config = $this->exportedConfig(self::MODEL_CONFIG);

    // The key order is not part of the contract: a model without an explicit
    // storage override gains the key at the end of the map.
    $this->assertEquals([
      'storage' => Settings::STORAGE_OPTION_NONE,
      'label' => 'Human readable model name',
      'documentation' => 'What this model does.',
      'tags' => ['eca-library', 'content'],
      'version' => '1.0.3',
      'changelog' => 'Initial release.',
    ], $config['third_party_settings']['modeler_api']);
    $this->assertSame(['setting' => 'value'], $config['third_party_settings']['unrelated_module']);
    $this->assertSame(['event_1' => ['plugin' => 'eca_base:eca_cron']], $config['events']);
    $this->assertArrayNotHasKey('uuid', $config);
    $this->assertArrayNotHasKey('_core', $config);
  }

  /**
   * Tests that separately stored raw data is left out of the recipe.
   */
  public function testExportOmitsSeparateDataModelConfig(): void {
    $this->export(Settings::STORAGE_OPTION_SEPARATE, [
      'modeler_id' => 'bpmn_io',
      'storage' => Settings::STORAGE_OPTION_SEPARATE,
      'data' => 'hash:' . hash('md5', 'raw model data'),
      'label' => 'Human readable model name',
    ]);

    $this->assertArrayNotHasKey($this->fileName(self::DATA_MODEL_CONFIG), $this->written);
    $this->assertArrayHasKey($this->fileName(self::MODEL_CONFIG), $this->written);

    $settings = $this->exportedConfig(self::MODEL_CONFIG)['third_party_settings']['modeler_api'];

    $this->assertArrayNotHasKey('data', $settings);
    $this->assertSame(Settings::STORAGE_OPTION_NONE, $settings['storage']);
    $this->assertSame('Human readable model name', $settings['label']);
  }

  /**
   * Tests that other config entities in the dependency chain are untouched.
   */
  public function testExportKeepsOtherConfigEntitiesUntouched(): void {
    $this->export(Settings::STORAGE_OPTION_THIRD_PARTY, [
      'modeler_id' => 'bpmn_io',
      'data' => 'own data',
      'label' => 'Human readable model name',
    ]);

    $this->assertSame([
      'modeler_id' => 'bpmn_io',
      'storage' => Settings::STORAGE_OPTION_THIRD_PARTY,
      'data' => 'unrelated data',
      'label' => 'Unrelated model',
    ], $this->exportedConfig(self::OTHER_MODEL_CONFIG)['third_party_settings']['modeler_api']);
  }

  /**
   * Runs an export for a model with the given storage method and settings.
   *
   * @param string $storageMethod
   *   The storage method the model owner reports for the model.
   * @param array $thirdPartySettings
   *   The modeler API third-party settings stored with the model.
   */
  private function export(string $storageMethod, array $thirdPartySettings): void {
    $configs = [
      self::MODEL_CONFIG => [
        'uuid' => '11111111-2222-3333-4444-555555555555',
        '_core' => ['default_config_hash' => 'oGuS0y2xVYcCRuLxvKAcQqHm3XFTxaRIcz2b6IudlVQ'],
        'id' => 'modeler_api_test',
        'label' => 'modeler_api_test',
        'status' => TRUE,
        'dependencies' => ['module' => []],
        'third_party_settings' => [
          'modeler_api' => $thirdPartySettings,
          'unrelated_module' => ['setting' => 'value'],
        ],
        'events' => ['event_1' => ['plugin' => 'eca_base:eca_cron']],
      ],
      self::OTHER_MODEL_CONFIG => [
        'uuid' => '66666666-7777-8888-9999-000000000000',
        'id' => 'modeler_api_other',
        'dependencies' => ['module' => []],
        'third_party_settings' => [
          'modeler_api' => [
            'modeler_id' => 'bpmn_io',
            'storage' => Settings::STORAGE_OPTION_THIRD_PARTY,
            'data' => 'unrelated data',
            'label' => 'Unrelated model',
          ],
        ],
      ],
      self::DATA_MODEL_CONFIG => [
        'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'id' => 'eca_bpmn_io_modeler_api_test',
        'data' => 'raw model data',
      ],
    ];

    // ManagedStorage is final, so wrap a mocked storage in a real instance.
    $storage = $this->createMock(StorageInterface::class);
    $storage->method('read')
      ->willReturnCallback(static fn (string $name): array|false => $configs[$name] ?? FALSE);
    $storageManager = $this->createMock(StorageManagerInterface::class);
    $storageManager->method('getStorage')->willReturn($storage);
    $configStorage = new ManagedStorage($storageManager);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('mkdir')
      ->willReturnCallback(static fn (string $uri): bool => is_dir($uri) || mkdir($uri, 0777, TRUE));
    $fileSystem->method('prepareDirectory')->willReturn(TRUE);
    $fileSystem->method('saveData')
      ->willReturnCallback(function (string $data, string $destination): string {
        $this->written[$destination] = $data;
        return $destination;
      });

    $api = $this->createMock(Api::class);
    $api->method('getNestedDependencies')
      ->willReturnCallback(static function (array &$allDependencies): void {
        $allDependencies['config'][] = self::OTHER_MODEL_CONFIG;
      });

    $owner = $this->createMock(ModelOwnerInterface::class);
    $owner->method('configEntityProviderId')->willReturn('eca');
    $owner->method('configEntityTypeId')->willReturn('eca');
    $owner->method('storageMethod')->willReturn($storageMethod);
    $owner->method('storageId')->willReturn('eca_bpmn_io_modeler_api_test');
    $owner->method('getDocumentation')->willReturn('What this model does.');
    $owner->method('docBaseUrl')->willReturn(NULL);

    $entity = $this->createMock(ConfigEntityInterface::class);
    $entity->method('id')->willReturn('modeler_api_test');
    $entity->method('label')->willReturn('Human readable model name');
    $entity->method('getDependencies')->willReturn(['module' => ['eca']]);

    $exportRecipe = new ExportRecipe(
      $configStorage,
      $fileSystem,
      $this->createMock(ModuleExtensionList::class),
      $this->createMock(MessengerInterface::class),
      $api,
    );
    $exportRecipe->setStringTranslation($this->getStringTranslationStub());
    $exportRecipe->doExport($owner, $entity, 'Test recipe', ExportRecipe::DEFAULT_NAMESPACE, $this->destination);
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

}
