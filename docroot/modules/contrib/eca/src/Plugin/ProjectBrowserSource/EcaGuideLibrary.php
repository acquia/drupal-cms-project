<?php

declare(strict_types=1);

namespace Drupal\eca\Plugin\ProjectBrowserSource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\ProjectBrowser\Filter\MultipleChoiceFilter;
use Drupal\project_browser\ProjectBrowser\Filter\TextFilter;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Drupal\project_browser\ProjectType;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes ECA Guide Library models to Project Browser.
 *
 * The models are fetched from a remote JSON feed hosted on the ECA
 * documentation domain. Each model is published as a Composer recipe, so the
 * projects declare the recipe project type and the recipe's Composer package
 * name. Installation is handled entirely by Project Browser's built-in
 * composer require and recipe activator pipeline; this plugin does not provide
 * its own activator.
 *
 * This plugin only becomes active when the Project Browser module is installed:
 * its source-plugin manager scans the `Plugin/ProjectBrowserSource` directory
 * of every module namespace. When Project Browser is absent, this class is
 * never autoloaded, so ECA does not depend on it.
 */
#[ProjectBrowserSource(
  id: 'eca_guide_library',
  label: new TranslatableMarkup('ECA Guide Library'),
  description: new TranslatableMarkup('ECA models published in the ECA Guide Library.'),
  local_task: [],
)]
final class EcaGuideLibrary extends ProjectBrowserSourceBase {

  /**
   * The path appended to the documentation domain to build the feed URL.
   */
  private const string FEED_PATH = '/library/project-browser.json';

  /**
   * The cache lifetime, in seconds, of the fetched feed.
   */
  private const int CACHE_TTL = 3600;

