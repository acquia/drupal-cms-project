<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\FunctionalJavascript;

use Drupal\google_tag\Entity\TagContainer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Ajax parts of the container form.
 *
 * Saving the form without JavaScript is covered by TagContainerUiTest.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class UserInterfaceFunctionalJavascriptTest extends GoogleTagTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'test_page_test',
    'google_tag',
  ];

  /**
   * Tests adding a tag ID with the Ajax "Add another ID" button.
   */
  public function testAddAnotherId(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer google_tag_container']));
    $this->drupalGet('admin/config/services/google-tag');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $page->fillField('accounts[0][value]', 'G-XXXXXX');
    $page->pressButton('Add another ID');
    $this->assertNotNull($assert_session->waitForField('accounts[1][value]'));
    // The first value survives the Ajax rebuild.
    $assert_session->fieldValueEquals('accounts[0][value]', 'G-XXXXXX');
    $page->fillField('accounts[1][value]', 'UA-XXXXXX');
    $page->pressButton('Save');
    $assert_session->pageTextContains('The configuration options have been saved.');

    $containers = TagContainer::loadMultiple();
    $this->assertCount(1, $containers);
    $this->assertSame(['G-XXXXXX', 'UA-XXXXXX'], reset($containers)->get('tag_container_ids'));
  }

}
