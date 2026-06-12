<?php

namespace Drupal\ai_seo;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Service to analyze content using AI and manage reports.
 */
class ReportService {

  use StringTranslationTrait;

  /**
   * Max response tokens.
   *
   * @var int
   */
  protected $maxTokens;

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * AI client.
   *
   * @var \AI\Client
   */
  protected $client;

  /**
   * The AI SEO settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * AI analyzer.
   *
   * @var \Drupal\ai_seo\AiSeoAnalyzer
   */
  protected $analyzer;

  /**
   * Creates the SEO Analyzer service.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI provider manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\ai_seo\AiSeoAnalyzer $analyzer
   *   AI analyzer.
   */
  public function __construct(
    AiProviderPluginManager $aiProvider,
    Connection $connection,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger,
    MessengerInterface $messenger,
    DateFormatterInterface $date_formatter,
    AiSeoAnalyzer $analyzer,
  ) {
    $this->aiProvider = $aiProvider;
    $this->connection = $connection;
    $this->config = $config_factory->get('ai_seo.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('ai_seo');
    $this->messenger = $messenger;
    $this->dateFormatter = $date_formatter;
    $this->analyzer = $analyzer;

    // Response token length.
    $this->maxTokens = 3000;
  }

  /**
   * Get a single formatted report by ID.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param int $report_id
   *   The report ID (rid from database).
   *
   * @return array|null
   *   Single formatted report or NULL if not found.
   */
  public function getFormattedReport($entity_id, $report_id) {
    $report = $this->analyzer->getReport($entity_id, $report_id);

    if (!$report) {
      return NULL;
    }

    $report_types = $this->analyzer->getReportTypeOptions();
    $time_ago = $this->dateFormatter->formatTimeDiffSince($report['timestamp']) . ' ' . $this->t('ago');

    $report_type_id = $report['report_type'] ?? 'full';
    $report_type = $report_types[$report_type_id] ?? $report_types['full'];

    $revision_row = '';
    if (!empty($report['revision_id'])) {
      $revision_url = \Drupal\Core\Url::fromRoute('entity.node.revision', [
        'node' => $entity_id,
        'node_revision' => $report['revision_id'],
      ])->toString();
      $revision_row = '<div class="report--revision-id"><label><strong>' . $this->t('Created for revision') . '</strong> <a href="' . $revision_url . '">#' . $report['revision_id'] . '</a></label></div>';
    }

    return [
      'timestamp' => $report['timestamp'],
      'time_ago' => $time_ago,
      'report_type' => $report_type,
      'report_type_id' => $report_type_id,
      'revision_id' => $report['revision_id'] ?? NULL,
      'revision_row' => $revision_row,
      'report' => $this->cleanTags($report['report']),
      'prompt' => $report['prompt'],
      'html' => $report['html'],
      'rid' => $report['rid'],
    ];
  }

  /**
   * Get report count for an entity (for calculating report numbers).
   *
   * @param int $entity_id
   *   The entity ID.
   *
   * @return int
   *   Total number of reports for this entity.
   */
  public function getReportCount($entity_id) {
    return $this->connection->select('ai_seo', 'a')
      ->condition('entity_id', $entity_id)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Get the report number for a specific report ID.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param int $report_id
   *   The report ID (rid from database).
   *
   * @return int
   *   The report number (newest = 1, oldest = highest number).
   */
  public function getReportNumber($entity_id, $report_id) {
    // Count how many reports are newer than this one
    $newer_reports = $this->connection->select('ai_seo', 'a')
      ->condition('entity_id', $entity_id)
      ->condition('rid', $report_id, '>')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $newer_reports + 1;
  }

  /**
   * Get reports for an entity and format them for display.
   *
   * @param int $entity_id
   *   The entity ID.
   *
   * @return array
   *   Array of formatted reports.
   */
  public function getFormattedReports($entity_id) {
    $reports = $this->analyzer->getReports($entity_id);
    $report_types = $this->analyzer->getReportTypeOptions();

    $formatted_reports = [];

    foreach ($reports as $index => $report) {
      $time_ago = $this->dateFormatter->formatTimeDiffSince($report['timestamp']) . ' ' . $this->t('ago');

      $report_type_id = $report['report_type'] ?? 'full';
      $report_type = $report_types[$report_type_id] ?? $report_types['full'];

      $revision_row = '';
      if (!empty($report['revision_id'])) {
        $revision_url = \Drupal\Core\Url::fromRoute('entity.node.revision', [
          'node' => $entity_id,
          'node_revision' => $report['revision_id'],
        ])->toString();
        $revision_row = '<div class="report--revision-id"><label><strong>' . $this->t('Created for revision') . '</strong> <a href="' . $revision_url . '">#' . $report['revision_id'] . '</a></label></div>';
      }

      $formatted_reports[] = [
        'index' => $index,
        'timestamp' => $report['timestamp'],
        'time_ago' => $time_ago,
        'report_type' => $report_type,
        'report_type_id' => $report_type_id,
        'revision_id' => $report['revision_id'] ?? NULL,
        'revision_row' => $revision_row,
        'report' => $this->cleanTags($report['report']),
        'prompt' => $report['prompt'],
        'html' => $report['html'],
        'rid' => $report['rid'],
      ];
    }

    return $formatted_reports;
  }

  /**
   * Clean tags to prevent broken HTML.
   *
   * @param string $html
   *   The HTML content to clean.
   *
   * @return string
   *   The cleaned HTML content.
   */
  protected function cleanTags($html) {
    // Define tags to replace with their escaped versions
    $tags_to_replace = ['noscript', 'title'];

    $cleanedHtml = $html;

    foreach ($tags_to_replace as $tag) {
      $cleanedHtml = str_replace(["<{$tag}>", "</{$tag}>"], ["&lt;{$tag}&gt;", "&lt;/{$tag}&gt;"], $cleanedHtml);
    }

    return trim($cleanedHtml);
  }


  /**
   * Build the latest report display form element.
   *
   * @param array $formatted_reports
   *   Array of formatted reports.
   * @param int $entity_id
   *   The entity ID.
   *
   * @return array
   *   Form element for latest report.
   */
  public function buildLatestReportElement(array $formatted_reports, $entity_id) {
    if (empty($formatted_reports)) {
      return [];
    }

    $latest_report = $formatted_reports[0];

    $meta_html = '<div class="ai-seo-report-meta">'
      . '<span class="ai-seo-report-type-badge">' . htmlspecialchars($latest_report['report_type']) . '</span>'
      . '<span class="ai-seo-report-time">' . htmlspecialchars($latest_report['time_ago']) . '</span>'
      . (!empty($latest_report['revision_row']) ? $latest_report['revision_row'] : '')
      . '</div>';

    return [
      '#type' => 'details',
      '#title' => $this->t('Latest report'),
      '#open' => TRUE,
      '#collapsible' => FALSE,
      '#attributes' => ['class' => ['reports__container']],
      'view_full_link' => [
        '#type' => 'link',
        '#title' => $this->t('View Full Report'),
        '#url' => \Drupal\Core\Url::fromRoute('entity.node.view_seo_report', [
          'node' => $entity_id,
          'report_id' => $latest_report['rid'],
        ]),
        '#attributes' => ['class' => ['button', 'button--primary', 'button--small']],
        '#weight' => -10,
      ],
      'meta' => [
        '#markup' => Markup::create($meta_html),
      ],
      'report' => [
        '#markup' => Markup::create($latest_report['report']),
      ],
      'prompt' => [
        '#type' => 'details',
        '#title' => $this->t('Prompt used'),
        '#open' => FALSE,
        'content' => [
          '#type' => 'textarea',
          '#disabled' => TRUE,
          '#readonly' => TRUE,
          '#value' => $latest_report['prompt'],
          '#rows' => 10,
        ],
      ],
      'html' => [
        '#type' => 'details',
        '#title' => $this->t('HTML analyzed'),
        '#open' => FALSE,
        'content' => [
          '#type' => 'textarea',
          '#disabled' => TRUE,
          '#readonly' => TRUE,
          '#value' => $latest_report['html'],
          '#rows' => 10,
        ],
      ],
    ];
  }

  /**
   * Build the older reports display form element.
   *
   * @param array $formatted_reports
   *   Array of formatted reports.
   * @param int $entity_id
   *   The entity ID.
   *
   * @return array
   *   Form element for older reports.
   */
  public function buildOlderReportsElement(array $formatted_reports, $entity_id) {
    $report_count = count($formatted_reports);

    if ($report_count <= 1) {
      return [];
    }

    $element = [
      '#type' => 'details',
      '#title' => $this->t('All reports (@count)', ['@count' => $report_count]),
      '#open' => FALSE,
      '#collapsible' => FALSE,
      '#attributes' => ['class' => ['older-reports__container']],
    ];

    // Build table header
    $header = [
      $this->t('Report #'),
      $this->t('Type'),
      $this->t('Created'),
      $this->t('Revision'),
      $this->t('Actions'),
    ];

    $rows = [];

    for ($i = 0; $i < $report_count; $i++) {
      $report = $formatted_reports[$i];
      $report_number = $report_count - $i;

      // Build revision cell
      $revision_cell = '';
      if (!empty($report['revision_id'])) {
        $revision_cell = [
          'data' => [
            '#type' => 'link',
            '#title' => '#' . $report['revision_id'],
            '#url' => \Drupal\Core\Url::fromRoute('entity.node.revision', [
              'node' => $entity_id,
              'node_revision' => $report['revision_id'],
            ]),
            '#attributes' => [
              'target' => '_blank',
            ],
          ],
        ];
      } else {
        $revision_cell = $this->t('Current');
      }

      // Build actions cell
      $actions_cell = [
        'data' => [
          '#type' => 'link',
          '#title' => $this->t('View Full Report'),
          '#url' => \Drupal\Core\Url::fromRoute('entity.node.view_seo_report', [
            'node' => $entity_id,
            'report_id' => $report['rid'],
          ]),
          '#attributes' => [
            'class' => ['button', 'button--small'],
          ],
        ],
      ];

      $rows[] = [
        'data' => [
          $report_number,
          $report['report_type'],
          $report['time_ago'],
          $revision_cell,
          $actions_cell,
        ],
        'class' => ['report-row'],
      ];
    }

    $element['reports_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No older reports available.'),
      '#attributes' => [
        'class' => ['older-reports-table'],
      ],
    ];

    return $element;
  }

  /**
   * Build moderation state form element for content revisions.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The node entity.
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   Form element for revision selection.
   */
  public function buildModerationStateElement(NodeInterface $entity, $entity_type_id = 'node') {
    if (empty($entity->moderation_state->value)) {
      return [];
    }

    /** @var \Drupal\content_moderation\ModerationInformation $moderation_information_service */
    $moderationInformationService = \Drupal::service('content_moderation.moderation_information');
    $workflow = $moderationInformationService->getWorkflowForEntity($entity);
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    // Find the revisions and build the options.
    $revisions = $storage->revisionIds($entity);
    $published_revision_id = NULL;
    $revisions = array_reverse($revisions);
    $options = [];

    foreach ($revisions as $revision_id) {
      $revision = $storage->loadRevision($revision_id);
      if ($revision->isPublished() && empty($published_revision_id)) {
        $published_revision_id = $revision_id;
      }
      $created_at = $this->dateFormatter->format($revision->getChangedTime(), 'short');

      $options[$revision_id] = $this->t('#:revision_id - :revision_created - :revision_label:current_label', [
        ':revision_id' => $revision_id,
        ':revision_created' => $created_at,
        ':revision_label' => $workflow->getTypePlugin()->getState($revision->moderation_state->value)->label(),
        ':current_label' => ($revision_id === $published_revision_id) ? ' (current revision)' : '',
      ]);
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Select Content Revision'),
      '#open' => TRUE,
      '#collapsible' => FALSE,
      '#attributes' => [
        'class' => [
          'analyze-url-settings__container',
        ],
      ],
      'revision_id' => [
        '#type' => 'select',
        '#title' => 'Revision to analyze',
        '#required' => TRUE,
        '#default_value' => $published_revision_id,
        '#options' => $options,
      ],
    ];
  }

  /**
   * Get the default report type from the latest report or fallback.
   *
   * @param array $formatted_reports
   *   Array of formatted reports.
   *
   * @return string
   *   The report type ID.
   */
  public function getDefaultReportType(array $formatted_reports) {
    if (!empty($formatted_reports)) {
      return $formatted_reports[0]['report_type_id'];
    }
    return 'full';
  }

}
