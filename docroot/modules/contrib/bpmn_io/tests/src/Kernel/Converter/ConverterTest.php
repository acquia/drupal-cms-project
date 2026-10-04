<?php

namespace Drupal\Tests\bpmn_io\Kernel\Converter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ModelerPluginManager;

/**
 * Tests converting different types of ECA-entities.
 *
 * @group bpmn_io
 */
class ConverterTest extends KernelTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * The modeler API.
   *
   * @var \Drupal\modeler_api\Api|null
   */
  protected ?Api $modelerApi;

  /**
   * The modeler plugin manager.
   *
   * @var \Drupal\modeler_api\Plugin\ModelerPluginManager|null
   */
  protected ?ModelerPluginManager $modelerPluginManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bpmn_io',
    'bpmn_io_test',
    'eca',
    'eca_base',
    'eca_content',
    'eca_ui',
    'eca_user',
    'eca_views',
    'field',
    'modeler_api',
    'user',
    'views',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');

    $this->installConfig(static::$modules);

    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->modelerApi = \Drupal::service('modeler_api.service');
    $this->modelerPluginManager = \Drupal::service('plugin.manager.modeler_api.modeler');
  }

  /**
   * Convert an ECA-entity that uses the fallback-model.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testConvertFallback(): void {
    /** @var \Drupal\eca\Entity\EcaStorage $storage */
    $storage = $this->entityTypeManager->getStorage('eca');
    // Confirm initial state.
    /** @var \Drupal\eca\Entity\Eca[] $ecaCollection */
    $ecaCollection = $storage->loadMultiple();
    $this->assertCount(2, $ecaCollection);

    /** @var \Drupal\eca\Entity\Eca $eca */
    $eca = $storage->load('eca_fallback');
    $owner = $this->modelerApi->findOwner($eca);
    $modeler = $owner->getModeler($eca);
    $this->assertEquals('fallback', $modeler->getPluginId());

    // Convert to bpmn_io.
    $modeler = $this->modelerPluginManager->createInstance('bpmn_io');
    $build = $modeler->convert($owner, $eca);

    // Assert result.
    /** @var \Drupal\eca\Entity\Eca[] $ecaCollection */
    $ecaCollection = $storage->loadMultiple();
    $this->assertCount(2, $ecaCollection);
    $this->assertEquals('bpmn_io', $modeler->getPluginId());
    $this->assertCount(34, $build['#attached']['drupalSettings']['bpmn_io_convert']['elements']);
    $this->assertEquals('StartEvent', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Event_0erz1e4']);
    $this->assertEquals('ExclusiveGateway', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Gateway_1rthid4']);
    $this->assertEquals('SequenceFlow', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Flow_0a1zeo8']);
    $this->assertEquals('Task', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Activity_0tlx3ln']);
    $this->assertEquals('event', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Event_04tl9lk']);
    $this->assertEquals('condition', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Flow_0c7hrjx']);
    $this->assertEquals('action', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Activity_0xd3fam']);
  }

  /**
   * Convert an ECA-entity that uses a non fallback-model.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testConvertNonFallback(): void {
    /** @var \Drupal\eca\Entity\EcaStorage $storage */
    $storage = $this->entityTypeManager->getStorage('eca');
    // Confirm initial state.
    /** @var \Drupal\eca\Entity\Eca[] $ecaCollection */
    $ecaCollection = $storage->loadMultiple();
    $this->assertCount(2, $ecaCollection);

    /** @var \Drupal\eca\Entity\Eca $eca */
    $eca = $storage->load('eca_bpmn_io');
    $owner = $this->modelerApi->findOwner($eca);
    $modeler = $owner->getModeler($eca);
    $this->assertEquals('bpmn_io', $modeler->getPluginId());

    // Convert.
    $build = $modeler->convert($owner, $eca);

    // Assert the original entity.
    $this->assertEquals('bpmn_io', $modeler->getPluginId());
    /** @var \Drupal\eca\Entity\Eca[] $ecaCollection */
    $ecaCollection = $storage->loadMultiple();
    $this->assertCount(2, $ecaCollection);

    $this->assertStringNotContainsString('(clone)', $build['#attached']['drupalSettings']['bpmn_io_convert']['metadata']['label']);

    // Assert build.
    $this->assertCount(34, $build['#attached']['drupalSettings']['bpmn_io_convert']['elements']);
    $this->assertEquals('StartEvent', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Event_00dfxlw']);
    $this->assertEquals('ExclusiveGateway', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Gateway_0hd8858']);
    $this->assertEquals('SequenceFlow', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Flow_1vczt3y']);
    $this->assertEquals('Task', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Activity_0nr4ng5']);
    $this->assertEquals('event', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Event_0erz1e4']);
    $this->assertEquals('condition', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Flow_0xavi4t']);
    $this->assertEquals('action', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Activity_0tlx3ln']);
  }

}
