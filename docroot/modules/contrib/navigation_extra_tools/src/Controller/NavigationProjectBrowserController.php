<?php

declare(strict_types=1);

namespace Drupal\navigation_extra_tools\Controller;

use Drupal\project_browser\ProjectRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Navigation Extra Tools routes.
 */
final class NavigationProjectBrowserController extends ReloadableControllerBase {

  /**
   * The controller constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\project_browser\ProjectRepository $projectRepository
   *   The Project Browser source handler.
   */
  public function __construct(
    readonly RequestStack $requestStack,
    private readonly ProjectRepository $projectRepository,
  ) {
    parent::__construct($requestStack);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('request_stack'),
      $container->get(ProjectRepository::class),
    );
  }

  /**
   * Clears the project browser storage.
   */
  public function clearStorage() {
    $this->projectRepository->clearAll();
    $this->messenger()->addStatus($this->t('Project Browser storage cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

}
