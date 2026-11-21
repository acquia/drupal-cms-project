<?php

declare(strict_types=1);

namespace Drupal\Tests\linkit\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\linkit\Tests\ProfileCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the linkit form element in a form built with the Form API only.
 *
 * @group linkit
 */
#[RunTestsInSeparateProcesses]
class LinkitFormElementTest extends WebDriverTestBase {

  use ProfileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'linkit',
    'linkit_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests selecting suggestions in linkit elements not named "uri".
   */
  public function testFormElement() {
    $session = $this->getSession();
    $assert_session = $this->assertSession();
    $page = $session->getPage();

    $profile = $this->createProfile();
    $matcher = $this->container->get('plugin.manager.linkit.matcher')->createInstance('entity:entity_test_mul');
    $profile->addMatcher($matcher->getConfiguration());
    $profile->save();

    $entity = EntityTestMul::create(['name' => 'Foo']);
    $entity->save();
    $entity_path = $entity->toUrl()->toString();

    $this->drupalLogin($this->drupalCreateUser(['view test entity']));
    $this->drupalGet(Url::fromRoute('linkit_test.form_element', ['linkit_profile_id' => $profile->id()]));

    // Select a suggestion in a linkit element with no related inputs.
    $button_link = $assert_session->elementExists('css', 'input[name="button_link"]');
    $this->selectFirstSuggestion($button_link, 'Fo');
    $this->assertSame($entity_path, $button_link->getValue());

    // Select a suggestion in a linkit element that is the "href" attribute
    // itself, and check that its sibling attributes are set.
    $href = $assert_session->elementExists('css', 'input[name="attributes[href]"]');
    $this->selectFirstSuggestion($href, 'Fo');
    $this->assertSame($entity_path, $href->getValue());
    $assert_session->hiddenFieldValueEquals('attributes[data-entity-type]', 'entity_test_mul');
    $assert_session->hiddenFieldValueEquals('attributes[data-entity-uuid]', $entity->uuid());
    $assert_session->hiddenFieldValueEquals('attributes[data-entity-substitution]', 'canonical');

    // Select different suggestions in two linkit elements on the same form,
    // and check that each keeps its own attributes.
    $other_entity = EntityTestMul::create(['name' => 'Bar']);
    $other_entity->save();
    $other_entity_path = $other_entity->toUrl()->toString();
    $this->selectFirstSuggestion($assert_session->elementExists('css', 'input[name="link_a[uri]"]'), 'Fo');
    $this->selectFirstSuggestion($assert_session->elementExists('css', 'input[name="link_b[uri]"]'), 'Ba');
    $assert_session->hiddenFieldValueEquals('link_a[attributes][href]', $entity_path);
    $assert_session->hiddenFieldValueEquals('link_a[attributes][data-entity-uuid]', $entity->uuid());
    $assert_session->hiddenFieldValueEquals('link_b[attributes][href]', $other_entity_path);
    $assert_session->hiddenFieldValueEquals('link_b[attributes][data-entity-uuid]', $other_entity->uuid());

    $page->pressButton('Submit');
    $assert_session->pageTextContains("button_link: $entity_path");
    $assert_session->pageTextContains("attributes[href]: $entity_path");
    $assert_session->pageTextContains('attributes[data-entity-type]: entity_test_mul');
    $assert_session->pageTextContains("attributes[data-entity-uuid]: {$entity->uuid()}");
    $assert_session->pageTextContains('attributes[data-entity-substitution]: canonical');
    $assert_session->pageTextContains("link_a[uri]: $entity_path");
    $assert_session->pageTextContains("link_a[attributes][data-entity-uuid]: {$entity->uuid()}");
    $assert_session->pageTextContains("link_b[uri]: $other_entity_path");
    $assert_session->pageTextContains("link_b[attributes][data-entity-uuid]: {$other_entity->uuid()}");
  }

  /**
   * Searches in a linkit element and clicks the first suggestion.
   *
   * @param \Behat\Mink\Element\NodeElement $input
   *   The linkit input element.
   * @param string $search
   *   The text to search for.
   */
  protected function selectFirstSuggestion(NodeElement $input, string $search): void {
    $input->setValue(substr($search, 0, -1));
    $this->getSession()->getDriver()->keyDown($input->getXpath(), substr($search, -1));
    $this->assertSession()->waitOnAutocomplete();
    // Each linkit element has its own suggestions list.
    $suggestions = array_filter(
      $this->getSession()->getPage()->findAll('css', 'ul.linkit-ui-autocomplete li.linkit-result-line span.linkit-result-line--title'),
      fn (NodeElement $suggestion) => $suggestion->isVisible(),
    );
    $this->assertNotEmpty($suggestions);
    reset($suggestions)->click();
  }

}
