<?php

declare(strict_types=1);

namespace Drupal\Tests\sitemap\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Syndicate (sitemap) block.
 *
 * @group sitemap
 * @group legacy
 */
class SitemapSyndicateBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node', 'sitemap'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test the basic Syndicate (sitemap) block functionality.
   */
  public function testSitemapSyndicateBlockBasics(): void {
    // Setup: Log in as a user with no privileges.
    $this->drupalLogin($this->drupalCreateUser());

    // Setup: Place the block on a page.
    $this->drupalPlaceBlock('sitemap_syndicate', [
      'id' => 'sitemap_syndicate_block',
      'label' => 'Syndicate (sitemap)',
    ]);

    // SUT: Visit a page with the block on it.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('Syndicate (sitemap)');
    $this->assertSession()->elementExists('xpath', '//div[@id="block-sitemap-syndicate-block"]/*');

    // Assert: The block contains a feed icon that links to the default feed.
    $this->assertSession()->linkByHrefExists('/rss.xml');

    // Setup: Make up a random path.
    $path0 = \sprintf('/%s', $this->randomMachineName());

    // Setup: Set the block's rss_feed_path.
    $this->container->get('config.factory')
      ->getEditable('block.block.sitemap_syndicate_block')
      ->set('settings.rss_feed_path', $path0)
      ->save();

    // SUT: Visit a page with the block on it.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('Syndicate (sitemap)');
    $this->assertSession()->elementExists('xpath', '//div[@id="block-sitemap-syndicate-block"]/*');

    // Assert: The page contains a feed icon that links to the rss_feed_path.
    $this->assertSession()->linkByHrefExists($path0);

    // Assert: The page no longer contains a link to the default feed.
    $this->assertSession()->linkByHrefNotExists('/rss.xml');
  }

  /**
   * Test that the block configuration form respects permissions.
   */
  public function testSitemapSyndicateBlockPermissions(): void {
    // Setup: Place the block on a page.
    $block0 = $this->drupalPlaceBlock('sitemap_syndicate', [
      'id' => 'sitemap_syndicate_block',
      'label' => 'Syndicate (sitemap)',
    ]);

    // Setup: Log in as a user with permission to administer blocks only.
    $this->drupalLogin($this->createUser([
      'administer blocks',
    ]));

    // SUT: Edit the block.
    $this->drupalGet(Url::fromRoute('entity.block.edit_form', [
      'block' => 'sitemap_syndicate_block',
    ]));

    // Assert: The rss_feed_path control is not visible.
    $this->assertSession()->fieldNotExists('settings[rss_feed_path]');

    // Setup: Make up a random path.
    $path0 = \sprintf('/%s', $this->randomMachineName());

    // Setup: Log in as a user with permission to administer blocks and set the
    // front page rss link on the sitemap.
    $this->drupalLogin($this->createUser([
      'administer blocks',
      'set front page rss link on sitemap',
    ]));

    // SUT: Edit the block.
    $this->drupalGet(Url::fromRoute('entity.block.edit_form', [
      'block' => 'sitemap_syndicate_block',
    ]));

    // Assert: The rss_feed_path control is visible.
    $this->assertSession()->fieldExists('settings[rss_feed_path]');

    // SUT: Change the rss_feed_path, save the block.
    $this->submitForm([
      'settings[rss_feed_path]' => $path0,
    ], 'Save block');

    // SUT: Visit a page with the block on it.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('Syndicate (sitemap)');
    $this->assertSession()->elementExists('xpath', '//div[@id="block-sitemap-syndicate-block"]/*');

    // Assert: The page contains a feed icon that links to the rss_feed_path.
    $this->assertSession()->linkByHrefExists($path0);

    // Assert: The page no longer contains a link to the default feed.
    $this->assertSession()->linkByHrefNotExists('/rss.xml');
  }

}
