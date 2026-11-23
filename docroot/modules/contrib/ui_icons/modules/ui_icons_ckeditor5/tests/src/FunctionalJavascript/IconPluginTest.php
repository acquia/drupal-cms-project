<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_ckeditor5\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\ckeditor5\Plugin\Editor\CKEditor5;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Symfony\Component\Validator\ConstraintViolation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the UI icons CKEditor features.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[Group('ui_icons')]
class IconPluginTest extends WebDriverTestBase {

  use CKEditor5TestTrait;

  /**
   * Icon pack from ui_icons_test module.
   */
  private const TEST_ICON_PACK_ID = 'test_path';

  /**
   * Icon from ui_icons_test module.
   */
  private const TEST_ICON_ID_1 = 'foo';

  /**
   * Icon filename from ui_icons_test module.
   */
  private const TEST_ICON_FILENAME_1 = 'foo.png';

  /**
   * Icon class from ui_icons_test module.
   */
  private const TEST_ICON_CLASS_1 = 'icon icon-foo';

  /**
   * Icon from ui_icons_test module.
   */
  private const TEST_ICON_ID_2 = 'bar';

  /**
   * Icon filename from ui_icons_test module.
   */
  private const TEST_ICON_FILENAME_2 = 'bar.png';

  /**
   * Icon class from ui_icons_test module.
   */
  private const TEST_ICON_CLASS_2 = 'icon icon-bar';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ui_icons',
    'ui_icons_ckeditor5',
    'ui_icons_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'icon_embed' => [
          'status' => TRUE,
          'allowed_icon_pack' => [self::TEST_ICON_PACK_ID => self::TEST_ICON_PACK_ID],
        ],
      ],
    ])->save();
    Editor::create([
      'editor' => 'ckeditor5',
      'format' => 'test_format',
      'image_upload' => [
        'status' => FALSE,
      ],
      'settings' => [
        'toolbar' => [
          'items' => [
            'icon',
            'sourceEditing',
          ],
        ],
        'plugins' => [
          'ckeditor5_sourceEditing' => [
            'allowed_tags' => [],
          ],
        ],
      ],
    ])->save();

    $this->assertSame([], array_map(
      function (ConstraintViolation $v) {
        return (string) $v->getMessage();
      },
      iterator_to_array(CKEditor5::validatePair(
        Editor::load('test_format'),
        FilterFormat::load('test_format')
      ))
    ));

    $this->drupalCreateContentType(['type' => 'page']);

    $this->drupalLogin($this->drupalCreateUser([
      'administer filters',
      'create page content',
      'edit own page content',
      'use text format test_format',
      'bypass node access',
    ]));
  }

  /**
   * Provide values for ::testIconPlugin.
   */
  public static function providerIconPlugin(): array {
    $icon_full_id_1 = IconDefinition::createIconId(self::TEST_ICON_PACK_ID, self::TEST_ICON_ID_1);
    $icon_full_id_2 = IconDefinition::createIconId(self::TEST_ICON_PACK_ID, self::TEST_ICON_ID_2);

    return [
      'icon with default settings' => [
        'icon_id' => $icon_full_id_1,
        'icon_class' => self::TEST_ICON_CLASS_1,
        'icon_filename' => self::TEST_ICON_FILENAME_1,
        'fill_settings' => FALSE,
        // @see tests/modules/ui_icons_test/ui_icons_test.ui_icons.yml
        'settings' => [
          'width' => 32,
          'height' => 33,
          'title' => 'Default title',
        ],
      ],
      'icon with changed settings' => [
        'icon_id' => $icon_full_id_2,
        'icon_class' => self::TEST_ICON_CLASS_2,
        'icon_filename' => self::TEST_ICON_FILENAME_2,
        'fill_settings' => TRUE,
        'settings' => [
          'width' => 98,
          'height' => 99,
          'title' => 'Test title',
        ],
      ],
    ];
  }

  /**
   * Test the CKEditor icon plugin.
   */
  #[DataProvider('providerIconPlugin')]
  public function testIconPlugin(string $icon_id, string $icon_class, string $icon_filename, bool $fill_settings, array $settings): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('node/add/page');
    $this->waitForEditor();

    // Ensure that CKEditor 5 is focused.
    $this->click('.ck-content');

    $this->assertEditorButtonEnabled('Insert Icon');
    $this->pressEditorButton('Insert Icon');

    // Our modal appear with input selector.
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-modal'));
    $input = $assert_session->waitForElementVisible('css', '[name="icon[icon_id]"]');
    $this->assertNotNull($input);

    // Make sure the input field can have focus and we can type into it.
    $input->setValue($icon_id);

    $assert_session->assertExpectedAjaxRequest(2);

    // @phpcs:disable
    // @todo test autocomplete list result?
    // $this->getSession()->getDriver()->keyDown($input->getXpath(), ' ');
    // $this->assertSession()->waitOnAutocomplete();
    // $suggestions_markup = $page->find('css', 'ul.ui-autocomplete')->getHtml();
    // $this->assertStringContainsString('', $suggestions_markup);
    // @phpcs:enable

    $icon_preview = $assert_session->elementExists('css', '.ui-icons-preview-icon img');

    $this->assertNotNull($icon_preview);
    // Autocomplete preview has own settings and preview class.
    $this->assertIconValues($icon_preview, $icon_filename, 'icon icon-preview');

    if (TRUE === $fill_settings) {
      // Need to open settings to be able to interact.
      $page->find('css', '.ui-icons-settings-wrapper details summary')->click();
      $setting_name = '[name="icon[icon_settings][%s][%s]"]';
      // Fill settings with value, printed and form id are not the same.
      foreach ($settings as $key => $value) {
        $assert_session->elementExists('css', sprintf($setting_name, self::TEST_ICON_PACK_ID, $key))->setValue($value);
      }
    }

    $assert_session->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Save');

    // Check the preview ajax request to display icon in CKEditor.
    $assert_session->assertExpectedAjaxRequest(5);
    $icon_ckeditor_preview = $assert_session->waitForElementVisible('css', '.ck-content .drupal-icon span img');

    $this->assertNotNull($icon_ckeditor_preview);
    $this->assertIconValues($icon_ckeditor_preview, $icon_filename, $icon_class, $settings);

    // Check the text filter <drupal-icon> inserted properly.
    $xpath = new \DOMXPath($this->getEditorDataAsDom());
    $drupal_icon = $xpath->query('//drupal-icon')[0];
    $this->assertSame($icon_id, $drupal_icon->getAttribute('data-icon-id'));

    // Compare settings in the html.
    $data_icon_settings = json_decode($drupal_icon->getAttribute('data-icon-settings'), TRUE);
    foreach ($settings as $key => $setting) {
      // Because of json we lost types.
      $this->assertSame((string) $data_icon_settings[$key], (string) $setting);
    }

    $this->submitForm([
      'title[0][value]' => 'My test content',
    ], 'Save');
    $assert_session->pageTextContains('page My test content has been created');

    $display_icon = $assert_session->elementExists('css', '.drupal-icon img');

    $this->assertNotNull($display_icon);
    $this->assertIconValues($display_icon, $icon_filename, $icon_class, $settings);
  }

  /**
   * Test icon values.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The NodeElement whose icon values are to be asserted.
   * @param string $filename
   *   The expected filename that the 'src' attribute should end with.
   * @param string $class
   *   The expected class that the 'class' attribute should match.
   * @param array $settings
   *   An associative array of additional attributes and their expected values.
   */
  private function assertIconValues(NodeElement $element, string $filename, string $class, array $settings = []): void {
    $this->assertStringEndsWith($filename, $element->getAttribute('src'));
    $this->assertEquals($class, $element->getAttribute('class'));
    foreach ($settings as $key => $expected) {
      $this->assertSame((string) $expected, (string) $element->getAttribute($key));
    }
  }

}
