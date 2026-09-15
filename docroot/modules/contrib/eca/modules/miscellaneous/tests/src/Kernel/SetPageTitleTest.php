<?php

namespace Drupal\Tests\eca_misc\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Event\RenderEventInterface;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_misc\Hook\PageTitleHooks;
use Drupal\eca_misc\PageTitleStore;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Kernel tests for the "eca_misc_set_page_title" action plugin.
 */
#[Group('eca')]
#[Group('eca_misc')]
#[RunTestsInSeparateProcesses]
class SetPageTitleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_misc',
    'modeler_api',
  ];

  /**
   * Action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * The page title store.
   *
   * @var \Drupal\eca_misc\PageTitleStore|null
   */
  protected ?PageTitleStore $pageTitleStore;

  /**
   * ECA token service.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(static::$modules);
    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->pageTitleStore = \Drupal::service(PageTitleStore::class);
    $this->tokenService = \Drupal::service('eca.token_services');
  }

  /**
   * Tests that executing the action stores the title.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testStoreReceivesTitle(): void {
    $this->assertFalse($this->pageTitleStore->hasTitle(), 'No title is set initially.');

    /** @var \Drupal\eca_misc\Plugin\Action\SetPageTitle $action */
    $action = $this->actionManager->createInstance('eca_misc_set_page_title', [
      'title' => 'My custom title',
    ]);
    $action->execute();

    $this->assertTrue($this->pageTitleStore->hasTitle(), 'A title is set after execution.');
    $this->assertSame('My custom title', $this->pageTitleStore->getTitle());
  }

  /**
   * Tests that tokens in the title are resolved.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testTitleSupportsTokens(): void {
    $this->tokenService->addTokenData('mytoken', 'Resolved');

    /** @var \Drupal\eca_misc\Plugin\Action\SetPageTitle $action */
    $action = $this->actionManager->createInstance('eca_misc_set_page_title', [
      'title' => '[mytoken] title',
    ]);
    $action->execute();

    $this->assertSame('Resolved title', $this->pageTitleStore->getTitle());
  }

  /**
   * Tests that an empty title is a no-op.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testEmptyTitleIsNoop(): void {
    /** @var \Drupal\eca_misc\Plugin\Action\SetPageTitle $action */
    $action = $this->actionManager->createInstance('eca_misc_set_page_title', [
      'title' => '   ',
    ]);
    $action->execute();

    $this->assertFalse($this->pageTitleStore->hasTitle(), 'An empty title does not set a title.');
  }

  /**
   * Tests that the preprocess hooks apply the stored title.
   */
  public function testPreprocessHooksApplyTitle(): void {
    $hooks = new PageTitleHooks($this->pageTitleStore);

    // Before any title is set, the hooks must be a no-op.
    $htmlVariables = ['head_title' => ['title' => 'Original', 'name' => 'Site']];
    $hooks->preprocessHtml($htmlVariables);
    $this->assertSame('Original', (string) $htmlVariables['head_title']['title'], 'Without an ECA title, head_title is unchanged.');

    $titleVariables = ['title' => 'Original heading'];
    $hooks->preprocessPageTitle($titleVariables);
    $this->assertSame('Original heading', (string) $titleVariables['title'], 'Without an ECA title, the heading is unchanged.');

    // Now set a title and verify both hooks override their respective values.
    $this->pageTitleStore->setTitle('ECA Title');

    $htmlVariables = ['head_title' => ['title' => 'Original', 'name' => 'Site']];
    $hooks->preprocessHtml($htmlVariables);
    $this->assertSame('ECA Title', (string) $htmlVariables['head_title']['title'], 'head_title is overridden with the ECA title.');
    $this->assertSame('Site', $htmlVariables['head_title']['name'], 'The site name part is preserved.');

    $titleVariables = ['title' => 'Original heading'];
    $hooks->preprocessPageTitle($titleVariables);
    $this->assertSame('ECA Title', (string) $titleVariables['title'], 'The on-page heading is overridden with the ECA title.');
  }

  /**
   * Tests that the action also sets #title on a render event (form case).
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testRenderEventSetsTitleOnRenderArray(): void {
    $event = new class() extends Event implements RenderEventInterface {

      /**
       * The render array.
       *
       * @var array
       */
      public array $build = ['#type' => 'form'];

      /**
       * {@inheritdoc}
       */
      public function &getRenderArray(): array {
        return $this->build;
      }

    };

    /** @var \Drupal\eca_misc\Plugin\Action\SetPageTitle $action */
    $action = $this->actionManager->createInstance('eca_misc_set_page_title', [
      'title' => 'Form title',
    ]);
    // Inject the render event so the action treats this as a render context.
    $action->setEvent($event);
    $action->execute();

    $this->assertSame('Form title', $event->build['#title'], 'The action sets #title on the render array.');
    $this->assertSame('Form title', $this->pageTitleStore->getTitle(), 'The action also stores the title.');
  }

}
