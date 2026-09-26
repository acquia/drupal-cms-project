<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Unit;

use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\modeler_api\Api;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Tests Api::getParentMenuName() route-to-menu-link-plugin-ID resolution.
 *
 * The "parent" key of a menu link must be a menu link plugin ID, not a route
 * name. getParentMenuName() resolves the parent path to a route, then maps
 * that route to the menu link plugin ID that points at it. The two are only
 * sometimes identical, so a derived link is orphaned (and never rendered) when
 * the route name is used directly as the parent.
 */
#[CoversClass(Api::class)]
#[Group('modeler_api')]
class ApiGetParentMenuNameTest extends UnitTestCase {

  /**
   * Builds an Api instance wired with the router and menu link manager mocks.
   *
   * Only the router and menu link manager are relevant to
   * getParentMenuName(). The Api constructor pulls in many services (one of
   * which is declared "final" and cannot be doubled), so the instance is
   * created without invoking the constructor and only the two properties under
   * test are populated via reflection.
   *
   * Both the router and the menu link manager are injected as service closures
   * to avoid container circular references, so they are resolved lazily via
   * their *Factory properties. The test populates those factories with closures
   * returning the mocks, mirroring the production wiring.
   *
   * @param \Symfony\Component\Routing\RouterInterface $router
   *   The router mock.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menuLinkManager
   *   The menu link manager mock.
   *
   * @return \Drupal\modeler_api\Api
   *   The API service under test.
   */
  protected function buildApi(RouterInterface $router, MenuLinkManagerInterface $menuLinkManager): Api {
    $reflection = new \ReflectionClass(Api::class);
    /** @var \Drupal\modeler_api\Api $api */
    $api = $reflection->newInstanceWithoutConstructor();

    $routerFactoryProperty = $reflection->getProperty('routerFactory');
    $routerFactoryProperty->setValue($api, static fn (): RouterInterface => $router);

    $menuLinkManagerFactoryProperty = $reflection->getProperty('menuLinkManagerFactory');
    $menuLinkManagerFactoryProperty->setValue($api, static fn (): MenuLinkManagerInterface => $menuLinkManager);

    return $api;
  }

  /**
   * Tests that a route name is mapped to the matching menu link plugin ID.
   *
   * Mirrors the AI module: the path "/admin/config/ai" resolves to the route
   * "ai.settings.menu", but the menu link plugin ID is "ai.admin_settings".
   * The parent must be the plugin ID, not the route name.
   */
  public function testResolvesRouteNameToMenuLinkPluginId(): void {
    $router = $this->createMock(RouterInterface::class);
    $router->expects($this->once())
      ->method('match')
      ->with('/admin/config/ai')
      ->willReturn(['_route' => 'ai.settings.menu']);

    $link = $this->createStub(MenuLinkInterface::class);
    $menuLinkManager = $this->createMock(MenuLinkManagerInterface::class);
    $menuLinkManager->expects($this->once())
      ->method('loadLinksByRoute')
      ->with('ai.settings.menu')
      ->willReturn(['ai.admin_settings' => $link]);

    $api = $this->buildApi($router, $menuLinkManager);

    $this->assertSame('ai.admin_settings', $api->getParentMenuName('admin/config/ai/agents'));
  }

  /**
   * Tests the fallback to the route name when no menu link is found.
   *
   * Mirrors ECA and Drupal core system links: the path
   * "/admin/config/workflow" resolves to "system.admin_config_workflow", whose
   * menu link plugin ID is identical to the route name. When no link is loaded
   * for the route, the route name itself is returned to preserve behavior.
   */
  public function testFallsBackToRouteNameWhenNoLinkFound(): void {
    $router = $this->createMock(RouterInterface::class);
    $router->expects($this->once())
      ->method('match')
      ->with('/admin/config/workflow')
      ->willReturn(['_route' => 'system.admin_config_workflow']);

    $menuLinkManager = $this->createMock(MenuLinkManagerInterface::class);
    $menuLinkManager->expects($this->once())
      ->method('loadLinksByRoute')
      ->with('system.admin_config_workflow')
      ->willReturn([]);

    $api = $this->buildApi($router, $menuLinkManager);

    $this->assertSame('system.admin_config_workflow', $api->getParentMenuName('admin/config/workflow/eca'));
  }

  /**
   * Tests that a path without a parent segment returns NULL.
   */
  public function testReturnsNullForTopLevelPath(): void {
    $router = $this->createMock(RouterInterface::class);
    $router->expects($this->never())->method('match');

    $menuLinkManager = $this->createMock(MenuLinkManagerInterface::class);
    $menuLinkManager->expects($this->never())->method('loadLinksByRoute');

    $api = $this->buildApi($router, $menuLinkManager);

    $this->assertNull($api->getParentMenuName('admin'));
  }

  /**
   * Tests that an unmatched parent path returns NULL.
   */
  public function testReturnsNullWhenRouterThrows(): void {
    $router = $this->createMock(RouterInterface::class);
    $router->expects($this->once())
      ->method('match')
      ->willThrowException(new ResourceNotFoundException());

    $menuLinkManager = $this->createMock(MenuLinkManagerInterface::class);
    $menuLinkManager->expects($this->never())->method('loadLinksByRoute');

    $api = $this->buildApi($router, $menuLinkManager);

    $this->assertNull($api->getParentMenuName('admin/config/does-not-exist/child'));
  }

  /**
   * Tests that a match without a "_route" key returns NULL.
   */
  public function testReturnsNullWhenRouteMissing(): void {
    $router = $this->createMock(RouterInterface::class);
    $router->expects($this->once())
      ->method('match')
      ->willReturn([]);

    $menuLinkManager = $this->createMock(MenuLinkManagerInterface::class);
    $menuLinkManager->expects($this->never())->method('loadLinksByRoute');

    $api = $this->buildApi($router, $menuLinkManager);

    $this->assertNull($api->getParentMenuName('admin/config/something/child'));
  }

}
