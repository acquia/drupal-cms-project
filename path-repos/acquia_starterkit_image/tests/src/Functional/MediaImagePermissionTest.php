<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_starterkit_image\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Acquia Starter Kit Image media type recipe permission.
 *
 * @group acquia_starterkit_image
 */
class MediaImagePermissionTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Verify the Image media type and role permissions.
   */
  public function testRolePermissions(): void {
    $dir = realpath(__DIR__ . '/../../..');

    // Read recipe file.
    $file = $dir . '/recipe.yml';
    $this->assertFileExists($file);
    $contents = file_get_contents($file);
    $contents = Yaml::decode($contents);
    $conditional_roles = $contents['config']['actions']['media.type.image']['setThirdPartySettings'][0]['value'];

    // Create roles.
    foreach ($conditional_roles as $role => $permissions) {
      $this->drupalCreateRole([], $role, $role);
    }

    // Apply recipe.
    $this->applyRecipe($dir);

    // Get Audio media config.
    $media = $this->container->get('config.factory')->get('media.type.image');

    // Get the third-party settings for the roles.
    $third_party_settings = $media->get('third_party_settings.acquia_starterkit_core.roles_permissions');
    $this->assertNotEmpty($third_party_settings);

    // Load the role storage.
    $role_storage = $this->container->get('entity_type.manager')->getStorage('user_role');
    foreach ($third_party_settings as $role => $permissions) {
      $this->assertNotEmpty($permissions['grant_permissions']);
      $this->assertCount(count($permissions['grant_permissions']), array_intersect($role_storage->load($role)->getPermissions(), $permissions['grant_permissions']));
    }
  }

}
