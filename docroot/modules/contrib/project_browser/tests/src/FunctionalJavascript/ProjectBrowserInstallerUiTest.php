<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\contact\Entity\ContactForm;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\State\StateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Url;
use Drupal\project_browser\Controller\InstallerController;
use PHPUnit\Framework\Attributes\CoversClass;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\project_browser\InstallProgress;
use Drupal\project_browser_test\TestActivator;
use Drupal\Tests\project_browser\Traits\PackageManagerFixtureUtilityTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Provides tests for the Project Browser Installer UI.
 *
 * @group project_browser
 */
#[CoversClass(InstallerController::class)]
#[Group('project_browser')]
final class ProjectBrowserInstallerUiTest extends WebDriverTestBase {

  use ProjectBrowserUiTestTrait, PackageManagerFixtureUtilityTrait;

  /**
   * The install state service.
   *
   * @var \Drupal\project_browser\InstallProgress
   */
  private InstallProgress $installState;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'package_manager_bypass',
    'package_manager',
    'package_manager_test_validation',
    'project_browser',
    'project_browser_test',
    'dblog',
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

    $this->initPackageManager();

    /** @var \Drupal\project_browser\InstallProgress $install_state */
    $install_state = $this->container->get(InstallProgress::class);
    $this->installState = $install_state;

    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'project_browser_test_mock' => [],
        'drupal_core' => [],
        'recipes' => [
          'additional_directories' => [
            __DIR__ . '/../../fixtures',
          ],
        ],
      ])
      ->set('allow_ui_install', TRUE)
      ->set('max_selections', 1)
      ->save();
    $this->drupalLogin($this->drupalCreateUser([
      'administer modules',
      'administer site configuration',
      'access site reports',
    ]));
  }

  /**
   * Tests the "select" button functionality.
   */
  public function testSingleModuleAddAndInstall(): void {
    TestActivator::handle('drupal/cream_cheese');

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->installProject('Cream cheese on a bagel');

    // The activator in project_browser_test should have logged the unqualified
    // project ID.
    // @see \Drupal\project_browser_test\TestActivator
    $this->assertContains('cream_cheese', $this->container->get(StateInterface::class)->get('test activator'));
  }

  /**
   * Tests already added project install functionality.
   *
   * This scenario is not possible if only the Project
   * Browser UI is used, but could happen if the module was added differently,
   * such as via the terminal with Compose or a direct file addition.
   */
  public function testInstallModuleAlreadyInFilesystem(): void {
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');

    $card = $this->waitForProject('Pinky and the Brain');
    $project_id = $card->getAttribute('data-project-id');
    $card->pressButton('Install Pinky and the Brain');

    // Scroll the card to the top of the viewport so we can test that we are
    // scrolled to the messages area when the activation fails.
    $session = $this->getSession();
    $session->executeScript("document.querySelector('[data-project-id=\"$project_id\"]').scrollIntoView();");
    $card_y_position = (int) $session->evaluateScript('return window.scrollY;');
    $this->assertGreaterThan(0, $card_y_position);
    // The Pinky and the Brain module doesn't actually exist in the filesystem,
    // but the test activator pretends it does, in order to test the presence
    // of the "Install" button as opposed vs. the default "Add and Install"
    // button. This happens to be a good way to test mid-install exceptions as
    // well.
    // @see \Drupal\project_browser_test\TestActivator::getStatus()
    $message = 'Unable to install modules pinky_brain due to missing modules pinky_brain';
    $this->assertPageHasText($message, 30000);
    $this->assertSession()->statusMessageContains($message, 'error');

    // We should have been scrolled to the messages area, which is above the
    // card.
    $session->wait(1000);
    $this->assertLessThan($card_y_position, (int) $session->evaluateScript('return window.scrollY;'));
  }

  /**
   * Tests applying a recipe from the project browser UI.
   */
  public function testApplyRecipe(): void {
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/modules/browse/recipes');
    $this->svelteInitHelper('css', '.pb-projects-list');
    $this->searchFor('image');

    // Apply a recipe that ships with core.
    $card = $this->waitForProject('Image media type');
    $card->pressButton('Install');
    $this->waitForProjectToBeInstalled($card);

    // If we reload, the installation status should be remembered.
    $this->getSession()->reload();
    $this->searchFor('image');
    $card = $this->waitForProject('Image media type');
    $this->waitForProjectToBeInstalled($card);

    // Apply a recipe that requires user input.
    $this->searchFor('test');
    $card = $this->waitForProject('Test Recipe');
    $this->waitForProject('Test Recipe')->pressButton('Install');
    $card->pressButton('Install');

    // The input form should appear in a modal dialog.
    $modal = $this->assertElementIsVisible('css', '#drupal-modal');
    $modal->fillField('test_recipe[new_name]', 'Y halo thar!');
    $this->assertSession()
      ->elementTextContains('css', '.ui-dialog-title', 'Test Recipe');
    $page->find('css', '.ui-dialog-buttonpane')?->pressButton('Continue');
    // Wait for the modal to vanish and confirm that the recipe did its job.
    $this->assertTrue($modal->waitFor(10, fn (NodeElement $modal) => !$modal->isValid()));
    $this->assertSame('Y halo thar!', $this->config('system.site')->get('name'));

    // A checkpoint should have been created. It's not available to the PHPUnit
    // test runner, but project_browser_test records it for us.
    $checkpoint_name = $this->container->get(StateInterface::class)
      ->get('project_browser_test.checkpoint_name');
    $this->assertSame('Project Browser checkpoint for Test Recipe', $checkpoint_name);

    // The recipe should be marked as applied, but we should be able to reapply
    // it.
    $this->waitForProjectToBeInstalled($card);
    $card->clickLink('Reapply');

    // The input form should appear, again, in a modal dialog.
    $modal = $this->assertElementIsVisible('css', '#drupal-modal');
    $modal->fillField('test_recipe[new_name]', 'Apply, apply again');
    $page->find('css', '.ui-dialog-buttonpane')?->pressButton('Continue');
    // Wait for the modal to vanish and confirm that the recipe did its job...
    // again.
    $this->assertTrue($modal->waitFor(10, fn (NodeElement $modal) => !$modal->isValid()));
    // Clear all caches so that our test environment reflects the state of the
    // test site.
    $this->resetAll();
    $this->assertSame('Apply, apply again', $this->config('system.site')->get('name'));
    $this->waitForProjectToBeInstalled($card);
  }

  /**
   * Tests install UI not available if not enabled.
   */
  public function testAllowUiInstall(): void {
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');

    $cream_cheese = $this->waitForProject('Cream cheese on a bagel');
    $this->assertTrue($cream_cheese->hasButton('Install Cream cheese on a bagel'));
    $this->config('project_browser.admin_settings')
      ->set('allow_ui_install', FALSE)
      ->save();
    $this->getSession()->reload();
    $cream_cheese = $this->waitForProject('Cream cheese on a bagel');
    $this->assertTrue($cream_cheese->hasButton('View Commands for Cream cheese on a bagel'));
  }

  /**
   * Confirms sandbox can be unlocked despite a missing Project Browser lock.
   *
   * @legacy-covers ::unlock
   */
  public function testCanUnlockSandboxWithMissingProjectBrowserLock(): void {
    TestActivator::handle('drupal/cream_cheese');

    // Start install begin.
    $this->drupalGet('admin/modules/project_browser/install-begin', [
      'query' => ['source' => 'project_browser_test_mock'],
    ]);
    $this->installState->clear();
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    // Try beginning another install while one is in progress, but not yet in
    // the applying sandbox.
    $this->waitForProject('Cream cheese on a bagel')
      ->pressButton('Install Cream cheese on a bagel');

    $this->assertPageHasText('The process for adding projects is locked, but that lock has expired. Use unlock link to unlock the process and try to add the project again.');
    $this->getSession()->getPage()->clickLink('unlock link');
    // Try beginning another install after breaking lock.
    $this->installProject('Cream cheese on a bagel');
  }

  /**
   * Confirms the break lock link is available and works.
   *
   * The break lock link is not available once the sandbox is applying.
   *
   * @legacy-covers ::unlock
   */
  public function testCanBreakLock(): void {
    TestActivator::handle('drupal/cream_cheese');

    // Start install begin.
    $this->drupalGet('admin/modules/project_browser/install-begin', [
      'query' => ['source' => 'project_browser_test_mock'],
    ]);
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    // Try beginning another install while one is in progress, but not yet in
    // the applying sandbox.
    $this->waitForProject('Cream cheese on a bagel')
      ->pressButton('Install Cream cheese on a bagel');
    $this->assertPageHasText('The process for adding projects is locked, but that lock has expired. Use unlock link to unlock the process and try to add the project again.');
    $this->getSession()->getPage()->clickLink('unlock link');
    // Try beginning another install after breaking lock.
    $this->installProject('Cream cheese on a bagel');
  }

  /**
   * Confirm that a status check error prevents download and install.
   */
  public function testPackageManagerErrorPreventsDownload(): void {
    // @see \Drupal\project_browser_test\TestInstallReadiness
    $this->container->get(StateInterface::class)
      ->set('project_browser_test.simulated_result_severity', RequirementSeverity::Error);

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $cream_cheese = $this->waitForProject('Cream cheese on a bagel');
    $cream_cheese->pressButton('Install Cream cheese on a bagel');
    $this->assertPageHasText('Simulate an error message for the project browser.');
    $this->assertTrue($cream_cheese->hasButton('Install Cream cheese on a bagel'));
  }

  /**
   * Confirm that a status check warning allows download and install.
   */
  public function testPackageManagerWarningAllowsDownloadInstall(): void {
    TestActivator::handle('drupal/cream_cheese');

    // @see \Drupal\project_browser_test\TestInstallReadiness
    $this->container->get(StateInterface::class)
      ->set('project_browser_test.simulated_result_severity', RequirementSeverity::Warning);

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->installProject('Cream cheese on a bagel');
    $this->drupalGet('admin/reports/dblog');
    $this->assertSession()->pageTextContains('Simulate a warning message for the project browser.');
  }

  /**
   * Tests the "Install selected projects" button functionality.
   */
  public function testMultipleModuleAddAndInstall(): void {
    TestActivator::handle('drupal/cream_cheese', 'drupal/kangaroo');
    $this->config('project_browser.admin_settings')
      ->set('max_selections', 2)
      ->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->drupalGet('project-browser/project_browser_test_mock');

    $cream_cheese = $this->waitForProject('Cream cheese on a bagel');
    $cream_cheese->pressButton('Select Cream cheese on a bagel');
    $this->assertTrue(
      $cream_cheese->waitFor(10, fn ($card) => $card->hasButton('Deselect Cream cheese on a bagel'))
    );

    $dancing_queen_button = $this->waitForProject('Dancing Queen')
      ->findButton('Select');
    $this->assertFalse($dancing_queen_button?->hasAttribute('disabled'));

    $this->assertNotEmpty($assert_session->waitForButton('Install selected projects'));

    $kangaroo = $this->waitForProject('Kangaroo');
    $kangaroo->pressButton('Select Kangaroo');
    $this->assertTrue($kangaroo->waitFor(10, fn ($card) => $card->hasButton('Deselect Kangaroo')));
    // Select button gets disabled on reaching maximum limit.
    $this->assertTrue($dancing_queen_button->hasAttribute('disabled'));

    $this->assertNotEmpty($assert_session->waitForButton('Install selected projects'));
    $page->pressButton('Install selected projects');

    $this->waitForProjectToBeInstalled($cream_cheese);
    $this->waitForProjectToBeInstalled($kangaroo);

    // The activator in project_browser_test should have logged the unqualified
    // project IDs.
    // @see \Drupal\project_browser_test\TestActivator
    $activated = $this->container->get(StateInterface::class)
      ->get('test activator');
    $this->assertContains('cream_cheese', $activated);
    $this->assertContains('kangaroo', $activated);
  }

  /**
   * Tests that adding projects to install list is plugin specific.
   */
  public function testPluginSpecificInstallList(): void {
    $this->config('project_browser.admin_settings')
      ->set('max_selections', 2)
      ->save();

    $assert_session = $this->assertSession();
    $this->drupalGet('project-browser/project_browser_test_mock');

    $this->waitForProject('Cream cheese on a bagel')
      ->pressButton('Select Cream cheese on a bagel');
    $this->assertNotEmpty($assert_session->waitForButton('Install selected projects'));

    $projects = $this->getSession()
      ->getPage()
      ->findAll('css', '.pb-project');
    $this->assertGreaterThanOrEqual(2, count($projects));
    $projects[1]->find('css', '.pb__action_button')?->press();
    $this->assertNotEmpty($assert_session->waitForButton('Install selected projects'));
  }

  /**
   * Tests that unlock url has correct href.
   */
  public function testUnlockLinkMarkup(): void {
    $this->drupalGet('admin/modules/project_browser/install-begin', [
      'query' => ['source' => 'project_browser_test_mock'],
    ]);
    $this->installState->clear();
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->waitForProject('Cream cheese on a bagel')
      ->pressButton('Install Cream cheese on a bagel');
    $unlock_url = $this->assertElementIsVisible('css', "#unlock-link")->getAttribute('href') ?? '';
    $path_string = parse_url($unlock_url, PHP_URL_PATH);
    $this->assertIsString($path_string);
    $this->assertStringEndsWith('/admin/modules/project_browser/install/unlock', $path_string);
    $query_string = parse_url($unlock_url, PHP_URL_QUERY);
    $this->assertIsString($query_string);
    parse_str($query_string, $query);
    $this->assertNotEmpty($query['token']);
    $this->assertIsString($query['destination']);
    $this->assertStringEndsWith('/admin/modules/browse/project_browser_test_mock', $query['destination']);
  }

  /**
   * Tests the "Select/Deselect" button functionality in modal.
   */
  public function testSelectDeselectToggleInModal(): void {
    $this->config('project_browser.admin_settings')
      ->set('max_selections', 2)
      ->save();

    $assert_session = $this->assertSession();
    $this->drupalGet('project-browser/project_browser_test_mock');
    $assert_session->waitForButton('Helvetica')?->click();
    // Click select button in modal.
    $assert_session->elementExists('css', '.pb-detail-modal__sidebar_element button.pb__action_button')->click();

    $this->assertSame('Deselect Helvetica',
      $assert_session->elementExists('css', '.pb-detail-modal__sidebar_element button.pb__action_button')->getText());

    // Close the modal.
    $assert_session->waitForButton('Close')?->click();
    $assert_session->elementNotExists('xpath', '//span[contains(@class, "ui-dialog-title") and text()="Helvetica"]');
    $this->assertTrue($this->waitForProject('Helvetica')->hasButton('Deselect Helvetica'));
  }

  /**
   * Tests that the install state does not change on error.
   */
  public function testInstallStatusUnchangedOnError(): void {
    $page = $this->getSession()->getPage();

    // Start install begin.
    $this->drupalGet('admin/modules/project_browser/install-begin', [
      'query' => ['source' => 'project_browser_test_mock'],
    ]);
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    // Try beginning another install while one is in progress, but not yet in
    // the applying sandbox.
    $this->waitForProject('Cream cheese on a bagel')
      ->pressButton('Install Cream cheese on a bagel');
    // Close the dialog to assert the state of install button.
    $page->find('css', '.ui-dialog-titlebar-close')?->click();
    // Assertion that the install state does not change.
    $cream_cheese = $this->waitForProject('Cream cheese on a bagel');
    $this->assertTrue($cream_cheese->hasButton('Install Cream cheese on a bagel'));

    $this->assertElementIsVisible('named', ['link', 'unlock link'])->click();

    // Activate this project specifically, because the test activator treats it
    // as present and therefore will try to install it without needing to deal
    // with Package Manager.
    // @see \Drupal\project_browser_test\TestActivator::getStatus()
    TestActivator::handle('drupal/pinky_brain');
    TestActivator::setErrorOnActivate('drupal/pinky_brain', TRUE);
    $card = $this->waitForProject('Pinky and the Brain');
    $card->pressButton('Install Pinky and the Brain');
    $this->assertPageHasText('Error while activating drupal/pinky_brain');
    $this->assertSession()->statusMessageContains('Error while activating drupal/pinky_brain', 'error');
    // Give Svelte a moment to re-render.
    sleep(1);
    $this->assertTrue($card->hasButton('Install Pinky and the Brain'));
    $this->assertStringNotContainsString('Pinky and the Brain is Installed', $card->getText());
    // Assert that previous activation error is cleared when
    // the project is successfully installed.
    TestActivator::setErrorOnActivate('drupal/pinky_brain', FALSE);
    $card = $this->waitForProject('Pinky and the Brain');
    $card->pressButton('Install Pinky and the Brain');
    $this->waitForProjectToBeInstalled($card);
    $this->assertSession()->statusMessageNotContains('Error while activating drupal/pinky_brain', 'error');
  }

  /**
   * Tests the drop button actions for a project.
   */
  public function testDropButtonActions(): void {
    $this->container->get(ModuleInstallerInterface::class)->install([
      'config_translation',
      'contact',
      'content_translation',
      'help',
    ]);
    $this->rebuildContainer();
    // Log in with permission to do the drop-button tasks.
    $account = $this->drupalCreateUser([
      'administer modules',
      'access help pages',
      'administer contact forms',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('admin/modules/browse/recipes');
    $this->svelteInitHelper('css', '.pb-projects-list');

    $card = $this->waitForProject('Admin theme');
    $card->pressButton('Install');
    $this->waitForProjectToBeInstalled($card);
    // Now assert that the dropdown button does not appear when
    // we don't have any follow-up actions.
    $this->assertNull($this->assertSession()->waitForElementVisible('css', '.dropbutton .secondary-action a'));

    $this->drupalGet('admin/modules/browse/drupal_core');
    $this->svelteInitHelper('css', '.pb-project.pb-project--list');
    $this->searchFor('contact');
    $card = $this->waitForProject('Contact');
    $card->pressButton('List additional actions');
    $this->assertChildElementIsVisible($card, 'css', '.dropbutton .secondary-action a');

    $available_actions = [];
    foreach ($card->findAll('css', '.dropbutton .dropbutton-action a') as $item) {
      $available_actions[$item->getText()] = $item->getAttribute('href') ?? '';
    }

    // Assert expected dropdown actions exist and point to the correct places.
    $this->assertStringEndsWith('/admin/structure/contact', $available_actions['Configure']);
    $this->assertStringEndsWith('/admin/help/contact', $available_actions['Help']);
    $this->assertStringContainsString('/project-browser/uninstall/contact', $available_actions['Uninstall']);

    // Ensure that dropdown menus are mutually exclusive.
    $this->searchFor('translation');
    $project1 = $this->waitForProject('Content Translation');
    $project2 = $this->waitForProject('Configuration Translation');

    // Ensure that an open dropdown closes when you click outside of it.
    $project1->pressButton('List additional actions');
    $this->assertChildElementIsVisible($project1, 'css', '.dropbutton .secondary-action a');
    $project2->click();
    $this->assertFalse($project1->find('css', '.dropbutton .secondary-action')?->isVisible());

    // Ensure that there can only be one open dropdown at a time.
    $project2->pressButton('List additional actions');
    $this->assertChildElementIsVisible($project2, 'css', '.dropbutton .secondary-action a');
    $project1->pressButton('List additional actions');
    $this->assertChildElementIsVisible($project1, 'css', '.dropbutton .secondary-action a');
    $this->assertFalse($project2->find('css', '.dropbutton .secondary-action')?->isVisible());

    // Ensure that we can close an open dropdown by clicking the button again.
    $project1->pressButton('List additional actions');
    $this->assertFalse($project1->find('css', '.dropbutton .secondary-action')?->isVisible());
  }

  /**
   * Tests applying multiple recipes at once, some of which require input.
   */
  public function testApplyMultipleRecipes(): void {
    $this->config('project_browser.admin_settings')
      ->set('max_selections', NULL)
      ->save();

    $this->drupalGet('admin/modules/browse/recipes');
    // Select a recipe that doesn't require input. It's important to choose this
    // one first, to ensure that the fields are grouped correctly in the modal.
    $this->searchFor('comment');
    $comments_card = $this->waitForProject('Article comments');
    $comments_card->pressButton('Select Article comments');
    // And select two recipes that do.
    $this->searchFor('contact');
    $contact_form_card = $this->waitForProject('Website feedback contact form');
    $contact_form_card->pressButton('Select Website feedback contact form');
    $this->searchFor('test');
    $test_card = $this->waitForProject('Test Recipe');
    $test_card->pressButton('Select Test Recipe');

    // Now apply the recipes and ensure the input form opens in a modal, with
    // the input fields grouped by recipe.
    $this->assertElementIsVisible('named', ['button', 'Install selected projects'])
      ->press();
    $modal = $this->assertElementIsVisible('css', '#drupal-modal');
    $this->assertSession()
      ->elementTextContains('css', '.ui-dialog-title', 'Applying recipes');
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', 'summary:contains("Website feedback contact form")', $modal)
      ->getParent()
      ->fillField('Feedback form email address', 'ben@space.net');
    $assert_session->elementExists('css', 'summary:contains("Test Recipe")', $modal)
      ->getParent()
      ->fillField('New site name', 'What a twist!');
    // There should be no group for the recipe that has no input.
    $assert_session->elementNotExists('css', 'summary:contains("Article comments")', $modal);
    // Apply the recipes and wait for the modal to vanish.
    $this->getSession()
      ->getPage()
      ->find('css', '.ui-dialog-buttonpane')?->pressButton('Continue');
    $this->assertTrue($modal->waitFor(10, fn (NodeElement $modal) => !$modal->isValid()));
    // Reload the container to reflect changes made by the recipes.
    $this->rebuildAll();
    // Confirm the recipes did what they should have done.
    $this->assertSame('What a twist!', $this->config('system.site')->get('name'));
    $this->assertContains('ben@space.net', (array) ContactForm::load('feedback')?->getRecipients());
    $this->assertInstanceOf(FieldConfig::class, FieldConfig::loadByName('node', 'article', 'comment'));
  }

  /**
   * Searches visible projects by keyword.
   *
   * @param string $text
   *   The text to search for.
   */
  private function searchFor(string $text): void {
    $this->inputSearchField($text, TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
  }

  /**
   * Tests that Install buttons are disabled during an install process.
   */
  public function testInstallButtonsAreDisabledDuringInstall(): void {
    TestActivator::handle('drupal/cream_cheese');

    $url = Url::fromRoute('project_browser.browse', [
      'source' => 'project_browser_test_mock',
    ]);
    $this->drupalGet($url);
    $install_button = $this->waitForProject('Dancing Queen')
      ->findButton('Install Dancing Queen');
    $this->assertInstanceOf(NodeElement::class, $install_button);

    // While installing another project, Dancing Queen's install button should
    // be disabled.
    $project = $this->waitForProject('Cream cheese on a bagel');
    $project->pressButton('Install Cream cheese on a bagel');
    $this->assertTrue(
      $install_button->waitFor(10, fn () => $install_button->hasAttribute('disabled')),
    );
    $this->waitForProjectToBeInstalled($project);
    $this->assertTrue(
      $install_button->waitFor(10, fn () => !$install_button->hasAttribute('disabled')),
    );
  }

  /**
   * Tests that Install buttons are disabled during a multi-project install.
   */
  public function testInstallButtonsAreDisabledDuringMultiProjectInstall(): void {
    TestActivator::handle('drupal/dancing_queen', 'drupal/octopus');

    $this->config('project_browser.admin_settings')
      ->set('max_selections', 2)
      ->save();
    $url = Url::fromRoute('project_browser.browse', [
      'source' => 'project_browser_test_mock',
    ]);
    $this->drupalGet($url);
    $select_button = $this->waitForProject('Cream cheese on a bagel')
      ->findButton('Select Cream cheese on a bagel');
    $this->assertInstanceOf(NodeElement::class, $select_button);

    // We shouldn't see a button to clear the selection until we've actually
    // selected something.
    $assert_session = $this->assertSession();
    $assert_session->buttonNotExists('Clear selection');

    $dancing_queen = $this->waitForProject('Dancing Queen');
    $octopus = $this->waitForProject('Octopus');
    $dancing_queen->pressButton('Select Dancing Queen');
    $octopus->pressButton('Select Octopus');
    // A third select button should be disabled even before we start installing.
    $this->assertTrue(
      $select_button->waitFor(10, fn () => $select_button->hasAttribute('disabled')),
    );
    // But we should be able to deselect a project.
    $dancing_queen->pressButton('Deselect Dancing Queen');
    // Which should cause the disabled button to become enabled again.
    $this->assertTrue(
      $select_button->waitFor(10, fn () => !$select_button->hasAttribute('disabled')),
    );
    // And we should be able to clear the selection.
    $this->assertElementIsVisible('named', ['button', 'Clear selection'])
      ->press();

    // Reselect the projects we want to install and start the process.
    $dancing_queen->pressButton('Select Dancing Queen');
    $octopus->pressButton('Select Octopus');
    $this->assertElementIsVisible('named', ['button', 'Install selected projects'])
      ->press();
    $this->assertPageHasText('2 projects selected');

    // While that's happening, we shouldn't be able to select another project,
    // nor should we be able to clear the selection.
    $this->assertTrue(
      $select_button->waitFor(10, fn () => $select_button->hasAttribute('disabled')),
    );
    $assert_session->buttonNotExists('Install selected projects');
    $assert_session->buttonNotExists('Clear selection');

    $this->waitForProjectToBeInstalled($dancing_queen);
    $this->waitForProjectToBeInstalled($octopus);
    // Now we can select another project.
    $this->assertTrue(
      $select_button->waitFor(10, fn () => !$select_button->hasAttribute('disabled')),
    );
    // Nothing is selected, so we shouldn't see a button to clear the selection.
    $this->assertPageHasText('No projects selected');
    $assert_session->buttonNotExists('Clear selection');
  }

  /**
   * Tests that the messages set by projects while installing are shown.
   */
  public function testMessagesSetByProjectsAreShown(): void {
    TestActivator::handle('drupal/cream_cheese');
    $this->drupalGet('project-browser/project_browser_test_mock');
    $messages_to_set = ["This message is from Cream cheese on a bagel", "For testing purpose only."];
    TestActivator::setMessagesOnActivate('drupal/cream_cheese', $messages_to_set);
    $card = $this->waitForProject('Cream cheese on a bagel');
    $card->pressButton('Install Cream cheese on a bagel');
    $this->waitForProjectToBeInstalled($card);
    foreach ($messages_to_set as $message) {
      $this->assertPageHasText($message);
    }
  }

  /**
   * Waits for a child of a particular element, to be visible.
   *
   * @param \Behat\Mink\Element\NodeElement $parent
   *   An element that (presumably) contains children.
   * @param string $selector
   *   The selector (e.g., `css`, `xpath`, etc.) to use to find a child element.
   * @param mixed $locator
   *   The locator to pass to the selector engine.
   * @param int $timeout
   *   (optional) How many seconds to wait for the child element to appear.
   *   Defaults to 10.
   */
  private function assertChildElementIsVisible(NodeElement $parent, string $selector, mixed $locator, int $timeout = 10): void {
    $is_visible = $parent->waitFor(
      $timeout,
      fn (NodeElement $parent) => $parent->find($selector, $locator)?->isVisible(),
    );
    $this->assertTrue($is_visible);
  }

}
