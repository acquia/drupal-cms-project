<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\trash_test\Entity\TrashTestEntity;

/**
 * Tests uninstall validator for Trash.
 */
class TrashUninstallTest extends TrashKernelTestBase {

  /**
   * Tests that Trash cannot be uninstalled if there's an entity in the trash.
   */
  public function testUninstallValidator(): void {
    $entity = TrashTestEntity::create();
    $entity->save();

    // With no entities in trash, we should be able to uninstall.
    $validation_reasons = \Drupal::service('module_installer')->validateUninstall(['trash']);
    $this->assertEquals($validation_reasons, [], 'Trash should be able to be uninstalled, something is preventing it.');

    $entity->delete();

    // Now our uninstall validator should prevent uninstallation since we put
    // something in the trash.
    $validation_reasons = \Drupal::service('module_installer')->validateUninstall(['trash']);
    $this->assertTrue(isset($validation_reasons['trash']) && (string) $validation_reasons['trash'][0] === 'There is deleted content for the <em class="placeholder">Trash test</em> entity type.', 'Trash is allowed to be uninstalled but it should not be.');

    // If we purge the item from the trash, then the validation error should go
    // away.
    \Drupal::service('trash.manager')->executeInTrashContext('ignore', function () use ($entity) {
      $entity->delete();
    });

    $validation_reasons = \Drupal::service('module_installer')->validateUninstall(['trash']);
    $this->assertEquals($validation_reasons, [], 'Trash should be able to be uninstalled, something is preventing it.');
  }

}
