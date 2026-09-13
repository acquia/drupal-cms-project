<?php

namespace Drupal\ai_seo\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\ai_seo\AiSeoAnalyzer;
use Drupal\ai_seo\ReportService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Analyze Url form.
 */
class AnalyzeNodeForm extends FormBase {
  use MessengerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * AI analyzer.
   *
   * @var \Drupal\ai_seo\AiSeoAnalyzer
   */
  protected $analyzer;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $providerManager;

  /**
   * The report service.
   *
   * @var \Drupal\ai_seo\ReportService
   */
  protected $reportService;

  /**
   * Constructs a new Weight table form object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   The language manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\ai_seo\AiSeoAnalyzer $analyzer
   *   AI analyzer.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\ai\AiProviderPluginManager $provider_manager
   *   The AI provider manager.
   * @param \Drupal\ai_seo\ReportService $report_service
   *   The report service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    CurrentRouteMatch $current_route_match,
    LanguageManagerInterface $language_manager,
    AiSeoAnalyzer $analyzer,
    DateFormatterInterface $date_formatter,
    MessengerInterface $messenger,
    AccountInterface $current_user,
    ConfigFactoryInterface $config_factory,
    AiProviderPluginManager $provider_manager,
    ReportService $report_service,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentRouteMatch = $current_route_match;
    $this->languageManager = $language_manager;
    $this->analyzer = $analyzer;
    $this->dateFormatter = $date_formatter;
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
    $this->config = $config_factory->get('ai_seo.settings');
    $this->providerManager = $provider_manager;
    $this->reportService = $report_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('language_manager'),
      $container->get('ai_seo.service'),
      $container->get('date.formatter'),
      $container->get('messenger'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('ai.provider'),
      $container->get('ai_seo.report_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'analyze_url_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $hash = NULL) {

    // Store the report types.
    $report_types = $this->analyzer->getReportTypeOptions();
    $report_type_default_value = 'full';

    $form['container'] = [
      '#type' => 'container',
      '#open' => TRUE,
      '#collapsible' => FALSE,
      '#attributes' => [
        'class' => [
          'analyze-url-form__container',
        ],
      ],
    ];

    // First make sure that the AI config is set up.
    $model_and_provider_string = $this->config->get('provider_and_model') ?? '';
    if (empty($model_and_provider_string)) {
      $model_and_provider_string = $this->providerManager->getSimpleDefaultProviderOptions('chat');
    }
    $model_and_provider = explode('__', $model_and_provider_string);

    if (count($model_and_provider) !== 2) {
      $form['container']['header'] = [
        '#type' => 'markup',
        '#markup' => $this->t('<p>Missing provider, select on at %settings.</p>', [
          '%settings' => Link::createFromRoute('AI SEO/GEO module settings', 'ai_seo.settings')->toString(),
        ]),
        '#attributes' => [
          'class' => [
            'form--header',
          ],
        ],
      ];
      return $form;
    }

    $entity_type_id = 'node';
    $entity = $this->currentRouteMatch->getParameter($entity_type_id);
    if (!($entity instanceof NodeInterface)) {
      $form['container']['header'] = [
        '#type' => 'markup',
        '#markup' => '
          <p>Node not found.</p>
        ',
        '#attributes' => [
          'class' => [
            'form--header',
          ],
        ],
      ];
      return $form;
    }

    $form['container']['header'] = [
      '#type' => 'markup',
      '#markup' => '<div class="ai-seo-intro">'
        . $this->t('Generate an AI-powered SEO/GEO report for this page. Choose a report type below, optionally edit the prompt, then click <strong>Analyze</strong>. Analysis may take up to a minute — stay on the page until it completes.')
        . '</div>',
    ];

    // Get formatted reports using the service
    $formatted_reports = $this->reportService->getFormattedReports($entity->id());
    $report_type_default_value = $this->reportService->getDefaultReportType($formatted_reports);

    // Show the previous reports if there are any.
    if (!empty($formatted_reports)) {
      $form['container']['reports'] = $this->reportService->buildLatestReportElement($formatted_reports, $entity->id());

      if (count($formatted_reports) > 1) {
        $form['container']['older_reports'] = $this->reportService->buildOlderReportsElement($formatted_reports, $entity->id());
      }
    }

    // Check permissions for report generation.
    if ($this->currentUser->hasPermission('create seo reports')) {
      // Start the report generation section.
      $form['container']['new_report'] = [
        '#type' => 'markup',
        '#markup' => '<div class="ai-seo-section-heading">' . $this->t('Generate a new report') . '</div>',
      ];

      $form['container']['report_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Report type'),
        '#options' => $report_types,
        '#default_value' => $report_type_default_value,
        '#description' => $this->t('Select the type of report to generate.'),
        '#ajax' => [
          'callback' => [$this, 'updatePrompt'],
          'wrapper' => 'analyze-url-prompt__container', // Important:  This should be the ID of the *container* of the prompt field.
          'event' => 'change',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Updating prompt...'),
          ],
          'disable-refocus' => TRUE, // Add this line.
        ],
      ];

      // Prompt.
      // Get the default / custom prompt.
      $form['container']['prompt'] = [
        '#type' => 'details',
        '#title' => $this->t('Customize Analysis Prompt'),
        '#open' => FALSE,
        '#collapsible' => TRUE,
        '#attributes' => [
          'class' => ['analyze-url-prompt__container'],
          'id' => 'analyze-url-prompt__container',
        ],
      ];

      $form['container']['prompt']['prompt_to_use'] = [
        '#type' => 'textarea',
        '#title' => 'Your Analysis Prompt',
        '#default_value' => $this->getPromptForReportType($form_state->getValue('report_type')),
        '#rows' => 20,
        '#description' => $this->t('Modify the analysis prompt as needed or use the default settings in the %settings.', [
          '%settings' => Link::createFromRoute('module settings', 'ai_seo.settings')->toString(),
        ]),
        '#required' => TRUE,
      ];

      // Settings.
      // $form['container']['settings'] = [
      //   '#type' => 'details',
      //   '#title' => $this->t('Report Settings'),
      //   '#open' => FALSE,
      //   '#collapsible' => TRUE,
      //   '#attributes' => [
      //     'class' => [
      //       'analyze-url-settings__container',
      //     ],
      //   ],
      // ];

      // If the entity is moderated, show some extra controls.
      if (!empty($entity->moderation_state->value)) {
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

        $form['container']['moderation_state'] = [
          '#type' => 'details',
          '#title' => $this->t('Select Content Revision'),
          '#open' => TRUE,
          '#collapsible' => FALSE,
          '#attributes' => [
            'class' => [
              'analyze-url-settings__container',
            ],
          ],
        ];
        $form['container']['moderation_state']['revision_id'] = [
          '#type' => 'select',
          '#title' => 'Revision to analyze',
          '#required' => TRUE,
          '#default_value' => $published_revision_id,
          '#options' => $options,
        ];
      }

      $form['container']['request_as_anonymous'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Analyze using anonymous visitor'),
        '#default_value' => TRUE,
        '#description' => $this->t('Check this box to analyze the page as an anonymous visitor. This will take into account any access restrictions that are in place.'),
      ];

      $form['container']['footer'] = [
        '#type' => 'markup',
        '#markup' => '<p class="ai-seo-submit-notice">' . $this->t('Report generation can take up to a minute. Do not navigate away while analysis is running.') . '</p>',
      ];

      // Fields to store entity info to submit.
      $form['container']['entity_id'] = [
        '#type' => 'hidden',
        '#title' => 'Entity ID',
        '#required' => TRUE,
        '#value' => $entity->id(),
      ];
      $form['container']['langcode'] = [
        '#type' => 'hidden',
        '#title' => 'Entity langcode',
        '#required' => TRUE,
        '#value' => $entity->language()->getId(),
      ];

      // Build streaming URL and a CSRF token for the JS handler.
      // We use a stable seed rather than relying on the route-path mechanism
      // (_csrf_token: TRUE) because that embeds the token into the URL via the
      // outbound route processor, which can produce session-mismatched tokens
      // when called outside a full render pipeline.
      $stream_url = Url::fromRoute('ai_seo.stream_analysis', ['node' => $entity->id()]);
      $form['#attached']['drupalSettings']['aiSeo'] = [
        'streamUrl' => $stream_url->toString(),
        'csrfToken' => \Drupal::csrfToken()->get('ai-seo-stream'),
      ];
      $form['#attached']['library'][] = 'ai_seo/stream_analyze';

      // Form actions.
      $form['container']['actions'] = ['#type' => 'actions'];
      $form['container']['actions']['submit'] = [
        '#type' => 'button',
        '#value' => $this->t('Analyze'),
        '#attributes' => [
          'class' => ['btn--analyze'],
        ],
      ];
    }

    // The wrapper for search results.
    $form['analyze_results'] = [
      '#prefix' => '<div id="seo-analyze-results">',
      '#suffix' => '</div>',
      '#markup' => '',
    ];

    // Attach library for styling.
    $form['#attached']['library'][] = 'ai_seo/report_styles';

    return $form;
  }

  /**
   * Ajax callback to update the prompt.
   */
  public function updatePrompt(array &$form, FormStateInterface $form_state) {
    $report_type = $form_state->getValue('report_type');
    $title = $this->t('Your Analysis Prompt', []);

    $form['container']['prompt']['prompt_to_use'] = [
      '#type' => 'textarea',
      '#title' => $title,
      // Use the user's input if available, otherwise use the default prompt.
      '#value' => $this->getPromptForReportType($report_type),
      '#attributes' => [
        'readonly' => TRUE,
      ],
      '#rows' => 20,
      '#required' => TRUE,
    ];
    return $form['container']['prompt'];
  }

  /**
   * Helper function to get the prompt based on the report type.
   */
  private function getPromptForReportType($report_type) {
    if (empty($report_type)) {
      $report_type = 'full';
    }
    // Get the report prompt by type.
    $prompt = $this->analyzer->getPromptByType($report_type);
    return $prompt;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Submission is handled client-side via the streaming JS handler.
  }

}
