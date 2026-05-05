<?php

namespace Drupal\project_browser\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ScrollTopCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\project_browser\ActivationManager;
use Drupal\project_browser\ProjectBrowser\Normalizer;
use Drupal\project_browser\QueryManager;
use Drupal\project_browser\InstallProgress;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectRepository;
use Drupal\project_browser\RefreshProjectsCommand;
use Drupal\system\Form\ModulesUninstallForm;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for the proxy layer.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class ProjectBrowserEndpointController extends ControllerBase {

  public function __construct(
    private readonly QueryManager $queryManager,
    private readonly ProjectBrowserSourceManager $sourceManager,
    private readonly ProjectRepository $projectRepository,
    private readonly Normalizer $normalizer,
    private readonly ModuleInstallerInterface $moduleInstaller,
    private readonly ModuleExtensionList $moduleList,
    private readonly ActivationManager $activationManager,
    private readonly InstallProgress $installProgress,
    #[Autowire(service: 'logger.channel.project_browser')] private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns a list of projects that match a query.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A list of projects.
   *
   * @see \Drupal\project_browser\ProjectBrowser\ProjectsResultsPage
   */
  public function getAllProjects(Request $request): JsonResponse {
    $current_sources = $this->sourceManager->getAllEnabledSources();
    $query = $this->buildQuery($request);
    if (!$current_sources || empty($query['source'])) {
      return new JsonResponse([], Response::HTTP_ACCEPTED);
    }

    $results = $this->queryManager->getProjects($query['source'], $query);
    return new JsonResponse($this->normalizer->normalize($results));
  }

  /**
   * Prepares to uninstall a module.
   *
   * @param string $name
   *   The machine name of the module to uninstall.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirection to the uninstall confirmation page.
   */
  public function uninstall(string $name, Request $request): RedirectResponse {
    $return_to = $request->query->get('return_to', Url::fromRoute('<front>')->toString());
    assert(is_string($return_to));

    // Ensure this module CAN be uninstalled. If it can't, redirect back to the
    // return URL with the messages set as errors.
    $validation_errors = $this->moduleInstaller->validateUninstall([$name]);

    // Check if the module is required by any installed modules, and flag an
    // error if so.
    $required_by = array_intersect_key(
      $this->moduleList->get($name)->required_by,
      $this->moduleList->getAllInstalledInfo(),
    );
    if ($required_by) {
      $required_by = array_map($this->moduleList->getName(...), array_keys($required_by));
      natcasesort($required_by);
      $validation_errors['project_browser'] = [
        $this->t('@name cannot be uninstalled because the following module(s) depend on it and must be uninstalled first: @list', [
          '@name' => $this->moduleList->getName($name),
          '@list' => implode(', ', $required_by),
        ]),
      ];
    }
    if ($validation_errors) {
      array_walk_recursive($validation_errors, function ($error): void {
        $this->messenger()->addError($error);
      });
      return new RedirectResponse($return_to);
    }

    $form_state = new FormState();
    $form_state->setValue('uninstall', [$name => $name]);
    $this->formBuilder()->submitForm(ModulesUninstallForm::class, $form_state);

    return $this->redirect('system.modules_uninstall_confirm', options: [
      'query' => [
        'destination' => $return_to,
      ],
    ]);
  }

  /**
   * Builds the query based on the current request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   See \Drupal\project_browser\QueryManager::getProjects().
   */
  private function buildQuery(Request $request): array {
    // Validate and build query.
    $query = [
      'page' => (int) $request->query->get('page', 0),
      'limit' => (int) $request->query->get('limit', 12),
    ];

    $machine_name = $request->query->get('machine_name');
    if ($machine_name) {
      $query['machine_name'] = $machine_name;
    }

    $sort = $request->query->get('sort');
    if ($sort) {
      $query['sort'] = $sort;
    }

    $title = $request->query->get('search');
    if ($title) {
      $query['search'] = $title;
    }

    $categories = $request->query->get('categories');
    if ($categories) {
      $query['categories'] = $categories;
    }

    $maintenance_status = $request->query->get('maintenance_status');
    if ($maintenance_status) {
      $query['maintenance_status'] = $maintenance_status;
    }

    $development_status = $request->query->get('development_status');
    if ($development_status) {
      $query['development_status'] = $development_status;
    }

    $security_advisory_coverage = $request->query->get('security_advisory_coverage');
    if ($security_advisory_coverage) {
      $query['security_advisory_coverage'] = $security_advisory_coverage;
    }

    $displayed_source = $request->query->get('source', 0);
    if ($displayed_source) {
      $query['source'] = $displayed_source;
    }

    return $query;
  }

  /**
   * Installs an already downloaded project.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A response that can be used by the client-side AJAX system.
   */
  public function activate(Request $request): AjaxResponse {
    $projects = $request->query->get('projects') ?? [];
    if ($projects) {
      assert(is_string($projects));
      $projects = explode(',', $projects);
    }
    assert(is_array($projects));

    // Load all the projects from permanent storage and key them by their
    // fully qualified project IDs.
    $projects = array_combine(
      $projects,
      array_map($this->projectRepository->get(...), $projects),
    );

    $response = new AjaxResponse();
    try {
      foreach ($this->activationManager->activate(...$projects) as $command) {
        $response->addCommand($command);
      }
    }
    catch (\Throwable $e) {
      $response->addCommand(new MessageCommand(
        $e->getMessage(),
        options: [
          'type' => MessengerInterface::TYPE_ERROR,
          'id' => 'activation_error',
        ],
      ));
      $response->addCommand(new ScrollTopCommand('[data-drupal-messages]'));
      Error::logException($this->logger, $e);
    }

    // Capture any messages set from activation and send to the frontend.
    // We do this in case any modules use their hook_install() to inform the
    // user about any "next steps" or other important information. We do it
    // here in the Endpoint Controller so we don't have to do it in each
    // individual Activator.
    foreach ($this->messenger()->all() as $type => $messages) {
      foreach ($messages as $message) {
        $response->addCommand(new MessageCommand(
          $message,
          options: ['type' => $type],
          clear_previous: FALSE,
        ));
      }
    }
    $this->messenger->deleteAll();

    $this->installProgress->clear();
    return $response->addCommand(new RefreshProjectsCommand());
  }

}
