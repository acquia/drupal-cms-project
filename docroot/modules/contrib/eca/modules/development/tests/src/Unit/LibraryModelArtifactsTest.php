<?php

namespace Drupal\Tests\eca_development\Unit;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Yaml;
use Drupal\eca\Entity\Eca;
use Drupal\eca_development\LibraryModelArtifacts;
use Drupal\modeler_api\Form\Settings;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests preparation of ECA library model artifacts.
 */
#[Group('eca')]
#[Group('eca_development')]
class LibraryModelArtifactsTest extends UnitTestCase {

  /**
   * Tests that only enabled models are exportable.
   */
  public function testIsExportable(): void {
    $artifacts = new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class));
    $eca = $this->createStub(Eca::class);
    $eca->method('status')->willReturn(TRUE);
    $this->assertTrue($artifacts->isExportable($eca));

    $eca = $this->createStub(Eca::class);
    $eca->method('status')->willReturn(FALSE);
    $this->assertFalse($artifacts->isExportable($eca));
  }

  /**
   * Tests that site-specific config metadata is omitted.
   */
  public function testConfig(): void {
    $eca = $this->createStub(Eca::class);
    $eca->method('toArray')->willReturn([
      'uuid' => 'c608346f-f8a4-457b-8dba-4a88450e5f04',
      '_core' => ['default_config_hash' => 'hash'],
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'events' => [],
    ]);

    $config = Yaml::decode((new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class)))->config($eca));
    $this->assertSame([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'events' => [],
    ], $config);
  }

  /**
   * Tests that the modeler specific payload never reaches the artifact.
   *
   * The graph is published beside the configuration as its own file, so
   * carrying it inline as well ships the same bytes twice. "modeler_id" goes
   * with it, because that sibling file is produced by a fixed modeler rather
   * than by the one the model names.
   */
  public function testConfigStripsModelerPayload(): void {
    $eca = $this->createStub(Eca::class);
    $eca->method('toArray')->willReturn([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'third_party_settings' => [
        'modeler_api' => [
          'modeler_id' => 'bpmn_io',
          'storage' => 'third_party_settings',
          'data' => '<?xml version="1.0"?><bpmn:definitions/>',
          'changelog' => 'Initial version',
        ],
      ],
      'events' => [],
    ]);

    $yaml = (new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class)))->config($eca);

    // Neither the graph nor the modeler that authored it may survive anywhere
    // in the serialized output, not just under the keys they were stored in.
    $this->assertStringNotContainsString('bpmn:definitions', $yaml);
    $this->assertStringNotContainsString('bpmn_io', $yaml);
    $this->assertSame([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'third_party_settings' => [
        'modeler_api' => [
          'storage' => Settings::STORAGE_OPTION_NONE,
          'changelog' => 'Initial version',
        ],
      ],
      'events' => [],
    ], Yaml::decode($yaml));
  }

  /**
   * Tests that the model metadata sharing the map survives publication.
   *
   * Everything besides the payload is portable model metadata, most notably
   * the label: Eca::label() reads it from this map, so it is the model's only
   * human-readable name and dropping it would publish an unnamed model.
   *
   * @see \Drupal\eca\Entity\Eca::label()
   */
  public function testConfigPreservesModelMetadata(): void {
    $eca = $this->createStub(Eca::class);
    $eca->method('toArray')->willReturn([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'third_party_settings' => [
        'modeler_api' => [
          'modeler_id' => 'bpmn_io',
          'data' => '<?xml version="1.0"?><bpmn:definitions/>',
          'label' => 'Human readable model name',
          'documentation' => 'What this model does.',
          'tags' => ['eca-library', 'content'],
          'version' => '1.0.3',
          'changelog' => 'Initial release.',
        ],
      ],
      'events' => [],
    ]);

    $config = Yaml::decode((new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class)))->config($eca));

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
  }

  /**
   * Tests that a model without a stored graph still loses its modeler.
   *
   * Models stored with "storage: none" carry no "data" key at all, so there is
   * nothing to strip - but they still name the modeler that authored them, and
   * a published artifact must not.
   */
  public function testConfigStripsModelerFromModelWithoutPayload(): void {
    $eca = $this->createStub(Eca::class);
    $eca->method('toArray')->willReturn([
      'id' => 'eca_lib_0037',
      'status' => TRUE,
      'third_party_settings' => [
        'modeler_api' => [
          'modeler_id' => 'workflow_modeler',
          'storage' => Settings::STORAGE_OPTION_NONE,
          'changelog' => 'Initial version',
        ],
      ],
      'events' => [],
    ]);

    $this->assertSame([
      'id' => 'eca_lib_0037',
      'status' => TRUE,
      'third_party_settings' => [
        'modeler_api' => [
          'storage' => Settings::STORAGE_OPTION_NONE,
          'changelog' => 'Initial version',
        ],
      ],
      'events' => [],
    ], Yaml::decode((new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class)))->config($eca)));
  }

  /**
   * Tests that stripping leaves no empty parent maps behind.
   *
   * A reader copies this configuration by hand, and "modeler_api: {  }" reads
   * as truncation rather than as an absent graph. Forcing the storage method
   * replaces what the removals took out, so the map that held nothing but a
   * payload keeps exactly that one key instead of collapsing.
   */
  public function testConfigLeavesNoEmptyModelerApiMap(): void {
    $eca = $this->createStub(Eca::class);
    $eca->method('toArray')->willReturn([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'third_party_settings' => [
        'modeler_api' => [
          'modeler_id' => 'bpmn_io',
          'data' => '<?xml version="1.0"?><bpmn:definitions/>',
        ],
      ],
      'events' => [],
    ]);

    $yaml = (new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class)))->config($eca);

    $this->assertStringNotContainsString('modeler_api: {  }', $yaml);
    $this->assertSame([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'third_party_settings' => [
        'modeler_api' => [
          'storage' => Settings::STORAGE_OPTION_NONE,
        ],
      ],
      'events' => [],
    ], Yaml::decode($yaml));
  }

  /**
   * Tests that other third-party settings keep their place.
   *
   * Only Modeler API's own map describes the modeler; another module's
   * settings say something the sibling files do not.
   */
  public function testConfigKeepsOtherThirdPartySettings(): void {
    $eca = $this->createStub(Eca::class);
    $eca->method('toArray')->willReturn([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'third_party_settings' => [
        'modeler_api' => [
          'modeler_id' => 'bpmn_io',
          'data' => '<?xml version="1.0"?><bpmn:definitions/>',
        ],
        'some_module' => ['setting' => 'value'],
      ],
      'events' => [],
    ]);

    $this->assertSame([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'third_party_settings' => [
        'modeler_api' => [
          'storage' => Settings::STORAGE_OPTION_NONE,
        ],
        'some_module' => ['setting' => 'value'],
      ],
      'events' => [],
    ], Yaml::decode((new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class)))->config($eca)));
  }

  /**
   * Tests that a model without Modeler API settings does not gain any.
   *
   * Forcing the storage method must describe a map that is already there, not
   * annotate a model that Modeler API never touched.
   */
  public function testConfigDoesNotInventModelerApiSettings(): void {
    $settings = [
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'third_party_settings' => ['some_module' => ['setting' => 'value']],
      'events' => [],
    ];
    $eca = $this->createStub(Eca::class);
    $eca->method('toArray')->willReturn($settings);

    $this->assertSame(
      Yaml::encode($settings),
      (new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class)))->config($eca),
    );
  }

  /**
   * Tests that the dependency on separately stored raw data is dropped.
   *
   * A model using the "separate" storage method depends on the config entity
   * holding its payload. The documentation export does not publish that
   * entity, so an artifact still declaring the dependency cannot be imported
   * at all.
   */
  public function testConfigDropsSeparateDataModelDependency(): void {
    $eca = $this->createStub(Eca::class);
    $eca->method('toArray')->willReturn([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'dependencies' => [
        'config' => [
          'modeler_api.data_model.eca_bpmn_io_eca_lib_0001',
          'node.type.article',
        ],
        'module' => ['eca_content'],
      ],
      'third_party_settings' => [
        'modeler_api' => [
          'modeler_id' => 'bpmn_io',
          'storage' => 'separate',
          'data' => 'hash:' . hash('md5', 'raw model data'),
        ],
      ],
      'events' => [],
    ]);

    $config = Yaml::decode((new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class)))->config($eca));

    // The surviving dependency has to be renumbered from zero, or the removal
    // turns the sequence into a keyed map.
    $this->assertSame(['node.type.article'], $config['dependencies']['config']);
    $this->assertSame(['eca_content'], $config['dependencies']['module']);
    $this->assertSame(
      ['storage' => Settings::STORAGE_OPTION_NONE],
      $config['third_party_settings']['modeler_api'],
    );
  }

  /**
   * Tests that a dependency list emptied by the removal is dropped as well.
   *
   * An empty "config: {  }" reads as truncation for the same reason an empty
   * third-party settings map does.
   */
  public function testConfigDropsDependencyKeysEmptiedByStripping(): void {
    $eca = $this->createStub(Eca::class);
    $eca->method('toArray')->willReturn([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'dependencies' => [
        'config' => ['modeler_api.data_model.eca_bpmn_io_eca_lib_0001'],
      ],
      'events' => [],
    ]);

    $yaml = (new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class)))->config($eca);

    $this->assertStringNotContainsString('dependencies', $yaml);
    $this->assertSame([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'events' => [],
    ], Yaml::decode($yaml));
  }

  /**
   * Tests that unrelated config dependencies keep their place.
   */
  public function testConfigKeepsOtherConfigDependencies(): void {
    $dependencies = [
      'config' => ['node.type.article', 'node.type.page'],
      'module' => ['eca_content'],
    ];
    $eca = $this->createStub(Eca::class);
    $eca->method('toArray')->willReturn([
      'id' => 'eca_lib_0001',
      'status' => TRUE,
      'dependencies' => $dependencies,
      'events' => [],
    ]);

    $this->assertSame(
      $dependencies,
      Yaml::decode((new LibraryModelArtifacts($this->createStub(ModelerPluginManager::class)))->config($eca))['dependencies'],
    );
  }

  /**
   * Tests that a model naming no modeler still gets a graph.
   *
   * A published library model is modeler agnostic and therefore carries no
   * "modeler_id", so Modeler API substitutes its "fallback" plugin whenever
   * the model is asked which modeler authored it. Rendering the graph is a
   * presentation concern rather than a property of how the model was
   * authored, so the fixed Workflow Modeler is asked directly and the model
   * owner is never consulted about a modeler at all.
   */
  public function testGraph(): void {
    $eca = $this->createStub(Eca::class);
    $owner = $this->createMock(ModelOwnerInterface::class);
    $owner->expects($this->never())->method('getModeler');
    $modeler = $this->createMock(ModelerInterface::class);
    $modeler->method('getRawFileExtension')->willReturn('json');
    $modeler->expects($this->once())
      ->method('export')
      ->with($owner, $eca)
      ->willReturn('{"nodes":[],"edges":[]}');
    $artifacts = new LibraryModelArtifacts($this->modelerPluginManager($modeler));

    $this->assertSame('{"nodes":[],"edges":[]}', $artifacts->graph($eca, $owner));
  }

  /**
   * Tests that an absent Workflow Modeler yields no graph and no error.
   *
   * The Workflow Modeler ships in a project of its own, so a publishing site
   * may legitimately not have it. Modeler API's plugin manager then hands out
   * its "fallback" plugin, which declares no raw file format and exports
   * nothing. Publishing no graph is the right answer there, and it must not
   * make the surrounding documentation export fail.
   */
  public function testGraphWithoutWorkflowModeler(): void {
    $modeler = $this->createMock(ModelerInterface::class);
    $modeler->method('getRawFileExtension')->willReturn(NULL);
    $modeler->expects($this->never())->method('export');
    $artifacts = new LibraryModelArtifacts($this->modelerPluginManager($modeler));

    $this->assertNull($artifacts->graph(
      $this->createStub(Eca::class),
      $this->createStub(ModelOwnerInterface::class),
    ));
  }

  /**
   * Tests that a modeler producing another raw format yields no graph.
   *
   * The standalone viewer loads a ".json" sibling file, so a modeler whose
   * raw format is anything else has nothing to contribute to that page.
   */
  public function testGraphWithNonJsonModeler(): void {
    $modeler = $this->createMock(ModelerInterface::class);
    $modeler->method('getRawFileExtension')->willReturn('xml');
    $modeler->expects($this->never())->method('export');
    $artifacts = new LibraryModelArtifacts($this->modelerPluginManager($modeler));

    $this->assertNull($artifacts->graph(
      $this->createStub(Eca::class),
      $this->createStub(ModelOwnerInterface::class),
    ));
  }

  /**
   * Tests that an unresolvable modeler plugin yields no graph.
   */
  public function testGraphWithUnresolvableModeler(): void {
    $manager = $this->createStub(ModelerPluginManager::class);
    $manager->method('createInstance')
      ->willThrowException(new PluginNotFoundException('workflow_modeler'));
    $artifacts = new LibraryModelArtifacts($manager);

    $this->assertNull($artifacts->graph(
      $this->createStub(Eca::class),
      $this->createStub(ModelOwnerInterface::class),
    ));
  }

  /**
   * Tests that an empty export is reported as no graph at all.
   *
   * An empty string would be written out as a zero-byte ".json" that the
   * viewer can never load, so it has to be indistinguishable from no graph.
   */
  public function testGraphWithEmptyExport(): void {
    $modeler = $this->createStub(ModelerInterface::class);
    $modeler->method('getRawFileExtension')->willReturn('json');
    $modeler->method('export')->willReturn('');
    $artifacts = new LibraryModelArtifacts($this->modelerPluginManager($modeler));

    $this->assertNull($artifacts->graph(
      $this->createStub(Eca::class),
      $this->createStub(ModelOwnerInterface::class),
    ));
  }

  /**
   * Tests the ECA 2 upgrade window without Modeler API services.
   */
  public function testGraphWithoutModelerApi(): void {
    $artifacts = new LibraryModelArtifacts(NULL);
    $eca = $this->createStub(Eca::class);
    $owner = $this->createMock(ModelOwnerInterface::class);
    $owner->expects($this->never())->method('getModeler');

    $this->assertNull($artifacts->graph($eca, $owner));
  }

  /**
   * Creates a plugin manager handing out the fixed graph modeler.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface $modeler
   *   The modeler plugin the manager is expected to return.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerPluginManager
   *   The plugin manager mock, asserting that exactly the Workflow Modeler is
   *   requested rather than whichever modeler a model happens to name.
   */
  private function modelerPluginManager(ModelerInterface $modeler): ModelerPluginManager {
    $manager = $this->createMock(ModelerPluginManager::class);
    $manager->expects($this->once())
      ->method('createInstance')
      ->with('workflow_modeler')
      ->willReturn($modeler);
    return $manager;
  }

}
