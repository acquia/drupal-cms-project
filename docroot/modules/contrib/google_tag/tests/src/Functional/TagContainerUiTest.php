<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests configuring the default container through its form.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class TagContainerUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['google_tag', 'test_page_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer google_tag_container']));
  }

  /**
   * Tests saving one tag ID, then adding a second one.
   */
  public function testTagIds(): void {
    $this->drupalGet('admin/config/services/google-tag');
    $this->submitForm(['accounts[0][value]' => 'G-XXXXXX'], 'Save');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertTagIds('G-XXXXXX', []);

    $this->drupalGet('admin/config/services/google-tag');
    $this->submitForm([], 'Add another ID');
    $this->submitForm(['accounts[1][value]' => 'UA-XXXXXX'], 'Save');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertTagIds('G-XXXXXX', ['UA-XXXXXX']);
  }

  /**
   * Tests that the user role condition limits where the tag is added.
   */
  public function testRoleCondition(): void {
    $this->drupalGet('admin/config/services/google-tag');
    $this->submitForm([
      'accounts[0][value]' => 'G-XXXXXX',
      'conditions[user_role][roles][anonymous]' => TRUE,
    ], 'Save');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    self::assertArrayNotHasKey('gtag', $this->getDrupalSettings());

    $this->drupalLogout();
    $this->drupalGet('test-page');
    $this->assertTagIds('G-XXXXXX', []);
  }

  /**
   * Asserts the tag IDs in the current page's drupalSettings.gtag.
   *
   * @param string $tag_id
   *   The expected default tag ID.
   * @param string[] $other_ids
   *   The expected additional tag IDs.
   */
  private function assertTagIds(string $tag_id, array $other_ids): void {
    $settings = $this->getDrupalSettings();
    self::assertArrayHasKey('gtag', $settings);
    self::assertSame($tag_id, $settings['gtag']['tagId']);
    self::assertSame($other_ids, $settings['gtag']['otherIds']);
  }

}
