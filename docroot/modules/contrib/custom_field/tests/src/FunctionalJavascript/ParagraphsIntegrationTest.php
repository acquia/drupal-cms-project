<?php

namespace Drupal\Tests\custom_field\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\Media;

/**
 * Test cases for paragraphs integration.
 *
 * @group custom_field
 */
class ParagraphsIntegrationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_paragraphs_test',
    'user',
    'system',
    'field',
    'field_ui',
    'text',
    'node',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\Core\Extension\ExtensionList $module_extension_list */
    $module_extension_list = $this->container->get('extension.list.module');

    $fixture_dir = implode(DIRECTORY_SEPARATOR, [
      $module_extension_list->getPath('custom_field'),
      'tests',
      'resources',
      'image',
    ]);

    /** @var \Drupal\file\FileRepositoryInterface $file_repository */
    $file_repository = $this->container->get('file.repository');

    foreach (['image_1.png' => 'Image 1', 'image_2.png' => 'Image 2'] as $file => $name) {
      $fixture = file_get_contents($fixture_dir . DIRECTORY_SEPARATOR . $file);
      $file = $file_repository->writeData($fixture, "public://$file");
      $file->save();
      Media::create([
        'name' => $name,
        'bundle' => 'image',
        'field_media_image' => [
          'target_id' => $file->id(),
          'alt' => '',
        ],
      ])->save();
    }
  }

  /**
   * Adds a card with the provided title and image.
   */
  protected function addCard(string $title, string $image_id): void {
    static $delta = 0;
    $this->getSession()->getPage()->pressButton('Add Card');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();

    $card = $assert->waitForElement('css', '[data-drupal-selector="edit-field-components-' . $delta . '"]');
    $this->assertNotNull($card, 'Card exists.');

    $card->fillField('Title', $title);
    $card->pressButton('Add media');
    $assert->waitForElement('css', '.media-library-widget-modal')
      ->clickLink('Show Image media');

    $assert->assertWaitOnAjaxRequest();

    $modal = $assert->waitForElement('css', '.media-library-widget-modal');
    $modal->checkField('media_library_select_form[' . $image_id . ']');

    $assert->waitForElement('css', '.ui-dialog-buttonset')
      ->pressButton('Insert selected');

    $assert->assertWaitOnAjaxRequest();

    ++$delta;
  }

  /**
   * Runs various assertions on the current page.
   *
   * @param int $delta
   *   The row delta.
   * @param string $title
   *   The expected title.
   * @param string $image_id
   *   The expected image id.
   */
  protected function assertCardData(int $delta, string $title, string $image_id): void {
    $assert = $this->assertSession();

    // Find the desired card delta.
    $row = $assert->elementExists('css', '[data-drupal-selector="edit-field-components-' . $delta . '"]');

    // Expand if necessary.
    if ($edit_button = $row->findButton('Edit')) {
      $edit_button->press();
      $assert->assertWaitOnAjaxRequest();
    }

    $assert->fieldValueEquals('Title', $title, $row);
    $assert->hiddenFieldValueEquals(
    'field_components[' . $delta . '][subform][field_content][0][media][selection][0][target_id]',
      $image_id,
      $row
    );
  }

  /**
   * Test case for paragraphs integration.
   */
  public function testParagraphsIntegration() {

    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    $this->drupalGet('/node/add/page');

    $this->getSession()->getPage()->fillField('Title', 'Test node');

    // Add some cards.
    $this->addCard('Card 1', '1');
    $this->addCard('Card 2', '2');

    // Ensure that no card data was lost.
    $this->assertCardData(0, 'Card 1', '1');
    $this->assertCardData(1, 'Card 2', '2');

    // Save the content.
    $this->submitForm([], 'Save');

    $this->drupalGet('/node/1/edit');

    // Ensure that the card data was properly persisted.
    $this->assertCardData(0, 'Card 1', '1');
    $this->assertCardData(1, 'Card 2', '2');
  }

}
