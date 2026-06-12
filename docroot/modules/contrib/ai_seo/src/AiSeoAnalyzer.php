<?php

namespace Drupal\ai_seo;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use League\CommonMark\CommonMarkConverter;

/**
 * Service to analyze content using AI.
 */
class AiSeoAnalyzer {

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
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Service to render entity HTML.
   *
   * @var \Drupal\ai_seo\RenderEntityHtmlService
   */
  protected $renderEntityHtml;

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
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The http client.
   * @param \Drupal\ai_seo\RenderEntityHtmlService $render_entity_html
   *   Service to render entity HTML.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    AiProviderPluginManager $aiProvider,
    Connection $connection,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    ClientInterface $http_client,
    RenderEntityHtmlService $render_entity_html,
    LoggerChannelFactoryInterface $logger,
    MessengerInterface $messenger,
  ) {
    $this->aiProvider = $aiProvider;
    $this->connection = $connection;
    $this->config = $config_factory->get('ai_seo.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
    $this->renderEntityHtml = $render_entity_html;
    $this->logger = $logger->get('ai_seo');
    $this->messenger = $messenger;

    // Response token length.
    $this->maxTokens = 2000;
  }

  /**
   * Render entity as HTML and analyze it.
   */
  public function analyzeEntity(string $report_type_id, string $entity_type_id, int $entity_id, ?int $revision_id = NULL, string $view_mode = 'full', ?string $langcode = NULL, array $options = []) {
    // Get the report prompt by type.
    $prompt = $this->getPromptByType($report_type_id);

    // Fetch the raw HTML.
    $html = $this->fetchEntityHtml($entity_type_id, $entity_id, $revision_id, $view_mode, $langcode, $options);

    // Set the report type in options.
    $options['report_type'] = $report_type_id;

    // Analyze HTML, store & return results.
    $results = $this->analyzeHtml($html, $prompt, NULL, $entity_type_id, $entity_id, $revision_id, $langcode, $options);

    return $results;
  }

  /**
   * Fetch given HTML from given URL and analyze it.
   */
  public function analyzeUrl(string $url, string $report_type_id, array $options = []) {
    // Get the report prompt by type.
    $prompt = $this->getPromptByType($report_type_id);

    // Fetch the raw HTML.
    $html = $this->fetchHtml($url);

    // Set the report type in options.
    $options['report_type'] = $report_type_id;

    // Analyze HTML, store & return results.
    $results = $this->analyzeHtml($html, $prompt, $url, NULL, NULL, NULL, NULL, $options);

    return $results;
  }

  public function getPromptByType($report_type_id) {
    // Get the report type entity to get the prompt.
    $report_type = $this->entityTypeManager->getStorage('ai_seo_report_type')->load($report_type_id);
    if (!$report_type) {
      throw new \InvalidArgumentException("Report type '{$report_type_id}' not found.");
    }

    $prompt = $report_type->getPrompt();

    return $prompt;
  }

  /**
   * Get available report types as options array.
   *
   * @return array
   *   Array of report type options.
   */
  public function getReportTypeOptions() {
    $report_types = $this->entityTypeManager->getStorage('ai_seo_report_type')->loadMultiple();
    $options = [];
    foreach ($report_types as $report_type) {
      if ($report_type->status()) {
        $options[$report_type->id()] = $report_type->label();
      }
    }
    return $options;
  }

  /**
   * Get a specific report type prompt.
   *
   * @param string $report_type_id
   *   The report type ID.
   *
   * @return string
   *   The prompt text.
   */
  public function getReportTypePrompt(string $report_type_id) {
    $report_type = $this->entityTypeManager->getStorage('ai_seo_report_type')->load($report_type_id);
    return $report_type ? $report_type->getPrompt() : '';
  }

  // Remove the old hardcoded prompt methods and replace getPromptText()
  /**
   * Return either default or custom prompt.
   *
   * @param string $report_type_id
   *   The report type ID to get prompt for.
   *
   * @return string
   *   Prompt text.
   */
  public function getPromptText(string $report_type_id = 'full') {
    // Get the custom prompt if one is set.
    $custom_prompt = $this->config->get('custom_prompt');

    if (!empty($custom_prompt)) {
      return $custom_prompt;
    }

    // Otherwise get from report type entity.
    return $this->getReportTypePrompt($report_type_id);
  }

  /**
   * Analyze passed HTML and return results.
   */
  /**
   * Analyze raw HTML directly — useful for draft/unsaved content from forms.
   *
   * Unlike analyzeEntity() and analyzeUrl(), this method does not fetch or
   * render content itself, and by default does not persist the report so that
   * draft analyses do not pollute the report history.
   *
   * @param string $html
   *   Raw HTML to analyze (e.g. rendered from an unsaved entity).
   * @param string $report_type_id
   *   Report type machine name.
   * @param array $options
   *   Options passed through to analyzeHtml(). Set 'save' => TRUE to persist.
   *
   * @return string|null
   *   Analysis result as HTML, or NULL on failure.
   */
  public function analyzeRawHtml(string $html, string $report_type_id = 'full', array $options = []): ?string {
    $prompt = $this->getPromptByType($report_type_id);
    $options['report_type'] = $report_type_id;
    $options += ['save' => FALSE];
    return $this->analyzeHtml($html, $prompt, NULL, NULL, NULL, NULL, NULL, $options);
  }

  /**
   * Returns the strict output format instruction appended to every prompt.
   */
  public function getFormatInstruction(): string {
    return "\n\n---\n\n**OUTPUT FORMAT — follow exactly, no exceptions:**\n\n"
      . "- Respond in markdown only. Do NOT wrap the response in a code block.\n"
      . "- Use `##` for each main section heading (matching the numbered sections in the instructions above).\n"
      . "- Use `###` for subsection headings within a section.\n"
      . "- Use `**bold**` for labels, field names, and key terms.\n"
      . "- Use a single `-` bullet list for all assessment points and recommendations. No nested bullets deeper than one level.\n"
      . "- When a score or rating is requested, format it as `**Score: X/10**` or `**Rating: Low/Medium/High**` on its own line at the start of that section.\n"
      . "- When providing a before/after rewrite example, use this exact format:\n"
      . "  > **Before:** [original text]\n\n"
      . "  > **After:** [improved text]\n"
      . "- When providing a JSON-LD example, wrap it in a fenced code block: ` ```json ` ... ` ``` `\n"
      . "- End every report with a `## Priority Actions` section containing a numbered list of the top recommendations ordered by impact.\n"
      . "- Do not add a preamble, meta-commentary, or sign-off. Start directly with the first `##` section heading.";
  }

  /**
   * Strips any wrapping code-block markers the AI may have added despite instructions.
   */
  public function stripCodeBlockWrapper(string $text): string {
    if (substr($text, 0, 3) === "```") {
      if (substr($text, 0, 11) === "```markdown") {
        $text = ltrim(substr($text, 11));
      }
      elseif (substr($text, 0, 7) === "```html") {
        $text = ltrim(substr($text, 7));
      }
      else {
        $text = ltrim(substr($text, 3));
      }
    }
    if (substr($text, -3) === "```") {
      $text = rtrim(substr($text, 0, -3));
    }
    return trim($text);
  }

  /**
   * Public wrapper for saveReport() — used by the streaming controller.
   */
  public function persistReport(string $report, string $prompt, string $html, ?string $url, ?string $entity_type_id, ?int $entity_id, ?int $revision_id, ?string $langcode, array $options = []): int {
    return $this->saveReport($report, $prompt, $html, $url, $entity_type_id, $entity_id, $revision_id, $langcode, $options);
  }

  /**
   * Renders entity HTML, cleans it, builds the prompt, and resolves the AI
   * provider — returning everything the streaming controller needs to run the
   * chat call itself without duplicating setup logic.
   *
   * @return array{prompt: string, cleaned_html: string, provider: object, model: string, system_prompt: string}
   */
  public function prepareEntityAnalysis(string $report_type_id, string $entity_type_id, int $entity_id, ?int $revision_id, string $view_mode, ?string $langcode, array $options): array {
    $prompt = $this->getPromptByType($report_type_id);
    $prompt .= $this->getFormatInstruction();

    $html = $this->fetchEntityHtml($entity_type_id, $entity_id, $revision_id, $view_mode, $langcode, $options);
    $cleaned_html = $this->parseHtml($html);

    $model_and_provider_string = $this->config->get('provider_and_model') ?? '';
    if (empty($model_and_provider_string)) {
      $model_and_provider_string = $this->aiProvider->getSimpleDefaultProviderOptions('chat');
    }
    $ai_settings = explode('__', $model_and_provider_string);
    if (count($ai_settings) !== 2) {
      throw new \Exception('No AI provider or model is configured for this operation.');
    }

    $provider = $this->aiProvider->createInstance($ai_settings[0], [
      'http_client_options' => ['timeout' => 500],
    ]);

    return [
      'prompt' => $prompt,
      'cleaned_html' => $cleaned_html,
      'provider' => $provider,
      'model' => $ai_settings[1],
      'system_prompt' => $this->getSystemPromptText(),
    ];
  }

  /**
   * Like prepareEntityAnalysis() but accepts raw HTML directly.
   *
   * Used by the draft-streaming endpoint where the HTML comes from an unsaved
   * entity rendered in the browser session rather than from the DB.
   *
   * @return array{prompt: string, cleaned_html: string, provider: object, model: string, system_prompt: string}
   */
  public function prepareHtmlAnalysis(string $html, string $report_type_id = 'full'): array {
    $prompt = $this->getPromptByType($report_type_id);
    $prompt .= $this->getFormatInstruction();
    $cleaned_html = $this->parseHtml($html);

    $model_and_provider_string = $this->config->get('provider_and_model') ?? '';
    if (empty($model_and_provider_string)) {
      $model_and_provider_string = $this->aiProvider->getSimpleDefaultProviderOptions('chat');
    }
    $ai_settings = explode('__', $model_and_provider_string);
    if (count($ai_settings) !== 2) {
      throw new \Exception('No AI provider or model is configured for this operation.');
    }

    $provider = $this->aiProvider->createInstance($ai_settings[0], [
      'http_client_options' => ['timeout' => 500],
    ]);

    return [
      'prompt' => $prompt,
      'cleaned_html' => $cleaned_html,
      'provider' => $provider,
      'model' => $ai_settings[1],
      'system_prompt' => $this->getSystemPromptText(),
    ];
  }

  /**
   * Prepares a focused field-level analysis from tempstore context data.
   *
   * Unlike prepareHtmlAnalysis(), this method does not parse HTML — it works
   * directly with the raw field value string and infers field intent from the
   * field name and type to build a targeted, concise SEO/GEO prompt.
   *
   * @param array $data
   *   Context array with keys: field_name, field_label, field_value,
   *   field_type, page_title, content_type.
   *
   * @return array{prompt: string, cleaned_html: string, provider: object, model: string, system_prompt: string}
   */
  public function prepareFieldAnalysis(array $data): array {
    $prompt = $this->getFieldPrompt(
      $data['field_name'] ?? '',
      $data['field_label'] ?? '',
      $data['field_value'] ?? '',
      $data['field_type'] ?? '',
      $data['page_title'] ?? '',
      $data['content_type'] ?? '',
      $data['site_name'] ?? '',
      $data['metatag_title'] ?? '',
    );

    $model_and_provider_string = $this->config->get('provider_and_model') ?? '';
    if (empty($model_and_provider_string)) {
      $model_and_provider_string = $this->aiProvider->getSimpleDefaultProviderOptions('chat');
    }
    $ai_settings = explode('__', $model_and_provider_string);
    if (count($ai_settings) !== 2) {
      throw new \Exception('No AI provider or model is configured for this operation.');
    }

    $provider = $this->aiProvider->createInstance($ai_settings[0], [
      'http_client_options' => ['timeout' => 500],
    ]);

    return [
      'prompt' => $prompt,
      'cleaned_html' => $data['field_value'] ?? '',
      'provider' => $provider,
      'model' => $ai_settings[1],
      'system_prompt' => $this->getSystemPromptText(),
    ];
  }

  /**
   * Builds a field-type-aware SEO/GEO analysis prompt.
   *
   * Selects a targeted prompt template based on the field name and type,
   * then prepends page context so the AI can give relevant advice.
   */
  public function getFieldPrompt(string $field_name, string $field_label, string $field_value, string $field_type, string $page_title, string $content_type, string $site_name = '', string $metatag_title = ''): string {
    $context = "You are analyzing a single field from a \"{$content_type}\" page.\n";
    if ($field_name !== 'title' && !empty($page_title)) {
      $context .= "Page title: \"{$page_title}\"\n";
    }
    $context .= "Field being analyzed: \"{$field_label}\"\n\n";

    $field_name_lower = strtolower($field_name);
    $field_label_lower = strtolower((string) $field_label);

    // Title / heading fields.
    if ($field_name === 'title' || str_contains($field_name_lower, 'title') || str_contains($field_label_lower, 'heading')) {
      // The effective SEO title is the Metatag override when present; otherwise
      // the node title field value is what search engines and AI see.
      $effective_title = !empty($metatag_title) ? $metatag_title : $field_value;

      // The full browser <title> appends the site name (e.g. "Title | Site").
      $full_title = !empty($site_name) ? "{$effective_title} | {$site_name}" : $effective_title;
      $full_title_length = mb_strlen($full_title);
      $effective_length = mb_strlen($effective_title);

      $specific = "Analyze this page title for SEO and AI search performance.\n\n";

      if (!empty($metatag_title)) {
        $specific .= "**Important:** A Metatag title override is active on this page. "
          . "The node title field (\"" . $field_value . "\") is NOT what search engines see — "
          . "the Metatag override below is the actual SEO title.\n\n";
      }

      $specific .= "**Node title field:** \"{$field_value}\"\n";

      if (!empty($metatag_title)) {
        $specific .= "**Effective SEO title (Metatag override):** \"{$metatag_title}\"\n";
      }

      if (!empty($site_name)) {
        $specific .= "**Full browser title (site name appended):** \"{$full_title}\"\n"
          . "**Full browser title length:** {$full_title_length} chars "
          . "(effective title alone: {$effective_length} chars)\n\n";
      }
      else {
        $specific .= "**Title length:** {$effective_length} chars\n\n";
      }

      $specific .= "Evaluate:\n"
        . "- Character length of the **full browser title** including site name (optimal 50–60 chars total for search snippets)\n"
        . "- Primary keyword placement in the effective SEO title (front-loading is stronger)\n"
        . "- Emotional hooks and click-through appeal\n"
        . "- AI citation quality: would an AI assistant quote this title when answering a user question?\n"
        . "- Whether the title signals expertise and topical authority\n";

      if (!empty($metatag_title)) {
        $specific .= "- Consistency between the node title and the Metatag override — are they aligned in intent?\n";
      }

      $specific .= "\nProvide: a **Score: X/10**, 3–5 bullet-point observations, and one concrete **Suggested rewrite** "
        . "(for the effective SEO title only, without site name suffix).\n"
        . "Keep the response concise — under 300 words. No preamble.";
    }
    // Meta description / SEO description fields.
    elseif (str_contains($field_name_lower, 'meta') || str_contains($field_name_lower, 'description') || str_contains($field_label_lower, 'meta description')) {
      $char_count = mb_strlen($field_value);
      $specific = "Analyze this meta description for SEO and click-through performance:\n\n\"{$field_value}\"\n\n"
        . "Evaluate:\n"
        . "- Character length (optimal 150–160; current: {$char_count} chars)\n"
        . "- Keyword inclusion and natural placement\n"
        . "- Presence of a call-to-action or value proposition\n"
        . "- Social sharing appeal (OpenGraph fallback)\n"
        . "- AI snippet quality: could this be surfaced verbatim by an AI search engine?\n\n"
        . "Provide: a **Score: X/10**, 3–4 bullet-point observations, and one **Suggested rewrite** example.\n"
        . "Keep the response concise — under 250 words. No preamble.";
    }
    // Summary / excerpt fields.
    elseif (str_contains($field_name_lower, 'summary') || str_contains($field_name_lower, 'excerpt') || str_contains($field_label_lower, 'summary')) {
      $specific = "Analyze this summary/excerpt field for AI-featured-snippet optimization:\n\n\"{$field_value}\"\n\n"
        . "Evaluate:\n"
        . "- Is this answer-ready? Could an AI assistant quote it directly when responding to a relevant user query?\n"
        . "- Clarity and specificity — does it stand alone without surrounding context?\n"
        . "- Keyword presence and topical focus\n"
        . "- Length (100–160 chars is ideal for AI snippets)\n\n"
        . "Provide: a **Score: X/10**, 3–4 bullet-point observations, and one **Suggested rewrite** example.\n"
        . "Keep the response concise — under 250 words. No preamble.";
    }
    // Body / long-form content fields.
    elseif ($field_type === 'text_with_summary' || $field_type === 'text_long' || $field_name === 'body' || str_contains($field_name_lower, 'body') || str_contains($field_name_lower, 'content')) {
      $word_count = str_word_count(strip_tags($field_value));
      $specific = "Analyze this content field for SEO quality and AI citability:\n\n"
        . strip_tags($field_value) . "\n\n"
        . "(Word count: approximately {$word_count} words)\n\n"
        . "Evaluate:\n"
        . "- Readability and paragraph length (aim for 3–5 sentences per paragraph)\n"
        . "- Keyword usage — is the primary topic clear and well-reinforced?\n"
        . "- E-E-A-T signals: does the content demonstrate experience, expertise, authoritativeness?\n"
        . "- AI citation likelihood: does it contain quotable, factual, well-structured passages?\n"
        . "- Heading and structure usage within the content\n\n"
        . "Provide: a **Score: X/10**, 4–5 bullet-point observations, and 2 specific improvement suggestions.\n"
        . "Keep the response under 400 words. No preamble.";
    }
    // Generic text field fallback.
    else {
      $specific = "Analyze this text field (\"{$field_label}\") for SEO and AI visibility:\n\n\"{$field_value}\"\n\n"
        . "Evaluate:\n"
        . "- Keyword relevance and topical clarity\n"
        . "- Whether this copy builds trust and authority\n"
        . "- AI search visibility: would this phrasing help or hinder AI engines surfacing this page?\n\n"
        . "Provide: a **Score: X/10**, 3 bullet-point observations, and one **Suggested rewrite** if relevant.\n"
        . "Keep the response under 250 words. No preamble.";
    }

    return $context . $specific;
  }

  protected function analyzeHtml(string $html, string $prompt, ?string $url = NULL, ?string $entity_type_id = NULL, ?int $entity_id = NULL, ?int $revision_id = NULL, ?string $langcode = NULL, array $options = []) {
    // Parse, minify & clean.
    $cleaned_html = $this->parseHtml($html);

    $prompt .= $this->getFormatInstruction();

    $result = NULL;

    try {
      // First make sure that the AI config is set up.
      $model_and_provider_string = $this->config->get('provider_and_model') ?? '';
      if (empty($model_and_provider_string)) {
        $model_and_provider_string = $this->aiProvider->getSimpleDefaultProviderOptions('chat');
      }
      // Get provider and model.
      $ai_settings = explode('__', $model_and_provider_string);
      if (count($ai_settings) !== 2) {
        throw new \Exception('No AI provider or model is configured for this operation.');
      }

      // Chat it up.
      $configuration = [
        'http_client_options' => [
          'timeout' => 500,
        ],
      ];
      $ai_provider = $this->aiProvider->createInstance($ai_settings[0], $configuration);

      // Set the system message.
      $system_prompt = $this->getSystemPromptText();
      $ai_provider->setChatSystemRole($system_prompt);

      // Create the chat array to pass on.
      $chat_array = [];

      // The analysis prompt.
      $chat_array[] = new chatMessage('user', $prompt);

      // Cleaned HTML as an user message.
      $chat_array[] = new chatMessage('user', $cleaned_html);

      // Create the input chain.
      $messages = new ChatInput($chat_array);
      $message = $ai_provider->chat($messages, $ai_settings[1])->getNormalized();
      $result = trim($message->getText()) ?? $this->t('No result could be generated.');

      // Remove any wrapping code block markers the AI added despite instructions.
      $result = $this->stripCodeBlockWrapper($result);

      // Convert to HTML.
      $converter = new CommonMarkConverter();
      $result = trim($converter->convert($result));

      if (!empty($result)) {
        if ($options['save'] ?? TRUE) {
          $this->saveReport($result, $prompt, $cleaned_html, $url, $entity_type_id, $entity_id, $revision_id, $langcode, $options);
          $this->messenger->addStatus($this->t('SEO/GEO report generated successfully'));
        }

        if ($url) {
          $this->logger->notice($this->t('SEO/GEO report generated for URL: %url', [
            '%url' => $url,
          ]));
        }
      }
      else {
        // If the result is empty, an error has been logged. Show a message.
        $this->messenger->addError($this->t('Error trying to fetch results from AI. Check logs for more information.'));
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error trying to fetch results from AI. ' . print_r($e, TRUE));
    }

    return $result;
  }

  /**
   * Returns the default system prompt.
   *
   * @return string
   *   The default system prompt.
   */
  public function getDefaultSystemPrompt() {
    return "You are an SEO/GEO analysis expert specialized in evaluating HTML content for both traditional search engine optimization and Generative Engine Optimization (GEO) — the practice of making content discoverable and citable by AI-powered search systems such as Google AI Mode, AI Overviews, ChatGPT, Perplexity, and Gemini. Your role is to provide a comprehensive audit covering both traditional SEO best practices and GEO signals including AI citability, structured data for AI extraction, E-E-A-T signals, and agentic search readiness. Be thorough, precise, and provide examples wherever possible. Always respond in markdown format.";
  }

  /**
   * Return either default or custom system prompt.
   *
   * @return string
   *   Prompt text.
   */
  public function getSystemPromptText() {
    // Get the custom prompt if one is set.
    $custom_system_prompt = $this->config->get('custom_system_prompt');

    // Use that or the default one.
    $prompt = (!empty($custom_system_prompt)) ? $custom_system_prompt : $this->getDefaultSystemPrompt();

    // Otherwise return the default one.
    return $prompt;
  }

  /**
   * Returns the default prompt.
   *
   * @return string
   *   The default prompt.
   */
  public function getDefaultPrompt() {
    return $this->getPromptByType('full');
  }

  /**
   * Saves a new SEO analysis report to the database.
   *
   * This function records the provided report along with the entity ID,
   * the ID of the user who created the report, and the current timestamp.
   *
   * @param string $report
   *   The SEO analysis report to be saved.
   * @param string $prompt
   *   The prompt used.
   * @param string $html
   *   The HTML used.
   * @param string $url
   *   The URL the report was generated from.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int $entity_id
   *   The entity ID associated with the report.
   * @param int $revision_id
   *   The entity revision ID that the report was generated from.
   * @param string $langcode
   *   The entity langcode.
   * @param array $options
   *  Additional options for saving the report.
   *
   * @return int
   *   The unique identifier (ID) of the inserted report record.
   */
  protected function saveReport(string $report, string $prompt, string $html, ?string $url = NULL, ?string $entity_type_id = NULL, ?int $entity_id = NULL, ?int $revision_id = NULL, ?string $langcode = NULL, array $options = []) {
    // Obtain the current time as a Unix timestamp.
    $timestamp = \Drupal::time()->getRequestTime();

    // Current user creates the report.
    $uid = \Drupal::currentUser()->id();

    // Set the report type.
    $report_type = $options['report_type'] ?? 'full';

    // Insert data into the 'ai_seo' table.
    $insert_id = $this->connection->insert('ai_seo')
      ->fields([
        'entity_type_id' => $entity_type_id,
        'entity_id' => $entity_id,
        'revision_id' => $revision_id,
        'langcode' => $langcode,
        'url' => $url,
        'uid' => $uid,
        'report' => $report,
        'report_type' => $report_type,
        'prompt' => $prompt,
        'html' => $html,
        'timestamp' => $timestamp,
      ])
      ->execute();

    return $insert_id;
  }

  /**
   * Retrieves reports from the database for a given entity ID.
   *
   * @param int $entity_id
   *   The entity ID for which reports are to be fetched.
   *
   * @return array
   *   An array of report records.
   */
  public function getReports(int $entity_id) {
    // Query the 'ai_seo' table for reports with the given nid.
    $query = $this->connection->select('ai_seo', 'o')
      ->fields('o', ['rid', 'entity_type_id', 'entity_id', 'revision_id', 'uid', 'report', 'report_type', 'prompt', 'html', 'timestamp'])
      ->condition('entity_id', $entity_id)
      ->orderBy('rid', 'DESC')
      ->execute();

    // Initialize an array to store the report data.
    $reports = [];

    // Fetch each record and add it to the reports array.
    foreach ($query as $record) {
      // Clean up stored reports.
      $report = $record->report;
      $report = str_replace(['<html>', '</html>'], '', $report);
      $report = str_replace(['<body>', '</body>'], '', $report);
      $report = preg_replace('/<head>.*?<\/head>/s', '', $report);
      $report = trim($report);

      $reports[] = [
        'rid' => $record->rid,
        'entity_type_id' => $record->entity_type_id,
        'entity_id' => $entity_id,
        'revision_id' => $record->revision_id,
        'uid' => $record->uid,
        'report' => $report,
        'report_type' => $record->report_type,
        'prompt' => $record->prompt,
        'html' => $record->html,
        'timestamp' => $record->timestamp,
      ];
    }

    return $reports;
  }

  /**
   * Get a single report by entity ID and report ID.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param int $report_id
   *   The report ID (rid from database).
   *
   * @return array|null
   *   Single report data or NULL if not found.
   */
  public function getReport($entity_id, $report_id) {
    $query = $this->connection->select('ai_seo', 'a')
      ->fields('a', [
        'rid',
        'entity_type_id',
        'entity_id',
        'revision_id',
        'langcode',
        'url',
        'uid',
        'report',
        'report_type',
        'prompt',
        'html',
        'timestamp',
      ])
      ->condition('entity_id', $entity_id)
      ->condition('rid', $report_id);

    $result = $query->execute()->fetchAssoc();

    if (!$result) {
      return NULL;
    }

    return $result;
  }

  /**
   * Fetch and return HTML.
   *
   * @param string $url
   *   URL to fetch.
   *
   * @return string
   *   Fetched HTML.
   */
  protected function fetchHtml(string $url) {
    $response = $this->httpClient->get($url);
    $data = $response->getBody();
    return $data;
  }

  /**
   * Fetch and return HTML.
   *
   * @param string $entity_type_id
   *   The type of the entity (e.g., 'node', 'user').
   * @param int $entity_id
   *   The unique identifier of the entity to be rendered.
   * @param int|null $revision_id
   *   Optional entity revision ID. (optional)
   * @param string $view_mode
   *   The view mode in which the entity will be rendered. (optional)
   *   Defaults to 'full'. Other common view modes include 'teaser', 'compact'.
   * @param string|null $langcode
   *   The language code for the rendering of the entity. (optional)
   *   If NULL, the default site language will be used.
   * @param array $options
   *  Additional options for rendering. (optional)
   *
   * @return string
   *   Fetched HTML.
   */
  protected function fetchEntityHtml(string $entity_type_id, int $entity_id, ?int $revision_id = NULL, string $view_mode = 'full', ?string $langcode = NULL, array $options = []) {
    $html = $this->renderEntityHtml->renderHtml($entity_type_id, $entity_id, $revision_id, $view_mode, $langcode, $options);
    return $html;
  }

  /**
   * Return content in a debug way.
   */
  protected function debug($text) {
    return '<pre><code>' . htmlentities($text) . '</pre></code>';
  }

  /**
   * Parse given HTML and remove unnecessary elements from it to save tokens.
   *
   * @param string $html
   *   The HTML to be minified.
   *
   * @return string
   *   The parsed HTML.
   */
  protected function parseHtml(string $html) {
    // Load the HTML content into a DOMDocument object.
    $dom = new \DOMDocument();
    libxml_use_internal_errors(TRUE);
    $dom->loadHTML($html);
    libxml_clear_errors();

    // Counters.
    $css_file_counter = 1;
    $js_file_counter = 1;

    // Remove all <svg> elements.
    $svgs = $dom->getElementsByTagName('svg');
    $length = $svgs->length;

    for ($i = $length - 1; $i >= 0; $i--) {
      $svg = $svgs->item($i);
      $svg->parentNode->removeChild($svg);
    }

    // Remove all base64 image srcs.
    $images = $dom->getElementsByTagName('img');
    foreach ($images as $image) {
      $src = $image->getAttribute('src');
      if (strpos($src, 'data:image/') === 0) {
        $image->parentNode->removeChild($image);
      }
    }

    // Remove irrelevant attributes.
    $allElements = $dom->getElementsByTagName('*');
    foreach ($allElements as $element) {
      if ($element->getAttribute('id') == 'toolbar-bar') {
        // Remove admin toolbar.
        $element->parentNode->removeChild($element);
        continue;
      }

      $element->removeAttribute('class');
      // $element->removeAttribute('type');
      $element->removeAttribute('style');
      $element->removeAttribute('media');

      // Iterate over attributes and remove those starting with "data-".
      foreach ($element->attributes as $attribute) {
        if (strpos($attribute->nodeName, 'data-') === 0) {
          $element->removeAttribute($attribute->nodeName);
        }
        else {
          // Remove query parameters from URLs.
          $attr_value = $attribute->nodeValue;
          $query_pos = strpos($attr_value, '?');
          if ($query_pos !== FALSE) {
            $attribute->nodeValue = substr($attr_value, 0, $query_pos);
          }
        }
      }
    }

    // Process link and script tags for renaming file references.
    // Renaming saves tokens.
    $links = $dom->getElementsByTagName('link');
    foreach ($links as $link) {
      if ($link->getAttribute('rel') == 'stylesheet') {
        $href = $link->getAttribute('href');
        $dirname = pathinfo($href, PATHINFO_DIRNAME);
        $new_filename = "file" . $css_file_counter++ . ".css";
        $new_url = $dirname . '/' . $new_filename;
        $link->setAttribute('href', $new_url);
      }
    }

    $scripts = $dom->getElementsByTagName('script');
    foreach ($scripts as $script) {
      $src = $script->getAttribute('src');
      if ($src) {
        $dirname = pathinfo($src, PATHINFO_DIRNAME);
        $new_filename = "file" . $js_file_counter++ . ".js";
        $new_url = $dirname . '/' . $new_filename;
        $script->setAttribute('src', $new_url);
      }
    }

    $html = $dom->saveHTML();

    // Clean and minify.
    $html = $this->minifyText($html);

    return $html;
  }

  /**
   * Minifies text to reduce token usage in API requests.
   *
   * This function trims and removes unnecessary whitespace from the text.
   * It's done to prepare text for AI API where token usage is a concern,
   * as it reduces the overall character count of the input.
   *
   * @param string $text
   *   The text to be minified.
   *
   * @return string
   *   The minified text.
   */
  protected function minifyText(string $text) {
    /*
      Commented out now that token usage is not so limited anymore.
    */
    // Remove <, >, and / characters.
    // $text = str_replace(['</', '<', '>'], ' ', $text);

    // Remove space after colons, semicolons, commas and opening curly braces.
    // $text = preg_replace('/([,;:{])\s+/', '$1', $text);

    // Remove space before colons, semicolons, commas and closing curly braces.
    // $text = preg_replace('/\s+([,;:}])/', '$1', $text);

    // Remove space around operators.
    // $text = preg_replace('/\s*([=><+*%&|!-])\s*/', '$1', $text);

    // Remove unnecessary spaces and newlines.
    // $text = str_replace(["\r", "\n", "\t", '  ', '    ', '    '], ' ', $text);

    // Multiple spaces to single.
    // $text = preg_replace('/\s+/', ' ', $text);

    // Remove comments.
    $text = preg_replace('!/\*.*?\*/!s', '', $text);
    $text = preg_replace('/\n\s*\n/', "\n", $text);

    // Trim.
    $text = trim($text);

    return $text;
  }

}
