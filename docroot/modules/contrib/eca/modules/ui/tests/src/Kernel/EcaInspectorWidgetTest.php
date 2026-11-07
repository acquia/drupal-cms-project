<?php

namespace Drupal\Tests\eca_ui\Kernel;

use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\node\Entity\Node;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the ECA inspector widget rendering and permission gating.
 *
 * The widget that lists the ECA events applied on the current page must be
 * shown based on the user's permission to view or edit ECA models, not on the
 * debug mode. Editable users link to the modeler edit form; view-only users
 * link to the read-only canonical route (the same path without "/edit").
 *
 * @see \Drupal\eca_ui\EventSubscriber\EcaEventCollector
 */
#[Group('eca')]
#[Group('eca_ui')]
#[RunTestsInSeparateProcesses]
class EcaInspectorWidgetTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * The ID of the ECA model created for this test.
   */
  protected const ECA_ID = 'inspector_test';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_content',
    'eca_ui',
    'modeler_api',
    // Provides a second modeler so modeler_api registers the read-only
    // canonical route, which view-only users link to. Without a second
    // modeler only the fallback exists and the canonical route is omitted.
    'eca_test_stub_modeler',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'eca', 'eca_ui']);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    $this->createContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);

    // A minimal ECA model that reacts to the node presave event. Saving a node
    // marks this event as applied on the Processor, independently of debug
    // mode, which is exactly what the inspector widget reports.
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => self::ECA_ID,
      'label' => 'Inspector test model',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'presave' => [
          'plugin' => 'content_entity:presave',
          'label' => 'Presave node',
          'configuration' => [
            'type' => 'node page',
          ],
          'successors' => [],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [],
    ])->save();

    // Trigger the event so the Processor records the applied event.
    Node::create([
      'type' => 'page',
      'title' => 'Inspector test node',
    ])->save();
  }

  /**
   * Invokes the response subscriber and returns the resulting response body.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response passed to the subscriber.
   *
   * @return string
   *   The rendered response content after the subscriber ran.
   */
  protected function captureResponseContent(Response $response): string {
    $event = new ResponseEvent(
      $this->container->get('http_kernel'),
      Request::create('/'),
      HttpKernelInterface::MAIN_REQUEST,
      $response,
    );
    /** @var \Drupal\eca_ui\EventSubscriber\EcaEventCollector $subscriber */
    $subscriber = $this->container->get('eca_ui.event_collector');
    $subscriber->onResponse($event);
    return $event->getResponse()->getContent();
  }

  /**
   * Sets the current user to one holding exactly the given permissions.
   *
   * @param string[] $permissions
   *   The permissions the current user should have.
   */
  protected function setCurrentUserWithPermissions(array $permissions): void {
    $account = $this->createStub(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, $permissions, TRUE),
    );
    $this->container->get('current_user')->setAccount($account);
  }

  /**
   * The widget is hidden for users without any ECA model permission.
   */
  public function testWidgetHiddenWithoutPermission(): void {
    $this->setCurrentUserWithPermissions([]);
    $content = $this->captureResponseContent(new HtmlResponse('<html><body><p>Page body.</p></body></html>'));
    $this->assertStringNotContainsString('eca-inspector-applied-events', $content);
  }

  /**
   * View-only users see the widget linking to the read-only canonical route.
   */
  public function testWidgetForViewOnlyUserLinksToCanonicalRoute(): void {
    $this->setCurrentUserWithPermissions(['modeler api view eca']);
    $content = $this->captureResponseContent(new HtmlResponse('<html><body><p>Page body.</p></body></html>'));
    $this->assertStringContainsString('id="eca-inspector-applied-events"', $content);
    // The view-only link points at the canonical route: the model path without
    // the trailing "/edit" segment.
    $this->assertStringContainsString('/admin/config/workflow/eca/' . self::ECA_ID . '?', $content);
    $this->assertStringNotContainsString('/' . self::ECA_ID . '/edit', $content);
  }

  /**
   * Editable users see the widget linking to the modeler edit form.
   */
  public function testWidgetForEditUserLinksToEditForm(): void {
    $this->setCurrentUserWithPermissions(['modeler api edit eca']);
    $content = $this->captureResponseContent(new HtmlResponse('<html><body><p>Page body.</p></body></html>'));
    $this->assertStringContainsString('id="eca-inspector-applied-events"', $content);
    $this->assertStringContainsString('/' . self::ECA_ID . '/edit', $content);
  }

  /**
   * HtmlResponse instances are recognized even without a Content-Type header.
   */
  public function testWidgetForHeaderlessHtmlResponse(): void {
    $this->setCurrentUserWithPermissions(['modeler api view eca']);

    $response = new HtmlResponse('<html><body><p>Page body.</p></body></html>');
    $response->headers->remove('Content-Type');
    $content = $this->captureResponseContent($response);

    $this->assertStringContainsString('id="eca-inspector-applied-events"', $content);
  }

  /**
   * Generic responses are excluded even when their Content-Type is HTML.
   */
  public function testWidgetSkippedForGenericHtmlResponse(): void {
    $this->setCurrentUserWithPermissions(['modeler api view eca']);

    $response = new Response('<html><body><p>Page body.</p></body></html>', 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
    ]);
    $expected = $response->getContent();
    $content = $this->captureResponseContent($response);

    $this->assertSame($expected, $content);
    $this->assertStringNotContainsString('id="eca-inspector-applied-events"', $content);
  }

  /**
   * JSON responses remain unchanged even if they contain "</body>".
   */
  public function testWidgetSkippedForJsonResponse(): void {
    $this->setCurrentUserWithPermissions(['modeler api view eca']);

    $response = new JsonResponse(['body' => '</body>']);
    $expected = $response->getContent();
    $content = $this->captureResponseContent($response);

    $this->assertSame($expected, $content);
    $this->assertStringNotContainsString('id="eca-inspector-applied-events"', $content);
  }

  /**
   * JavaScript responses remain unchanged even if they contain "</body>".
   */
  public function testWidgetSkippedForJavaScriptResponse(): void {
    $this->setCurrentUserWithPermissions(['modeler api view eca']);

    $javascript = <<<'JS'
window.addEventListener('load', () => {
  const marker = "</body>";
  console.log(marker);
});
JS;

    $content = $this->captureResponseContent(new Response($javascript, 200, [
      'Content-Type' => 'application/javascript; charset=UTF-8',
    ]));

    $this->assertStringContainsString('</body>', $content);
    $this->assertStringNotContainsString('id="eca-inspector-applied-events"', $content);
    $this->assertSame($javascript, $content);
  }

  /**
   * Redirect responses are not page responses and remain unchanged.
   */
  public function testWidgetSkippedForRedirectResponse(): void {
    $this->setCurrentUserWithPermissions(['modeler api view eca']);

    $response = new RedirectResponse('/target');
    $expected = $response->getContent();
    $content = $this->captureResponseContent($response);

    $this->assertSame($expected, $content);
    $this->assertStringNotContainsString('id="eca-inspector-applied-events"', $content);
  }

}
