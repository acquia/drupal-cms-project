<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\project_browser\Activator\ActivationStatus;
use Drupal\project_browser\Controller\InstallerController;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\Tests\ApiRequestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\project_browser\Traits\PackageManagerFixtureUtilityTrait;
use Drupal\package_manager\Event\PostApplyEvent;
use Drupal\package_manager\Event\PostCreateEvent;
use Drupal\package_manager\Event\PostRequireEvent;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreRequireEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber;
use Drupal\project_browser\ComposerInstaller\Installer;
use Drupal\project_browser\QueryManager;
use Drupal\project_browser\InstallProgress;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests the installer controller.
 *
 * @group project_browser
 */
#[CoversClass(InstallerController::class)]
#[Group('project_browser')]
final class InstallerControllerTest extends BrowserTestBase {

  use PackageManagerFixtureUtilityTrait;
  use ApiRequestTrait;

  /**
   * A sandbox ID.
   */
  protected string $sandboxId;

  /**
   * The installer.
   *
   * @var \Drupal\project_browser\ComposerInstaller\Installer
   */
  private $installer;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'package_manager_bypass',
    'package_manager',
    'package_manager_test_validation',
    'project_browser',
    'project_browser_test',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Setup for install controller test.
   */
  protected function setUp(): void {
    parent::setUp();
    $connection = $this->container->get('database');
    $query = $connection->insert('project_browser_projects')->fields([
      'nid',
      'title',
      'created',
      'project_usage_total',
      'maintenance_status',
      'development_status',
      'status',
      'field_security_advisory_coverage',
      'field_project_type',
      'project_data',
      'field_project_machine_name',
    ]);
    $query->values([
      'nid' => 111,
      'title' => 'An Awesome Module',
      'created' => 1383917647,
      'project_usage_total' => 455,
      'maintenance_status' => 13028,
      'development_status' => 9988,
      'status' => 1,
      'field_security_advisory_coverage' => 'covered',
      'field_project_type' => 'full',
      'project_data' => serialize([
        'body' => [
          'value' => $this->getRandomGenerator()->paragraphs(1),
        ],
      ]),
      'field_project_machine_name' => 'awesome_module',
    ]);
    $query->values([
      'nid' => 333,
      'title' => 'Drupal core',
      'created' => 1383917647,
      'project_usage_total' => 987654321,
      'maintenance_status' => 13028,
      'development_status' => 9988,
      'status' => 1,
      'field_security_advisory_coverage' => 'covered',
      'field_project_type' => 'full',
      'project_data' => serialize([
        'body' => [
          'value' => $this->getRandomGenerator()->paragraphs(1),
        ],
      ]),
      'field_project_machine_name' => 'core',
    ]);
    $query->values([
      'nid' => 444,
      'title' => 'Metatag',
      'created' => 1383917448,
      'project_usage_total' => 455,
      'maintenance_status' => 13028,
      'development_status' => 9988,
      'status' => 1,
      'field_security_advisory_coverage' => 'covered',
      'field_project_type' => 'full',
      'project_data' => serialize([
        'body' => [
          'value' => $this->getRandomGenerator()->paragraphs(1),
        ],
      ]),
      'field_project_machine_name' => 'metatag',
    ]);
    $query->execute();
    $this->initPackageManager();
    /** @var \Drupal\project_browser\ComposerInstaller\Installer $installer */
    $installer = $this->container->get(Installer::class);
    $this->installer = $installer;
    $this->drupalLogin($this->drupalCreateUser(['administer modules']));
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'project_browser_test_mock' => [],
        'drupal_core' => [],
      ])
      ->set('allow_ui_install', TRUE)
      ->save();

    // Prime the non-volatile cache.
    $this->container->get(QueryManager::class)->getProjects('project_browser_test_mock');
  }

  /**
   * Confirms install endpoint not available if UI installs are not enabled.
   *
   * @legacy-covers ::access
   */
  public function testUiInstallUnavailableIfDisabled(): void {
    $this->config('project_browser.admin_settings')->set('allow_ui_install', FALSE)->save();
    $this->drupalGet('admin/modules/project_browser/install-begin');
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->pageTextContains('Access denied');
  }

  /**
   * Confirms a require will stop if package already present.
   *
   * @legacy-covers ::require
   */
  public function testInstallAlreadyPresentPackage(): void {
    // Though core is not available as a choice in project browser, it works
    // well for the purposes of this test as it's definitely already added
    // via composer.
    $contents = $this->drupalGet('admin/modules/project_browser/install-begin');
    $this->sandboxId = Json::decode($contents)['sandboxId'];
    $response = $this->getPostResponse(
      Url::fromRoute('project_browser.stage.require', ['sandbox_id' => $this->sandboxId]),
      ['project_browser_test_mock/core'],
    );
    $this->assertSame(500, (int) $response->getStatusCode());
    $this->assertSame('{"message":"SandboxEventException: The following package is already installed: drupal\/core\n","phase":"require"}', (string) $response->getBody());
  }

  /**
   * Calls the endpoint that begins installation.
   *
   * @legacy-covers ::begin
   */
  private function doStart(): void {
    $contents = Json::decode($this->drupalGet('admin/modules/project_browser/install-begin'));
    $this->assertSession()->statusCodeEquals(200);
    $this->sandboxId = $contents['sandboxId'];
    $this->assertNotEmpty($contents['progress']);
  }

  /**
   * Calls the endpoint that continues to the require phase of installation.
   *
   * @legacy-covers ::require
   */
  private function doRequire(): void {
    $response = $this->getPostResponse(
      Url::fromRoute('project_browser.stage.require', ['sandbox_id' => $this->sandboxId]),
      ['project_browser_test_mock/awesome_module'],
    );
    $contents = Json::decode((string) $response->getBody());
    $this->assertSame($this->sandboxId, $contents['sandboxId']);
    $this->assertNotEmpty($contents['progress']);
    $this->assertInstallInProgress('project_browser_test_mock/awesome_module', ActivationStatus::Absent);
  }

  /**
   * Calls the endpoint that continues to the apply phase of installation.
   *
   * @legacy-covers ::apply
   */
  private function doApply(): void {
    $contents = Json::decode($this->drupalGet("/admin/modules/project_browser/install-apply/$this->sandboxId"));
    $this->assertSame($this->sandboxId, $contents['sandboxId']);
    $this->assertNotEmpty($contents['progress']);
    $this->assertInstallInProgress('project_browser_test_mock/awesome_module', ActivationStatus::Present);
  }

  /**
   * Calls the endpoint that continues to the post apply phase of installation.
   *
   * @legacy-covers ::postApply
   */
  private function doPostApply(): void {
    $contents = Json::decode($this->drupalGet("/admin/modules/project_browser/install-post_apply/$this->sandboxId"));
    $this->assertSame($this->sandboxId, $contents['sandboxId']);
    $this->assertNotEmpty($contents['progress']);
    $this->assertInstallInProgress('project_browser_test_mock/awesome_module', ActivationStatus::Present);
  }

  /**
   * Calls the endpoint that continues to the destroy phase of installation.
   *
   * @legacy-covers ::destroy
   */
  private function doDestroy(): void {
    $contents = Json::decode($this->drupalGet("/admin/modules/project_browser/install-destroy/$this->sandboxId"));
    $this->assertSame($this->sandboxId, $contents['sandboxId']);
    $this->assertNotEmpty($contents['progress']);
    $this->assertInstallInProgress('project_browser_test_mock/awesome_module', ActivationStatus::Present);
  }

  /**
   * Calls every endpoint needed to do a UI install and confirms they work.
   */
  public function testUiInstallerEndpoints(): void {
    $this->doStart();
    $this->doRequire();
    $this->doApply();
    $this->doPostApply();
    $this->doDestroy();
  }

  /**
   * Tests an error during a pre create event.
   *
   * @legacy-covers ::create
   */
  public function testPreCreateError(): void {
    $message = new TranslatableMarkup('This is a PreCreate error.');
    $result = ValidationResult::createError([$message]);
    TestSubscriber::setTestResult([$result], PreCreateEvent::class);
    $contents = $this->drupalGet('admin/modules/project_browser/install-begin');
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSame('{"message":"SandboxEventException: This is a PreCreate error.\n","phase":"create"}', $contents);
  }

  /**
   * Tests an exception during a pre create event.
   *
   * @legacy-covers ::create
   */
  public function testPreCreateException(): void {
    $error = new \Exception('PreCreate did not go well.');
    TestSubscriber::setException($error, PreCreateEvent::class);
    $contents = $this->drupalGet('admin/modules/project_browser/install-begin');
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSame('{"message":"SandboxEventException: PreCreate did not go well.","phase":"create"}', $contents);
  }

  /**
   * Tests an exception during a post create event.
   *
   * @legacy-covers ::create
   */
  public function testPostCreateException(): void {
    $error = new \Exception('PostCreate did not go well.');
    TestSubscriber::setException($error, PostCreateEvent::class);
    $contents = $this->drupalGet('admin/modules/project_browser/install-begin');
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSame('{"message":"SandboxEventException: PostCreate did not go well.","phase":"create"}', $contents);
  }

  /**
   * Tests an error during a pre require event.
   *
   * @legacy-covers ::require
   */
  public function testPreRequireError(): void {
    $message = new TranslatableMarkup('This is a PreRequire error.');
    $result = ValidationResult::createError([$message]);
    $this->doStart();
    TestSubscriber::setTestResult([$result], PreRequireEvent::class);
    $response = $this->getPostResponse(
      Url::fromRoute('project_browser.stage.require', ['sandbox_id' => $this->sandboxId]),
      ['project_browser_test_mock/awesome_module'],
    );
    $this->assertSame(500, (int) $response->getStatusCode());
    $this->assertSame('{"message":"SandboxEventException: This is a PreRequire error.\n","phase":"require"}', (string) $response->getBody());
  }

  /**
   * Tests an exception during a pre require event.
   *
   * @legacy-covers ::require
   */
  public function testPreRequireException(): void {
    $error = new \Exception('PreRequire did not go well.');
    TestSubscriber::setException($error, PreRequireEvent::class);
    $this->doStart();
    $response = $this->getPostResponse(
      Url::fromRoute('project_browser.stage.require', ['sandbox_id' => $this->sandboxId]),
      ['project_browser_test_mock/awesome_module'],
    );
    $this->assertSame(500, (int) $response->getStatusCode());
    $this->assertSame('{"message":"SandboxEventException: PreRequire did not go well.","phase":"require"}', (string) $response->getBody());
  }

  /**
   * Tests an exception during a post require event.
   *
   * @legacy-covers ::require
   */
  public function testPostRequireException(): void {
    $error = new \Exception('PostRequire did not go well.');
    TestSubscriber::setException($error, PostRequireEvent::class);
    $this->doStart();
    $response = $this->getPostResponse(
      Url::fromRoute('project_browser.stage.require', ['sandbox_id' => $this->sandboxId]),
      ['project_browser_test_mock/awesome_module'],
    );
    $this->assertSame(500, $response->getStatusCode());
    $this->assertSame('{"message":"SandboxEventException: PostRequire did not go well.","phase":"require"}', (string) $response->getBody());
  }

  /**
   * Tests an error during a pre apply event.
   *
   * @legacy-covers ::apply
   */
  public function testPreApplyError(): void {
    $message = new TranslatableMarkup('This is a PreApply error.');
    $result = ValidationResult::createError([$message]);
    TestSubscriber::setTestResult([$result], PreApplyEvent::class);
    $this->doStart();
    $this->doRequire();
    $contents = $this->drupalGet("/admin/modules/project_browser/install-apply/$this->sandboxId");
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSame('{"message":"SandboxEventException: This is a PreApply error.\n","phase":"apply"}', $contents);
  }

  /**
   * Tests an exception during a pre apply event.
   *
   * @legacy-covers ::apply
   */
  public function testPreApplyException(): void {
    $error = new \Exception('PreApply did not go well.');
    TestSubscriber::setException($error, PreApplyEvent::class);
    $this->doStart();
    $this->doRequire();
    $contents = $this->drupalGet("/admin/modules/project_browser/install-apply/$this->sandboxId");
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSame('{"message":"SandboxEventException: PreApply did not go well.","phase":"apply"}', $contents);
  }

  /**
   * Tests an exception during a post apply event.
   *
   * @legacy-covers ::apply
   */
  public function testPostApplyException(): void {
    $error = new \Exception('PostApply did not go well.');
    TestSubscriber::setException($error, PostApplyEvent::class);
    $this->doStart();
    $this->doRequire();
    $this->doApply();
    $contents = $this->drupalGet("/admin/modules/project_browser/install-post_apply/$this->sandboxId");
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSame('{"message":"SandboxEventException: PostApply did not go well.","phase":"post apply"}', $contents);
  }

  /**
   * Confirms the various versions of the "install in progress" messages.
   *
   * @legacy-covers ::unlock
   */
  public function testInstallUnlockMessage(): void {
    $this->doStart();
    $this->doRequire();

    $request_options = [
      'query' => [
        'redirect' => Url::fromRoute('project_browser.browse')
          ->setRouteParameter('source', 'project_browser_test_mock')
          ->toString(),
      ],
    ];

    $assert_unlock_response = function (string $response, string $expected_message): void {
      $response = Json::decode($response);
      $this->assertSame($expected_message, $response['message']);

      if ($response['unlock_url']) {
        $path_string = parse_url($response['unlock_url'], PHP_URL_PATH);
        $this->assertIsString($path_string);
        $this->assertStringEndsWith('/admin/modules/project_browser/install/unlock', $path_string);
        $query_string = parse_url($response['unlock_url'], PHP_URL_QUERY);
        $this->assertIsString($query_string);
        parse_str($query_string, $query);
        $this->assertNotEmpty($query['token']);
        $this->assertIsString($query['destination']);
        $this->assertStringEndsWith('/admin/modules/browse/project_browser_test_mock', $query['destination']);
      }
    };

    // The sandbox was locked within the last 7 minutes, so it can be unlocked,
    // but we ask the user to have a little patience.
    $response = $this->drupalGet('admin/modules/project_browser/install-begin', $request_options);
    $this->assertSession()->statusCodeEquals(418);
    $assert_unlock_response($response, "The process for adding the project that was locked less than a minute ago might still be in progress. Consider waiting a few more minutes before using [+unlock link].");
    $this->assertInstallInProgress('project_browser_test_mock/awesome_module', ActivationStatus::Absent);
    $this->assertFalse($this->installer->isAvailable());
    $this->assertFalse($this->installer->isApplying());

    // If it's been more than 7 minutes, we should offer the unlock link without
    // any judgment.
    \Drupal::state()->set('InstallerController time offset', 800);
    $response = $this->drupalGet('admin/modules/project_browser/install-begin', $request_options);
    $this->assertSession()->statusCodeEquals(418);
    $this->assertFalse($this->installer->isAvailable());
    $this->assertFalse($this->installer->isApplying());
    $assert_unlock_response($response, "The process for adding the project was locked 13 minutes ago. Use [+ unlock link] to unlock the process.");

    // Once we're applying, we should not offer the unlock link until at least
    // an hour has passed.
    $this->doApply();
    \Drupal::state()->set('InstallerController time offset', 800);
    $response = $this->drupalGet('admin/modules/project_browser/install-begin', $request_options);
    $this->assertSession()->statusCodeEquals(418);
    $this->assertFalse($this->installer->isAvailable());
    $this->assertTrue($this->installer->isApplying());
    $assert_unlock_response($response, "The process for adding the project was locked 13 minutes ago. It should not be unlocked while changes are being applied to the site.");

    // Clear the state cache to ensure that the new time offset will actually
    // be persisted. This prevents this test from randomly failing.
    \Drupal::state()->resetCache();
    // Simulate that we've been applying for 55 minutes: stand firm and don't
    // offer the unlock link, even though it *has* been a really long time.
    \Drupal::state()->set('InstallerController time offset', 55 * 60);
    $response = $this->drupalGet('admin/modules/project_browser/install-begin', $request_options);
    $this->assertSession()->statusCodeEquals(418);
    $assert_unlock_response($response, "The process for adding the project was locked 55 minutes ago. It should not be unlocked while changes are being applied to the site.");

    // Unlocking the stage becomes possible after 1 hour, even if we're still
    // applying.
    \Drupal::state()->set('InstallerController time offset', 75 * 60);
    $this->assertTrue($this->installer->isApplying());
    $response = $this->drupalGet('admin/modules/project_browser/install-begin', $request_options);
    $this->assertSession()->statusCodeEquals(418);
    $assert_unlock_response($response, "The process for adding the project was locked an hour ago. Use [+ unlock link] to unlock the process.");
  }

  /**
   * Confirms the break lock link is available and works.
   *
   * The break lock link is not available once the sandbox is applying.
   *
   * @legacy-covers ::unlock
   */
  public function testCanBreakLock(): void {
    $this->doStart();
    // Try beginning another install while one is in progress, but not yet in
    // the applying stage.
    $content = $this->drupalGet('admin/modules/project_browser/install-begin', [
      'query' => [
        'redirect' => Url::fromRoute('project_browser.browse')
          ->setRouteParameter('source', 'project_browser_test_mock')
          ->toString(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(418);
    $this->assertFalse($this->installer->isAvailable());
    $this->assertFalse($this->installer->isApplying());
    $json = Json::decode($content);
    $this->assertSame('The process for adding projects is locked, but that lock has expired. Use [+ unlock link] to unlock the process and try to add the project again.', $json['message']);
    $unlock_url = parse_url($json['unlock_url']);
    parse_str($unlock_url['query'] ?? '', $unlock_url['query']);
    $unlock_content = $this->drupalGet($unlock_url['path'] ?? '', ['query' => $unlock_url['query']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue($this->installer->isAvailable());
    $this->assertStringContainsString('Operation complete, you can add a new project again.', $unlock_content);
    $this->assertTrue($this->installer->isAvailable());
    $this->assertFalse($this->installer->isApplying());
  }

  /**
   * Confirms sandbox can be unlocked despite a missing Project Browser lock.
   *
   * @legacy-covers ::unlock
   */
  public function testCanUnlockSandboxWithMissingProjectBrowserLock(): void {
    $this->doStart();
    $this->container->get(InstallProgress::class)->clear();
    $content = $this->drupalGet('admin/modules/project_browser/install-begin', [
      'query' => [
        'redirect' => Url::fromRoute('project_browser.browse')
          ->setRouteParameter('source', 'project_browser_test_mock')
          ->toString(),
      ],
    ]);
    $this->assertSession()->statusCodeEquals(418);
    $this->assertFalse($this->installer->isAvailable());
    $this->assertFalse($this->installer->isApplying());
    $json = Json::decode($content);
    $this->assertSame('The process for adding projects is locked, but that lock has expired. Use [+ unlock link] to unlock the process and try to add the project again.', $json['message']);
    $unlock_url = parse_url($json['unlock_url']);
    parse_str($unlock_url['query'] ?? '', $unlock_url['query']);
    $unlock_content = $this->drupalGet($unlock_url['path'] ?? '', ['query' => $unlock_url['query']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue($this->installer->isAvailable());
    $this->assertStringContainsString('Operation complete, you can add a new project again.', $unlock_content);
    $this->assertTrue($this->installer->isAvailable());
    $this->assertFalse($this->installer->isApplying());
  }

  /**
   * Confirm a module and its dependencies can be installed via the endpoint.
   *
   * @legacy-covers \Drupal\project_browser\Controller\ProjectBrowserEndpointController::activate
   */
  public function testActivate(): void {
    // Data for another source is cached in setUp, so we explicitly pass the
    // query parameter in getProjects() to ensure it uses the correct source.
    $this->container->get(QueryManager::class)->getProjects('drupal_core', ['source' => 'drupal_core']);
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules');
    $assert_session->checkboxNotChecked('edit-modules-views-enable');
    $assert_session->checkboxNotChecked('edit-modules-views-ui-enable');

    $response = $this->drupalGet(
      Url::fromRoute('project_browser.activate'),
      [
        'query' => [
          'projects' => 'drupal_core/views_ui',
        ],
      ],
    );
    $assert_session->statusCodeEquals(200);
    $this->assertTrue(json_validate($response));

    $this->drupalGet('admin/modules');
    $assert_session->checkboxChecked('edit-modules-views-enable');
    $assert_session->checkboxChecked('edit-modules-views-ui-enable');
  }

  /**
   * Confirms the project browser in progress input provides the expected value.
   *
   * @param string $project_id
   *   The ID of the project being enabled.
   * @param \Drupal\project_browser\Activator\ActivationStatus $expected_status
   *   The install state.
   */
  protected function assertInstallInProgress(string $project_id, ActivationStatus $expected_status): void {
    $status = $this->container->get('keyvalue')
      ->get('project_browser.install_progress')
      ->get(Project::normalizeId($project_id));

    $this->assertIsArray($status);
    $this->assertSame($expected_status->value, $status[1]);
  }

  /**
   * Sends a POST request to the specified route with the provided project ID.
   *
   * @param \Drupal\Core\Url $url
   *   The URL to which the POST request is sent.
   * @param array $payload
   *   The POST request body. Will be encoded to JSON.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   */
  private function getPostResponse(Url $url, array $payload): ResponseInterface {
    return $this->makeApiRequest('POST', $url, [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
      RequestOptions::BODY => Json::encode($payload),
    ]);
  }

  /**
   * Tests uninstall redirection to the confirmation page.
   */
  public function testUninstallRedirectsToConfirmation(): void {
    $assert_session = $this->assertSession();

    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('package_manager_test_validation'));
    $this->drupalGet('/project-browser/uninstall/package_manager_test_validation', [
      'query' => [
        'return_to' => Url::fromRoute('project_browser.browse')
          ->setRouteParameter('source', 'drupal_core')
          ->toString(),
      ],
    ]);

    $assert_session->pageTextContains(
      'The following modules will be completely uninstalled from your site, and all data from these modules will be lost!'
    );
    $assert_session->addressEquals('/admin/modules/uninstall/confirm');
    $this->submitForm([], 'Uninstall');
    $assert_session->statusCodeEquals(200);
    $assert_session->addressEquals('/admin/modules/browse/drupal_core');

    // Trying to uninstall a module that throws up a validation error should
    // redirect us back to the return URL (or the front page, if no return URL
    // is specified), with the validation error as a message.
    $this->drupalGet('/project-browser/uninstall/project_browser_test');
    $assert_session->statusCodeEquals(200);
    $assert_session->statusMessageContains("Can't touch this!", 'error');

    // Trying to uninstall a module that is depended upon by other modules
    // should also bounce you off.
    $this->container->get(ModuleInstallerInterface::class)->install([
      'field_ui',
      'text',
    ]);
    $this->drupalGet('/project-browser/uninstall/field');
    $assert_session->statusCodeEquals(200);
    $assert_session->statusMessageContains('Field cannot be uninstalled because the following module(s) depend on it and must be uninstalled first: Field UI, Text', 'error');
  }

}
