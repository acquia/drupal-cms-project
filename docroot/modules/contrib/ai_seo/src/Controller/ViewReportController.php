<?php

namespace Drupal\ai_seo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\ai_seo\ReportService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ViewReportController.
 */
class ViewReportController extends ControllerBase {

  /**
   * The report service.
   *
   * @var \Drupal\ai_seo\ReportService
   */
  protected $reportService;

  /**
   * Constructs a ViewReportController object.
   *
   * @param \Drupal\ai_seo\ReportService $report_service
   *   The report service.
   */
  public function __construct(ReportService $report_service) {
    $this->reportService = $report_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_seo.report_service')
    );
  }

  /**
   * Build the page to display a specific report.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param int $report_id
   *   The report ID.
   *
   * @return array
   *   Render array for the report display.
   */
  public function printReport(\Drupal\node\NodeInterface $node, $report_id) {
    $report_id = (int) $report_id;
    $report = $this->reportService->getFormattedReport($node->id(), $report_id);

    if (!$report) {
      throw new NotFoundHttpException('Report not found.');
    }

    $report_number = $this->reportService->getReportNumber($node->id(), $report_id);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['seo-report-view']],
    ];

    $build['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('← Back to SEO/GEO Analysis'),
      '#url' => \Drupal\Core\Url::fromRoute('entity.node.seo_analyzer', ['node' => $node->id()]),
      '#attributes' => ['class' => ['button', 'button--small', 'ai-seo-back-link']],
    ];

    $meta_html = '<div class="ai-seo-report-meta">'
      . '<span class="ai-seo-report-type-badge">' . htmlspecialchars($report['report_type']) . '</span>'
      . '<span class="ai-seo-report-time">' . htmlspecialchars($report['time_ago']) . '</span>'
      . (!empty($report['revision_row']) ? $report['revision_row'] : '')
      . '</div>'
      . '<div class="ai-seo-node-title">' . htmlspecialchars($node->getTitle()) . ' — ' . $this->t('Report #@n', ['@n' => $report_number]) . '</div>';

    $build['metadata'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ai-seo-report-metadata']],
      'info' => ['#markup' => Markup::create($meta_html)],
    ];

    $build['report_content'] = [
      '#type' => 'details',
      '#title' => $this->t('SEO/GEO Analysis Report'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['report-content', 'reports__container']],
      'content' => ['#markup' => Markup::create($report['report'])],
    ];

    $build['prompt_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Prompt used'),
      '#open' => FALSE,
      '#attributes' => ['class' => ['report-prompt']],
      'prompt' => [
        '#type' => 'textarea',
        '#title' => $this->t('AI Prompt'),
        '#value' => $report['prompt'],
        '#rows' => 10,
        '#attributes' => ['readonly' => 'readonly'],
      ],
    ];

    $build['html_details'] = [
      '#type' => 'details',
      '#title' => $this->t('HTML analyzed'),
      '#open' => FALSE,
      '#attributes' => ['class' => ['report-html']],
      'html' => [
        '#type' => 'textarea',
        '#title' => $this->t('Source HTML'),
        '#value' => $report['html'],
        '#rows' => 15,
        '#attributes' => ['readonly' => 'readonly'],
      ],
    ];

    $build['#attached']['library'][] = 'ai_seo/report_styles';

    return $build;
  }

  /**
   * Page title callback.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param int $report_id
   *   The report ID.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function getTitle(?\Drupal\node\NodeInterface $node = NULL, $report_id = NULL) {
    if ($node && $report_id !== NULL) {
      $report_number = $this->reportService->getReportNumber($node->id(), (int) $report_id);
      return $this->t('SEO/GEO Report #@number — @title', [
        '@number' => $report_number,
        '@title' => $node->getTitle(),
      ]);
    }
    return $this->t('SEO/GEO Report');
  }

}