  /**
   * Constructs an EcaGuideLibrary plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client used to fetch the feed.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend used to store the decoded feed.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param string|null $defaultDocumentationDomain
   *   The default documentation domain, read from the
   *   `eca.default_documentation_domain` container parameter. Used as the
   *   fallback when the `documentation_domain` setting is empty.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ClientInterface $httpClient,
    protected CacheBackendInterface $cacheBackend,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerInterface $logger,
    protected TimeInterface $time,
    protected ?string $defaultDocumentationDomain,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('cache.default'),
      $container->get('config.factory'),
      $container->get('logger.channel.eca'),
      $container->get('datetime.time'),
      $container->getParameter('eca.default_documentation_domain'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {
    $feed = $this->fetchFeed();
    $categories_map = $feed['categories'];
    $models = $feed['models'];

    // Interpret the incoming query so that the models can be filtered.
    $search = mb_strtolower(trim((string) ($query['search'] ?? '')));
    $categories_filter = array_filter(explode(',', (string) ($query['categories'] ?? '')));
    $machine_name_filter = (string) ($query['machine_name'] ?? '');

    $projects = [];
    foreach ($models as $model) {
      if (!is_array($model)) {
        continue;
      }
      $machine_name = (string) ($model['machine_name'] ?? '');
      if ($machine_name === '') {
        continue;
      }

      // Exact machine name match.
      if ($machine_name_filter !== '' && $machine_name !== $machine_name_filter) {
        continue;
      }

      $title = (string) ($model['title'] ?? '');
      $summary = (string) ($model['summary'] ?? '');
      $description = (string) ($model['description'] ?? '');

      // Case-insensitive search on the title and summary.
      if ($search !== '') {
        $haystack = mb_strtolower($title . ' ' . $summary);
        if (!str_contains($haystack, $search)) {
          continue;
        }
      }

      // Build the category map (id => label) for this model.
      $categories = [];
      foreach ($model['categories'] ?? [] as $category_id) {
        $category_id = (string) $category_id;
        $categories[$category_id] = $categories_map[$category_id] ?? $category_id;
      }

      // Filter by the requested categories, if any.
      if ($categories_filter !== [] && array_intersect($categories_filter, array_keys($categories)) === []) {
        continue;
      }

      $logo = NULL;
      if (!empty($model['logo'])) {
        $logo = Url::fromUri((string) $model['logo']);
      }

      // The model's page on the ECA Guide, if the feed provides one. Older
      // feed entries may omit it, so it is only built when present.
      $url = NULL;
      if (!empty($model['url'])) {
        $url = Url::fromUri((string) $model['url']);
      }

      // Screenshots are bare URL strings in the feed. Project Browser requires
      // each image to be an array with a `file` Url and a non-empty `alt`; the
      // feed no longer supplies a per-screenshot title, so the model title is
      // used as alt text, falling back to the machine name when the title is
      // empty.
      $alt = $title !== '' ? $title : $machine_name;
      $images = [];
      foreach ($model['screenshots'] ?? [] as $screenshot) {
        if (!is_string($screenshot) || $screenshot === '') {
          continue;
        }
        $images[] = [
          'file' => Url::fromUri($screenshot),
          'alt' => $alt,
        ];
      }

      $projects[] = new Project(
        logo: $logo,
        isCompatible: TRUE,
        machineName: $machine_name,
        body: [
          'summary' => $summary,
          'value' => $description,
        ],
        title: $title,
        packageName: (string) ($model['package'] ?? ''),
        isCovered: TRUE,
        isMaintained: TRUE,
        url: $url,
        categories: $categories,
        images: $images,
        type: ProjectType::Recipe,
      );
    }

    // Apply the user-selected sort. Project Browser passes the chosen sort
    // option as $query['sort']; the source plugin is responsible for ordering
    // the results (see \Drupal\project_browser\Plugin\ProjectBrowserSource\
    // Recipes::getProjects()). Titles may be strings or Stringable objects, so
    // they are cast before comparison, and strcasecmp is used so that the sort
    // is case-insensitive and natural (e.g. "apple" sorts before "Zebra").
    switch ($query['sort'] ?? '') {
      case 'a_z':
        usort($projects, static fn (Project $x, Project $y): int => strcasecmp((string) $x->title, (string) $y->title));
        break;

      case 'z_a':
        usort($projects, static fn (Project $x, Project $y): int => strcasecmp((string) $y->title, (string) $x->title));
        break;

      // When no sort is chosen, the results are left in feed order. The ECA
      // Guide feed generator already emits the models in a sensible order, and
      // this mirrors the default (unsorted) behavior of the Recipes source.
    }

    return $this->createResultsPage($projects);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    $filters = [
      'search' => new TextFilter('', $this->t('Search')),
    ];

    $categories = $this->fetchFeed()['categories'];
    $filters['categories'] = new MultipleChoiceFilter($categories, [], $this->t('Categories'));

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortOptions(): array {
    // Only sorts backed by data in the feed are advertised. The feed models do
    // not currently carry an "updated"/timestamp field, so a "most recently
    // updated" option is intentionally omitted; it can be re-added (sorting by
    // that timestamp descending) once the ECA Guide feed generator emits it.
    return [
      'a_z' => (string) $this->t('Title, A-Z'),
      'z_a' => (string) $this->t('Title, Z-A'),
    ];
  }

  /**
   * Builds the feed URL from the configured documentation domain.
   *
   * The domain is read from the `documentation_domain` setting in
   * `eca.settings`, falling back to the `eca.default_documentation_domain`
   * container parameter when the setting is empty.
   *
   * @return string
   *   The absolute feed URL, or an empty string when no domain is available.
   */
  private function getFeedUrl(): string {
    $domain = (string) $this->configFactory->get('eca.settings')->get('documentation_domain');
    if ($domain === '') {
      $domain = (string) $this->defaultDocumentationDomain;
    }
    if ($domain === '') {
      return '';
    }
    return rtrim($domain, '/') . self::FEED_PATH;
  }

  /**
   * Fetches and caches the ECA Guide Library feed.
   *
   * The decoded payload is cached, keyed by the resolved feed URL, for the
   * configured lifetime. Any network or decoding error is logged as a warning
   * and results in an empty, well-formed payload so that callers never fail.
   *
   * @return array
   *   An associative array with two keys:
   *   - categories: An associative array of category ID => label.
   *   - models: A numerically indexed array of model definitions.
   */
  private function fetchFeed(): array {
    $empty = ['categories' => [], 'models' => []];

    $feed_url = $this->getFeedUrl();
    if ($feed_url === '') {
      return $empty;
    }

    $cid = 'eca:project_browser_library:feed:' . $feed_url;
    $cached = $this->cacheBackend->get($cid);
    if ($cached !== FALSE) {
      return $cached->data;
    }

    try {
      $response = $this->httpClient->request('GET', $feed_url);
      $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Unable to fetch the ECA Guide Library feed from %url: @message', [
        '%url' => $feed_url,
        '@message' => $e->getMessage(),
      ]);
      return $empty;
    }

    $data = [
      'categories' => is_array($payload['categories'] ?? NULL) ? $payload['categories'] : [],
      'models' => is_array($payload['models'] ?? NULL) ? array_values($payload['models']) : [],
    ];

    $expire = $this->time->getRequestTime() + self::CACHE_TTL;
    $this->cacheBackend->set($cid, $data, $expire);

    return $data;
  }

}
