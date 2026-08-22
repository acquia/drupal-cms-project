<?php

namespace Drupal\ai_seo\Plugin\QueueWorker;

use Drupal\ai_seo\AiSeoAnalyzer;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued AI SEO/GEO analysis jobs.
 *
 * @QueueWorker(
 *   id = "ai_seo_analysis",
 *   title = @Translation("AI SEO/GEO Analysis"),
 *   cron = {"time" = 120}
 * )
 */
class AiSeoAnalysisWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AiSeoAnalyzer $analyzer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_seo.service'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Expected $data keys:
   *   - report_type: string
   *   - entity_type_id: string
   *   - entity_id: int
   *   - revision_id: int|null
   *   - langcode: string|null
   *   - options: array
   */
  public function processItem($data): void {
    $this->analyzer->analyzeEntity(
      $data['report_type'],
      $data['entity_type_id'],
      $data['entity_id'],
      $data['revision_id'] ?? NULL,
      'full',
      $data['langcode'] ?? NULL,
      $data['options'] ?? [],
    );
  }

}
