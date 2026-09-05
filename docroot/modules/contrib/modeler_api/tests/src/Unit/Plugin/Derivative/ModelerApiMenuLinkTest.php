<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Unit\Plugin\Derivative;

use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\Derivative\ModelerApiMenuLink;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests the menu link derivative definitions for model owners.
 *
 * The deriver builds one collection link per model owner, plus an "Import" and
 * a "Settings" child link. A child link attaches to the menu tree only when its
 * "parent" key points at an existing menu link plugin ID. The bare derivative
 * key "entity.<type>.collection" is not a plugin ID, so the child links ended
 * up orphaned. The deriver must therefore set the parent to the full plugin ID
 * "modeler_api:entity.<type>.collection".
 */
#[CoversClass(ModelerApiMenuLink::class)]
#[Group('modeler_api')]
class ModelerApiMenuLinkTest extends UnitTestCase {

  /**
   * Tests that child links use the full plugin ID of the collection as parent.
   *
   * The collection derivative is registered under the key
   * "entity.my_type.collection" but its plugin ID is
   * "modeler_api:entity.my_type.collection". The Import and Settings children
   * must use the full plugin ID as their parent, never the bare derivative key.
   */
  public function testChildDerivativesUseFullPluginIdAsParent(): void {
    $owner = $this->createStub(ModelOwnerInterface::class);
    $owner->method('configEntityTypeId')->willReturn('my_type');
    $owner->method('configEntityBasePath')->willReturn('admin/config/foo');
    $owner->method('label')->willReturn('My Type');
    $owner->method('description')->willReturn('Test owner');

    $manager = $this->createStub(ModelOwnerPluginManager::class);
    $manager->method('getAllInstances')->willReturn([$owner]);

    $route = new Route('/admin/config/foo');
    $api = $this->createStub(Api::class);
    $api->method('getRouteByName')->willReturn($route);
    $api->method('getParentMenuName')->willReturn('system.admin_config_workflow');

    $deriver = new ModelerApiMenuLink($manager, $api);
    // The base plugin ID is normally assigned by create() from the menu link
    // definition. Assign it directly here because the deriver is instantiated
    // without the container.
    $reflection = new \ReflectionClass(ModelerApiMenuLink::class);
    $reflection->getProperty('basePluginId')->setValue($deriver, 'modeler_api');
    $deriver->setStringTranslation($this->getStringTranslationStub());

    $definitions = $deriver->getDerivativeDefinitions(['id' => 'modeler_api']);

    // The collection link resolves its own parent from the base path.
    $this->assertArrayHasKey('entity.my_type.collection', $definitions);
    $this->assertSame('system.admin_config_workflow', $definitions['entity.my_type.collection']['parent']);

    // The child links must point at the full plugin ID of the collection.
    $this->assertArrayHasKey('entity.my_type.import', $definitions);
    $this->assertSame('modeler_api:entity.my_type.collection', $definitions['entity.my_type.import']['parent']);
    $this->assertArrayHasKey('entity.my_type.settings', $definitions);
    $this->assertSame('modeler_api:entity.my_type.collection', $definitions['entity.my_type.settings']['parent']);

    // The bare derivative key is not a plugin ID and must never be used.
    $this->assertNotSame('entity.my_type.collection', $definitions['entity.my_type.import']['parent']);
    $this->assertNotSame('entity.my_type.collection', $definitions['entity.my_type.settings']['parent']);
  }

}
