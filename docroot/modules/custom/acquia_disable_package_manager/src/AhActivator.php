<?php

namespace Drupal\acquia_disable_package_manager;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\project_browser\Activator\ActivationStatus;
use Drupal\project_browser\Activator\ActivatorInterface;
use Drupal\project_browser\Activator\InstructionsInterface;
use Drupal\project_browser\Activator\TasksInterface;
use Drupal\project_browser\ProjectBrowser\Project;

final class AhActivator implements InstructionsInterface, TasksInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly ActivatorInterface $decorated,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getStatus(Project $project): ActivationStatus {
    return $this->decorated->getStatus($project);
  }

  /**
   * {@inheritdoc}
   */
  public function supports(Project $project): bool {
    return $this->decorated->supports($project);
  }

  /**
   * {@inheritdoc}
   */
  public function activate(Project $project): ?array {
    return $this->decorated->activate($project);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstructions(Project $project, ?string $source_id = NULL): string {
    if (AcquiaDrupalEnvironmentDetector::isAhEnv() && $this->getStatus($project) === ActivationStatus::Absent) {
      return (string) $this->t('Acquia Cloud is write-protected by design. To add modules or recipes to your codebase, use a Cloud IDE. You can open or create a Cloud ID from your subscription.');
    }
    elseif ($this->decorated instanceof InstructionsInterface) {
      return $this->decorated->getInstructions($project, $source_id);
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTasks(Project $project, ?string $source_id = NULL): array {
    return $this->decorated instanceof TasksInterface
      ? $this->decorated->getTasks($project, $source_id)
      : [];
  }


}
