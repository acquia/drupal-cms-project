<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\trash_test\Entity\TrashTestEntity;

/**
 * Tests unique field validation during entity restoration.
 *
 * @group trash
 */
class TrashUniqueFieldTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['trash', 'trash_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to manage trash test entities.
   */
  protected AccountInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // First, enable trash for trash_test_entity via the UI using a temporary
    // admin user.
    $setupUser = $this->drupalCreateUser([
      'administer trash_test',
      'administer trash',
    ]);
    $this->drupalLogin($setupUser);
    $this->drupalGet('admin/config/content/trash');
    $this->submitForm([
      'enabled_entity_types[trash_test_entity][enabled]' => TRUE,
    ], 'Save configuration');
    $this->drupalLogout();

    // Rebuild the container to pick up the new entity type definition with
    // the 'deleted' field.
    $this->rebuildContainer();

    // Now create the admin user with all the trash permissions.
    $this->adminUser = $this->drupalCreateUser([
      'administer trash_test',
      'access trash',
      'view deleted entities',
      'restore trash_test_entity entities',
      'purge trash_test_entity entities',
    ]);
  }

  /**
   * Tests that restoring an entity with a conflicting unique field fails.
   */
  public function testRestoreWithUniqueFieldConflict(): void {
    $this->drupalLogin($this->adminUser);

    // Create an entity with a unique code. We're intentionally setting an empty
    // value for the required field in order to check that only unique field
    // violations are taken into account.
    $entity1 = TrashTestEntity::create([
      'label' => 'Entity 1',
      'required_field' => '',
      'unique_code' => 'UNIQUE-CODE-123',
    ]);
    $entity1->save();

    // Delete entity1 (moves to trash).
    $entity1->delete();

    // Create another entity with the same unique code.
    $entity2 = TrashTestEntity::create([
      'label' => 'Entity 2',
      'required_field' => '',
      'unique_code' => 'UNIQUE-CODE-123',
    ]);
    $entity2->save();

    // Try to restore entity1 via the restore form.
    $this->drupalGet('/admin/content/trash/trash_test_entity');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Restore');
    $this->clickLink('Restore');

    // Submit the restore form.
    $this->submitForm([], 'Confirm');

    // Verify that restoration failed with a validation error.
    $this->assertSession()->pageTextContains('already exists');

    // Verify that entity1 is still in the trash.
    $this->drupalGet('/admin/content/trash/trash_test_entity');
    $this->assertSession()->pageTextContains('Entity 1');
  }

  /**
   * Tests that creating an entity with a value matching trashed content fails.
   */
  public function testCreateWithTrashedConflict(): void {
    $this->drupalLogin($this->adminUser);

    // Create an entity with a unique code and delete it.
    $entity = TrashTestEntity::create([
      'label' => 'Entity 1',
      'unique_code' => 'UNIQUE-CODE-789',
    ]);
    $entity->save();
    $entity->delete();

    // Try to create another entity with the same unique code.
    $entity2 = TrashTestEntity::create([
      'label' => 'Entity 2',
      'unique_code' => 'UNIQUE-CODE-789',
    ]);

    // Validation should fail because the value conflicts with trashed content.
    $violations = $entity2->validate();
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsString('already exists', (string) $violations->get(0)->getMessage());
  }

}
