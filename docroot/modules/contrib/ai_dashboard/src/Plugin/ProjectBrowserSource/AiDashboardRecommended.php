<?php

declare(strict_types=1);

namespace Drupal\ai_dashboard\Plugin\ProjectBrowserSource;

use Composer\Semver\Semver;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use GuzzleHttp\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A source that reads projects from a curated list.
 *
 * The list can be a local file or a remote URL. It is expected to be a YAML
 * file with a list of projects, each of which is an associative array with some
 * of the parameters of the `Project` class constructor.
 */
#[ProjectBrowserSource(
  id: 'ai_dashboard_recommended',
  label: new TranslatableMarkup('Recommended AI Recipes'),
  description: new TranslatableMarkup('A curated set of recommended ai recipes for Drupal.'),
  local_task: [],
)]
final class AiDashboardRecommended extends ProjectBrowserSourceBase {

  /**
   * The default location of the curated list of recommended recipes.
   *
   * Used when the source has no stored URI, for example after the Project
   * Browser settings form is saved, which resets each source's configuration.
   */
  public const DEFAULT_URI = 'https://git.drupalcode.org/api/v4/projects/196945/repository/files/ai_dashboard_recommended_recipes.yml/raw?ref=HEAD';

  public function __construct(
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected ?CacheBackendInterface $cacheBackend,
    protected ClientInterface $httpClient,
    protected TimeInterface $time,
    protected ?LoggerInterface $logger,
    mixed ...$arguments,
  ) {
    parent::__construct(...$arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $container->get(StreamWrapperManagerInterface::class),
      $container->get('cache.project_browser', ContainerInterface::NULL_ON_INVALID_REFERENCE),
      $container->get(ClientInterface::class),
      $container->get(TimeInterface::class),
      $container->get('logger.channel.project_browser', ContainerInterface::NULL_ON_INVALID_REFERENCE),
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {
    $results = [];

    $projects = $this->loadProjects();
    if ($projects === NULL) {
      // Report the failure as an error so that Project Browser does not cache
      // the empty result and the list recovers as soon as the data is
      // available again.
      return $this->createResultsPage([], 0, (string) $this->t('The list of recommended recipes could not be loaded.'));
    }

    foreach ($projects as $arguments) {
      // The project is considered compatible if its core constraint is
      // satisfied by the current Drupal version. The core constraint is set by
      // whoever is curating the list of recommended projects.
      $arguments['isCompatible'] = Semver::satisfies(\Drupal::VERSION, $arguments['core']);
      unset($arguments['core']);

      // We don't need statistics, because this is a recommended add-on.
      unset($arguments['projectUsageTotal']);

      // Since this plugin is showing projects that are explicitly recommended
      // by an external curator, we can assume it is covered by security
      // advisories and actively maintained.
      $arguments['isCovered'] = TRUE;
      $arguments['isMaintained'] = TRUE;

      $arguments['logo'] = isset($arguments['logo'])
        ? Url::fromUri($arguments['logo'])
        : NULL;

      if (isset($arguments['url'])) {
        $arguments['url'] = Url::fromUri($arguments['url']);
      }
      $results[] = new Project(...$arguments);
    }
    return $this->createResultsPage($results);
  }

  /**
   * Loads project data from the configured URI.
   *
   * @return iterable<array>|null
   *   Arrays of raw values to pass to the `Project` constructor, or NULL if
   *   the data could not be loaded.
   */
  private function loadProjects(): ?iterable {
    $cid = $this->getPluginId();
    $cached = $this->cacheBackend?->get($cid);
    if ($cached) {
      return $cached->data;
    }

    ['uri' => $uri, 'ttl' => $ttl] = $this->getConfiguration();
    if (empty($uri)) {
      return NULL;
    }

    if (file_exists($uri) || $this->streamWrapperManager->isValidUri($uri)) {
      $data = file_get_contents($uri) ?: NULL;
    }
    else {
      try {
        $data = $this->httpClient->request('GET', $uri)
          ->getBody()
          ->getContents();
      }
      catch (ClientExceptionInterface $e) {
        $this->logger?->error($e->getMessage());
        $data = NULL;
      }
    }

    if (isset($data)) {
      $data = Yaml::decode($data);
      $this->cacheBackend?->set($cid, $data, $this->time->getRequestTime() + intval($ttl));
    }
    return $data ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'uri' => self::DEFAULT_URI,
      // Cache data for 3 days by default.
      'ttl' => 86400 * 3,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    return [];
  }

}
