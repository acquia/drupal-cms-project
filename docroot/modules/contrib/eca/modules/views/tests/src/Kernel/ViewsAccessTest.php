<?php

namespace Drupal\Tests\eca_views\Kernel;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_views\AccessCheck;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Kernel tests for the views access of the "eca_views" submodule.
 *
 * Covers both consumers of the "eca_views:access" event: the route access
 * check and the views access plugin.
 */
#[Group('eca')]
#[Group('eca_views')]
#[RunTestsInSeparateProcesses]
class ViewsAccessTest extends KernelTestBase {

  /**
   * The ID of the view used throughout this test.
   */
  private const VIEW_ID = 'test_view';

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
    'views',
    'eca',
    'eca_access',
    'eca_views',
    'modeler_api',
  ];

  /**
   * The account for which access is being checked.
   *
   * @var \Drupal\Core\Session\AccountInterface|null
   */
  protected ?AccountInterface $account;

  /**
   * The views access checker.
   *
   * @var \Drupal\eca_views\AccessCheck|null
   */
  protected ?AccessCheck $accessCheck;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);

    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    $account = User::create(['uid' => 2, 'name' => 'authenticated']);
    $account->save();
    $this->account = $account;

    View::create([
      'id' => self::VIEW_ID,
      'label' => 'Test View',
    ])->save();

    /** @var \Drupal\eca_views\AccessCheck $accessCheck */
    $accessCheck = $this->container->get('eca_views.access_checker');
    $this->accessCheck = $accessCheck;
  }

  /**
   * Tests both consumers when no ECA model reacts upon the access event.
   *
   * This is the baseline behavior that must remain unchanged.
   */
  public function testAccessWithoutModel(): void {
    $result = $this->routeAccessResult();
    $this->assertTrue($result->isForbidden(), "The access check forbids access when no ECA model reacts upon the event.");

    $this->assertFalse($this->viewsAccessPluginAllows(), "The views access plugin denies access when no ECA model reacts upon the event.");
  }

  /**
   * Tests the access check for a route without a view.
   *
   * This is the baseline behavior that must remain unchanged.
   */
  public function testAccessCheckWithoutView(): void {
    $route = new Route('/' . self::VIEW_ID);
    $route_match = new RouteMatch('eca_views_test', $route);
    $result = $this->accessCheck->access($route, $route_match, $this->account);
    $this->assertTrue($result->isForbidden(), "The access check forbids access when the route has no view.");
  }

  /**
   * Tests both consumers when a single ECA model grants access.
   *
   * Accumulating access results must not turn a granted access result into a
   * denied one when only one ECA model reacts upon the event.
   */
  public function testAccessWithSingleModelGrantingAccess(): void {
    $this->createViewsAccessModel('views_access_allowed', 'allowed', 0);

    $result = $this->routeAccessResult();
    $this->assertTrue($result->isAllowed(), "The access check grants access when a single ECA model grants access.");
    $this->assertFalse($result->isForbidden(), "The access check does not forbid access when a single ECA model grants access.");

    $this->assertTrue($this->viewsAccessPluginAllows(), "The views access plugin grants access when a single ECA model grants access.");
  }

  /**
   * Tests both consumers when a single ECA model revokes access.
   */
  public function testAccessWithSingleModelRevokingAccess(): void {
    $this->createViewsAccessModel('views_access_forbidden', 'forbidden', 0);

    $result = $this->routeAccessResult();
    $this->assertTrue($result->isForbidden(), "The access check forbids access when a single ECA model revokes access.");

    $this->assertFalse($this->viewsAccessPluginAllows(), "The views access plugin denies access when a single ECA model revokes access.");
  }

  /**
   * Tests both consumers when multiple ECA models react upon the event.
   *
   * The access result is accumulated across all reacting ECA models, so a
   * single model revoking access must win regardless of the order in which the
   * models are being executed.
   */
  public function testAccessWithMultipleModels(): void {
    // Two models react upon the event for the same view: the one granting
    // access is being executed first, the one revoking access is being
    // executed last.
    $this->createViewsAccessModel('views_access_allowed', 'allowed', 0);
    $this->createViewsAccessModel('views_access_forbidden', 'forbidden', 10);

    $result = $this->routeAccessResult();
    $this->assertFalse($result->isAllowed(), "The access check does not grant access when another ECA model revoked access, even when that model is being executed last.");
    $this->assertTrue($result->isForbidden(), "The access check forbids access when another ECA model revoked access, even when that model is being executed last.");
    $this->assertFalse($this->viewsAccessPluginAllows(), "The views access plugin denies access when another ECA model revoked access, even when that model is being executed last.");

    // Reverse the weights, so that the model revoking access is now being
    // executed first and the model granting access is being executed last.
    // The accumulated result must not depend on the execution order.
    $this->setModelWeight('views_access_forbidden', 0);
    $this->setModelWeight('views_access_allowed', 10);

    $result = $this->routeAccessResult();
    $this->assertFalse($result->isAllowed(), "The access check does not grant access when another ECA model revoked access, even when that model is being executed first.");
    $this->assertTrue($result->isForbidden(), "The access check forbids access when another ECA model revoked access, even when that model is being executed first.");
    $this->assertFalse($this->viewsAccessPluginAllows(), "The views access plugin denies access when another ECA model revoked access, even when that model is being executed first.");
  }

  /**
   * Determines the access result of the views route access check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  private function routeAccessResult(): AccessResultInterface {
    // Views defines the view and the display of a page display as route
    // defaults, which is how the access checker receives them.
    $defaults = [
      'view_id' => self::VIEW_ID,
      'display_id' => 'default',
    ];
    $route = new Route('/' . self::VIEW_ID, $defaults);
    $route_match = new RouteMatch('view.' . self::VIEW_ID . '.default', $route, $defaults);
    return $this->accessCheck->access($route, $route_match, $this->account);
  }

  /**
   * Determines whether the views access plugin grants access.
   *
   * @return bool
   *   TRUE if access is being granted, FALSE otherwise.
   */
  private function viewsAccessPluginAllows(): bool {
    $view = Views::getView(self::VIEW_ID);
    $this->assertInstanceOf(ViewExecutable::class, $view);
    $view->setDisplay('default');
    // Only assign the ECA access plugin to the executable, not to the stored
    // view configuration, so that no other test aspect depends on it.
    $view->display_handler->setOption('access', [
      'type' => 'eca',
      'options' => [],
    ]);
    return $view->display_handler->access($this->account);
  }

  /**
   * Creates an ECA model that sets an access result for the view.
   *
   * @param string $id
   *   The ID of the ECA model.
   * @param string $access_result
   *   The access result to set, one of "allowed", "neutral" or "forbidden".
   * @param int $weight
   *   The weight of the ECA model, which determines the order of execution.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createViewsAccessModel(string $id, string $access_result, int $weight): void {
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => $id,
      'label' => 'ECA views access -> ' . $access_result,
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'weight' => $weight,
      'events' => [
        'views_access' => [
          'plugin' => 'eca_views:access',
          'label' => 'ECA views access',
          'configuration' => [
            'view_id' => self::VIEW_ID,
            'display_id' => 'default',
          ],
          'successors' => [
            ['id' => 'set_access_result', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_access_result' => [
          'plugin' => 'eca_access_set_result',
          'label' => 'Set access result',
          'configuration' => [
            'access_result' => $access_result,
          ],
          'successors' => [],
        ],
      ],
    ];
    Eca::create($eca_config_values)->save();
  }

  /**
   * Changes the weight of an existing ECA model.
   *
   * @param string $id
   *   The ID of the ECA model.
   * @param int $weight
   *   The weight to set, which determines the order of execution.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function setModelWeight(string $id, int $weight): void {
    $eca = Eca::load($id);
    if (!($eca instanceof Eca)) {
      $this->fail(sprintf('The ECA model %s does not exist.', $id));
    }
    $eca->set('weight', $weight);
    $eca->save();
  }

}
