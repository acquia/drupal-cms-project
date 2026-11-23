<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\google_tag\Entity\TagContainer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests adding Google Tag IDs through the container form.
 *
 * @coversDefaultClass \Drupal\google_tag\Form\TagContainerForm
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class TagContainerFormTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['google_tag'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that every ID entered with "Add another ID" is saved.
   *
   * Clicking a button straight after typing into an ID field blurs the field,
   * firing its change event, within the same click. WebDriver commands are
   * too slow to reproduce that, so the events of each click are fired in a
   * single script, in the order a browser fires them: the Ajax "Add another
   * ID" button acts on mousedown, before the field blurs, while the plain
   * "Save" button submits on click, after it.
   *
   * @see https://www.drupal.org/project/google_tag/issues/3482534
   */
  public function testMultipleIdsAreSaved(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer google_tag_container']));
    $this->drupalGet('admin/config/services/google-tag');

    $assert_session = $this->assertSession();
    $ids = ['G-AAAAAA', 'GTM-BBBBBB', 'AW-CCCCCC', 'G-DDDDDD'];
    $last = array_key_last($ids);
    foreach ($ids as $index => $id) {
      $field = "jQuery('[name=\"accounts[$index][value]\"]')";
      if ($index < $last) {
        $this->getSession()->executeScript("$field.val('$id'); jQuery('[name=\"accounts_wrapper_accounts_add_gtag_id\"]').trigger('mousedown'); $field.trigger('change');");
        $assert_session->assertWaitOnAjaxRequest();
        $this->assertNotNull($assert_session->waitForField('accounts[' . ($index + 1) . '][value]'));
      }
      else {
        $this->getSession()->executeScript("$field.val('$id').trigger('change'); jQuery('[data-drupal-selector=\"edit-submit\"]')[0].click();");
      }
    }
    $this->assertNotNull($assert_session->waitForText('The configuration options have been saved.'));

    $id = $this->config('google_tag.settings')->get('default_google_tag_entity');
    self::assertSame($ids, TagContainer::load($id)->get('tag_container_ids'));
  }

}
