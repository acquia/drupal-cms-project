<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_starterkit_remote_video\Functional;

use Drupal\Component\Utility\SortArray;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests the Acquia Starter Kit Remote Video media type recipe.
 *
 * @group acquia_starterkit_remote_video
 */
class MediaRemoteVideoTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ckeditor5'];

  /**
   * Verify the Remote Video media type by adding media.
   */
  public function testRemoteVideo(): void {
    $dir = realpath(__DIR__ . '/../../..');
    $this->applyRecipe($dir);

    $this->drupalLogin($this->rootUser);

    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Add Categories vocabulary terms to the select list.
    $this->drupalGet("admin/structure/taxonomy/manage/categories/add");
    $page->fillField('Name', 'Music');
    $page->pressButton('Save');
    $assert_session->pageTextContains('Created new term Music.');

    $this->drupalGet("/media/add/remote_video");
    $assert_session->statusCodeEquals(200);

    // Assert that the expected fields show up.
    $assert_session->fieldExists('Name');
    $assert_session->fieldExists('Video URL');
    $assert_session->fieldExists('Categories');
    $assert_session->fieldExists('Tags');

    // The standard Categories and Tags fields should be present.
    $group = $assert_session->elementExists('css', '#edit-group-taxonomy');

    $tags = $assert_session->fieldExists('Tags', $group);
    $this->assertTrue($tags->hasAttribute('data-autocomplete-path'));

    $categories = $assert_session->selectExists('Categories', $group);
    // No item added to the select list.
    $this->assertTrue($categories->hasAttribute('multiple'));

    // Ensure that the select list has every term in the Categories vocabulary.
    $terms = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => 'categories',
      ]);

    /** @var \Drupal\taxonomy\TermInterface $term */
    foreach ($terms as $term) {
      $assert_session->optionExists('Categories', $term->label(), $group);
    }

    // Assert that the fields are in the correct order.
    $this->assertFieldsOrder([
      'name',
      'field_media_oembed_video',
      'field_categories',
      'field_tags',
    ]);

    // Submit the form and ensure that we see the expected error message(s).
    $page->pressButton('Save');
    $assert_session->pageTextContains('Name field is required.');

    // Fill in the required fields and assert that things went as expected.
    $page->fillField('Name', 'Drupal 8 Beginners, Lesson 01: Intro to the Course');
    $page->fillField('Video URL', 'https://www.youtube.com/watch?v=6e8QyfvQMmU&list=PLpVC00PAQQxHzlDeQvCNDKkyKRV1G3_vT');
    // For convenience, the parent class creates a few categories during set-up.
    // @see \Drupal\Tests\acquia_cms_common\Functional\ContentModelTestBase::setUp()
    $page->selectFieldOption('Categories', 'Music');
    $page->fillField('Tags', 'Techno');
    $page->pressButton('Save');
    $assert_session->pageTextContains('Video Drupal 8 Beginners, Lesson 01: Intro to the Course has been created.');

    // Assert that the techno tag was created dynamically in the correct
    // vocabulary.
    /** @var \Drupal\taxonomy\TermInterface $tag */
    $tag = Term::load(2);
    $this->assertInstanceOf(Term::class, $tag);
    $this->assertSame('tags', $tag->bundle());
    $this->assertSame('Techno', $tag->getName());

    // Media items are not normally exposed at standalone URLs, so assert that
    // the URL alias field does not show up.
    $assert_session->fieldNotExists('path[0][alias]');
  }

  /**
   * Asserts that the fields are in the correct order.
   *
   * @param string[] $expected_order
   *   The machine names of the fields we expect in media type's form display,
   *   in the order we expect them to have.
   */
  protected function assertFieldsOrder(array $expected_order): void {
    $components = $this->container->get('entity_display.repository')
      ->getFormDisplay('media', 'remote_video')
      ->getComponents();

    $this->assertDisplayComponentsOrder($components, $expected_order, "The fields of the 'remote_video' media type's edit form were not in the expected order.");
  }

  /**
   * Asserts that the components of an entity display are in a specific order.
   *
   * @param array[] $components
   *   The components in the entity display.
   * @param string[] $expected_order
   *   The components' keys, in the expected order.
   * @param string $message
   *   (optional) A message if the assertion fails.
   */
  protected function assertDisplayComponentsOrder(array $components, array $expected_order, string $message = ''): void {
    uasort($components, SortArray::class . '::sortByWeightElement');
    $components = array_intersect(array_keys($components), $expected_order);
    $this->assertSame($expected_order, array_values($components), $message);
  }

}
