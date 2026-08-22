<?php

declare(strict_types=1);

namespace Drupal\Tests\drupical\FunctionalJavascript;

use Drupal\block\BlockInterface;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Test the Events Feed block visibility and permissions.
 *
 * @group drupical
 */
class EventsBlockTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'drupical',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The events block instance.
   *
   * @var \Drupal\block\BlockInterface
   */
  protected BlockInterface $eventsBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the events block.
    $this->eventsBlock = $this->placeBlock('events_block', [
      'label' => 'Drupal Events',
    ]);
  }

  /**
   * Tests events block visibility based on permissions.
   */
  public function testEventsBlockPermissions(): void {
    // User with "access events" permission.
    $account = $this->drupalCreateUser([
      'access events',
    ]);
    $anonymous_account = new AnonymousUserSession();

    $this->drupalLogin($account);
    $this->drupalGet('<front>');

    $assert_session = $this->assertSession();

    // Block should be visible for the user with permission.
    $assert_session->pageTextContains('Drupal Events');

    // Block is not accessible without permission.
    $this->drupalLogout();
    $assert_session->pageTextNotContains('Drupal Events');

    // Test access() method return type.
    $this->assertTrue($this->eventsBlock->getPlugin()->access($account));
    $this->assertInstanceOf(AccessResultAllowed::class, $this->eventsBlock->getPlugin()->access($account, TRUE));

    $this->assertFalse($this->eventsBlock->getPlugin()->access($anonymous_account));
    $this->assertInstanceOf(AccessResultNeutral::class, $this->eventsBlock->getPlugin()->access($anonymous_account, TRUE));
  }

}
