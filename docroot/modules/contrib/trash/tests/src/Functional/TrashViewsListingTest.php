<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\trash_test\Entity\TrashTestEntity;

/**
 * Tests the Views-based trash listing.
 *
 * @group trash
 */
class TrashViewsListingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['trash', 'trash_test', 'views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable trash for trash_test_entity via the UI using a temporary admin.
    $this->drupalLogin($this->drupalCreateUser([
      'administer trash_test',
      'administer trash',
    ]));
    $this->drupalGet('admin/config/content/trash');
    $this->submitForm([
      'enabled_entity_types[trash_test_entity][enabled]' => TRUE,
    ], 'Save configuration');
    $this->drupalLogout();

    // Rebuild the container to pick up the new entity type definition with
    // the 'deleted' field.
    $this->rebuildContainer();

    $this->drupalLogin($this->drupalCreateUser([
      'administer trash_test',
      'access trash',
      'view deleted entities',
      'restore trash_test_entity entities',
      'purge trash_test_entity entities',
    ]));
  }

  /**
   * Tests the Views-based trash listing display, filtering, and sorting.
   */
  public function testTrashViewsListing(): void {
    // Test empty state.
    $this->drupalGet('/admin/content/trash/trash_test_entity');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no deleted');

    // Create test entities. The first entity comes later alphabetically to test
    // that we're sorting by deleted date by default.
    $entity1 = TrashTestEntity::create(['label' => 'Zebra']);
    $entity1->save();
    $entity2 = TrashTestEntity::create(['label' => 'Apple']);
    $entity2->save();

    // Delete the entities (move to trash) with different timestamps to ensure
    // consistent sorting. The TestTime service adds the offset to request time.
    $entity1->delete();
    \Drupal::keyValue('trash_test')->set('time_offset', 100);
    $entity2->delete();

    // Visit the trash overview.
    $this->drupalGet('/admin/content/trash/trash_test_entity');
    $this->assertSession()->statusCodeEquals(200);

    // Verify the View renders with expected content.
    $this->assertSession()->pageTextContains('Zebra');
    $this->assertSession()->pageTextContains('Apple');

    // Verify the table headers are present (Views table style).
    $this->assertSession()->elementExists('css', 'table');
    $this->assertSession()->pageTextContains('Title');
    $this->assertSession()->pageTextContains('Deleted');
    $this->assertSession()->pageTextContains('Operations');

    // Verify operations links are available.
    $this->assertSession()->linkExists('Restore');
    $this->assertSession()->linkExists('Purge');

    // Verify entity label links to the canonical URL with in_trash parameter.
    $expected_url = $entity2->toUrl(options: ['query' => ['in_trash' => 1]])->toString();
    $link = $this->assertSession()->elementExists('css', 'table tbody tr td a');
    $this->assertEquals($expected_url, $link->getAttribute('href'));

    // Verify default sorting by deleted date DESC (most recent first).
    // Apple should appear first even though Zebra comes first alphabetically.
    $page = $this->getSession()->getPage();
    $rows = $page->findAll('css', 'table tbody tr');
    $this->assertCount(2, $rows);
    $first_row_text = $rows[0]->getText();
    $this->assertStringContainsString('Apple', $first_row_text);

    // Test exposed filter.
    $this->submitForm(['label' => 'Zebra'], 'Filter');
    $this->assertSession()->pageTextContains('Zebra');
    $this->assertSession()->pageTextNotContains('Apple');

    // Reset the filter.
    $this->submitForm([], 'Reset');
    $this->assertSession()->pageTextContains('Zebra');
    $this->assertSession()->pageTextContains('Apple');
  }

  /**
   * Tests bulk operations from the Views listing.
   */
  public function testTrashViewsBulkOperations(): void {
    if (version_compare(\Drupal::VERSION, '11', '<')) {
      $this->markTestSkipped('Skipped due to test-only cache invalidation issue.');
    }
    // Create entities for testing bulk operations.
    $entity1 = TrashTestEntity::create(['label' => 'Bulk Entity 1']);
    $entity1->save();
    $entity2 = TrashTestEntity::create(['label' => 'Bulk Entity 2']);
    $entity2->save();
    $entity3 = TrashTestEntity::create(['label' => 'Bulk Entity 3']);
    $entity3->save();

    // Delete them.
    $entity1->delete();
    $entity2->delete();
    $entity3->delete();

    // Visit the trash overview.
    $this->drupalGet('/admin/content/trash/trash_test_entity');

    // Verify bulk form elements are present.
    // The bulk form field is named {entity_type}_bulk_form per core convention.
    $this->assertSession()->elementExists('css', 'select[name="action"]');
    $this->assertSession()->elementExists('css', 'input[type="checkbox"][name^="trash_test_entity_bulk_form"]');

    // Verify restore and purge actions are available in the dropdown.
    $this->assertSession()->optionExists('action', 'trash_test_entity_restore_action');
    $this->assertSession()->optionExists('action', 'trash_test_entity_purge_action');

    // Test bulk restore: select Entity 1 and Entity 2 by finding their rows.
    $this->checkRowByLabel('Bulk Entity 1');
    $this->checkRowByLabel('Bulk Entity 2');
    $this->getSession()->getPage()->selectFieldOption('action', 'trash_test_entity_restore_action');
    $this->getSession()->getPage()->pressButton('Apply to selected items');

    // Confirm the bulk restore.
    $this->assertSession()->pageTextContains('Are you sure you want to restore');
    $this->submitForm([], 'Restore');
    $this->assertSession()->statusMessageContains('Restored 2 items from trash', 'status');

    // Verify only entity3 remains in trash.
    $this->drupalGet('/admin/content/trash/trash_test_entity');
    $this->assertSession()->pageTextNotContains('Bulk Entity 1');
    $this->assertSession()->pageTextNotContains('Bulk Entity 2');
    $this->assertSession()->pageTextContains('Bulk Entity 3');

    // Test bulk purge: select Entity 3.
    $this->checkRowByLabel('Bulk Entity 3');
    $this->getSession()->getPage()->selectFieldOption('action', 'trash_test_entity_purge_action');
    $this->getSession()->getPage()->pressButton('Apply to selected items');

    // Confirm the bulk purge.
    $this->assertSession()->pageTextContains('Are you sure you want to permanently delete');
    $this->submitForm([], 'Permanently delete');
    $this->assertSession()->statusMessageContains('Permanently deleted 1 item', 'status');

    // Verify trash is now empty.
    $this->drupalGet('/admin/content/trash/trash_test_entity');
    $this->assertSession()->pageTextContains('There are no deleted');
  }

  /**
   * Tests restore and purge operations from the Views listing.
   */
  public function testTrashViewsOperations(): void {
    if (version_compare(\Drupal::VERSION, '11', '<')) {
      $this->markTestSkipped('Skipped due to test-only cache invalidation issue.');
    }
    // Create entities for testing operations.
    $entity_to_restore = TrashTestEntity::create(['label' => 'Entity To Restore']);
    $entity_to_restore->save();
    $entity_to_purge = TrashTestEntity::create(['label' => 'Entity To Purge']);
    $entity_to_purge->save();

    // Delete them.
    $entity_to_restore->delete();
    $entity_to_purge->delete();

    // Visit the trash overview.
    $this->drupalGet('/admin/content/trash/trash_test_entity');
    $this->assertSession()->pageTextContains('Entity To Restore');
    $this->assertSession()->pageTextContains('Entity To Purge');

    // Test restore operation by clicking the specific entity's restore link.
    $this->getSession()->getPage()->find('css', 'a[aria-label="Restore Entity To Restore"]')->click();
    $this->submitForm([], 'Confirm');
    $this->assertSession()->statusMessageContains('has been restored from trash', 'status');

    // Verify only purge entity remains in trash.
    $this->drupalGet('/admin/content/trash/trash_test_entity');
    $this->assertSession()->pageTextNotContains('Entity To Restore');
    $this->assertSession()->pageTextContains('Entity To Purge');

    // Test purge operation by clicking the specific entity's purge link.
    $this->getSession()->getPage()->find('css', 'a[aria-label="Purge Entity To Purge"]')->click();
    $this->submitForm([], 'Confirm');
    $this->assertSession()->statusMessageContains('has been permanently deleted', 'status');

    // Verify trash is now empty.
    $this->drupalGet('/admin/content/trash/trash_test_entity');
    $this->assertSession()->pageTextNotContains('Entity To Purge');
    $this->assertSession()->pageTextContains('There are no deleted');
  }

  /**
   * Checks the bulk form checkbox for a row containing the given label.
   *
   * @param string $label
   *   The entity label to find in the table.
   */
  protected function checkRowByLabel(string $label): void {
    $page = $this->getSession()->getPage();
    // Find the table row containing the label, then find its checkbox.
    $row = $page->find('xpath', "//table//tr[contains(., '$label')]");
    $this->assertNotNull($row, "Row containing '$label' not found");
    $checkbox = $row->find('css', 'input[type="checkbox"][name^="trash_test_entity_bulk_form"]');
    $this->assertNotNull($checkbox, "Checkbox in row '$label' not found");
    $checkbox->check();
  }

}
