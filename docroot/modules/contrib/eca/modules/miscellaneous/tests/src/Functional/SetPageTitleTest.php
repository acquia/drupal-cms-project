<?php

declare(strict_types=1);

namespace Drupal\Tests\eca_misc\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\eca\Entity\Eca;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the "eca_misc_set_page_title" action.
 *
 * These tests prove that the ECA page title override wins on real page types
 * that defeated earlier approaches: an entity view page (node canonical) and a
 * views page (admin/content). Both the HTML title element and the on-page
 * heading must show the ECA-defined title.
 */
#[Group('eca')]
#[Group('eca_misc')]
#[RunTestsInSeparateProcesses]
class SetPageTitleTest extends BrowserTestBase {

  /**
   * The ECA-defined title that the test model applies on every request.
   */
  protected const OVERRIDDEN_TITLE = 'ECA overridden title';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'views',
    'eca',
    'eca_misc',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    Eca::setTesting();
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->createPageTitleModel();
  }

  /**
   * Tests that the model is installed and enabled.
   */
  public function testModelIsInstalled(): void {
    $eca = Eca::load('eca_test_set_page_title');
    $this->assertNotNull($eca, 'The test ECA model is installed.');
    $this->assertTrue($eca->status(), 'The test ECA model is enabled.');
  }

  /**
   * Tests the title override on an entity view page (node canonical).
   */
  public function testTitleOnNodeViewPage(): void {
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Original node title',
    ]);

    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    // The HTML <title> element must contain the ECA title, not the node label.
    $this->assertSession()->titleEquals(self::OVERRIDDEN_TITLE . ' | Drupal');
    // The on-page H1 must show the ECA title.
    $this->assertSession()->elementTextContains('css', 'h1', self::OVERRIDDEN_TITLE);
    $this->assertSession()->pageTextNotContains('Original node title');
  }

  /**
   * Tests the title override on a views page (admin/content).
   */
  public function testTitleOnViewsPage(): void {
    $admin = $this->drupalCreateUser([
      'access content overview',
      'access administration pages',
    ]);
    $this->drupalLogin($admin);

    $this->drupalGet('admin/content');
    $this->assertSession()->statusCodeEquals(200);

    // The views page title ("Content") must be overridden by the ECA title in
    // both the <title> element and the on-page heading.
    $this->assertSession()->titleEquals(self::OVERRIDDEN_TITLE . ' | Drupal');
    $this->assertSession()->elementTextContains('css', 'h1', self::OVERRIDDEN_TITLE);
  }

  /**
   * Tests the override does not affect pages once the model is disabled.
   */
  public function testNoOverrideWhenModelDisabled(): void {
    $eca = Eca::load('eca_test_set_page_title');
    $eca->disable()->save();

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Untouched node title',
    ]);

    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'h1', 'Untouched node title');
    $this->assertSession()->pageTextNotContains(self::OVERRIDDEN_TITLE);
  }

  /**
   * Creates and saves an ECA model that sets the page title on each request.
   *
   * The model is created after site installation (rather than via a module's
   * config/install) to avoid interfering with the action plugin discovery that
   * runs while core's default action config is being installed.
   */
  protected function createPageTitleModel(): void {
    $eca = Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_test_set_page_title',
      'label' => 'Set page title',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'request' => [
          'plugin' => 'kernel:request',
          'label' => 'Start dispatching request',
          'configuration' => [],
          'successors' => [
            ['id' => 'set_title', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_title' => [
          'plugin' => 'eca_misc_set_page_title',
          'label' => 'Set page title',
          'configuration' => [
            'title' => self::OVERRIDDEN_TITLE,
          ],
          'successors' => [],
        ],
      ],
    ]);
    $eca->save();
  }

}
