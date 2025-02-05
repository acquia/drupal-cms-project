<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_starterkit_search\Functional;

use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Acquia Starter Kit Search recipe.
 *
 * @group acquia_starterkit_search
 */
class SearchTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['menu_ui'];

  /**
   * Tests the Search functionality.
   */
  public function testSearch(): void {
    $dir = realpath(__DIR__ . '/../../..');
    $this->applyRecipe($dir);
    $this->drupalLogin($this->rootUser);
    $assert_session = $this->assertSession();

    // By default, Search server index id is database.
    $this->drupalGet('admin/config/search/search-api');
    $assert_session->statusCodeEquals(200);
    $assert_session->linkExists('Database Search Server');

    // Check if contents are indexed.
    $this->drupalGet('admin/config/search/search-api/index/content');
    $assert_session->statusCodeEquals(200);

    // Check if content fields are indexed.
    $this->drupalGet('admin/config/search/search-api/index/content/fields');
    $assert_session->statusCodeEquals(200);
  }

}
