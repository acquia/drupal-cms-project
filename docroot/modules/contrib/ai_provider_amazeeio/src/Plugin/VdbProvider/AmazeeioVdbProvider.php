<?php

namespace Drupal\ai_provider_amazeeio\Plugin\VdbProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\Attribute\AiVdbProvider;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Plugin\VdbProvider\PostgresProvider;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\PostgresPgvectorClient;

/**
 * Plugin implementation of the 'amazee.ai Vector Database' provider.
 */
#[AiVdbProvider(
    id: 'amazeeio_vector_db',
    label: new TranslatableMarkup('amazee.ai Vector Database'),
)]
class AmazeeioVdbProvider extends PostgresProvider {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get(name: 'ai_provider_amazeeio.settings');
  }

  /**
   * Get the Postgres database connection.
   *
   * This connection is used interface with the Postgres client.
   *
   * @return \PDO|false
   *   A connection to the Postgres instance.
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   */
  #[\Override]
  public function getConnection(string $database = 'default'): \PDO|false {
    $config = $this->getConnectionData();
    return $this->getClient()->getConnection(
          host: $config['host'],
          port: $config['port'],
          username: $config['username'],
          password: $config['password'],
          default_database: $config['default_database'],
          database: $database
      );
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(
    array $form,
    FormStateInterface $form_state,
    array $configuration,
  ): array {
    $form = parent::buildSettingsForm($form, $form_state, $configuration);
    $config = $this->getConfig();

    // Add an introductory help block explaining how the amazee.ai VectorDB
    // is provisioned, what the user can and cannot change, and how the
    // dimension count is decided. This guidance was previously only
    // available by reading the source.
    $form['amazeeio_vdb_help'] = [
      '#type' => 'details',
      '#title' => $this->t('About the amazee.ai Vector Database'),
      '#open' => FALSE,
      '#weight' => -10,
      'content' => [
        '#markup' => '<p>' . $this->t('Your amazee.ai private AI key includes a dedicated PostgreSQL database with the <a href=":pgvector">pgvector</a> extension enabled. The connection details (host, port, username, password and database name) are configured automatically when you connect on the <a href=":provider">amazee.ai provider settings page</a>.', [
          ':pgvector' => 'https://github.com/pgvector/pgvector',
          ':provider' => '/admin/config/ai/providers/amazeeio',
        ]) . '</p>'
        . '<ul>'
        . '<li>' . $this->t('<strong>Database Name</strong> is pre-filled with the database that amazee.ai allocated to your key. You generally should not change it.') . '</li>'
        . '<li>' . $this->t('<strong>Collection</strong> is the name of the table that will be created inside that database to hold the vectors for this server. The default is <code>amazee_ai</code>. The collection is created on save and cannot be renamed afterwards. Use only letters, numbers and underscores. If you plan to run more than one Search API server against the same database, give each one a unique collection name.') . '</li>'
        . '<li>' . $this->t('<strong>Number of dimensions</strong> is <em>not</em> set here. It is taken from the embedding model you select on this same form (under "Embeddings Engine"). Different models produce vectors of different sizes (some commonly used models use 1024 dimensions, others larger or smaller); refer to the documentation of the embedding model you choose for its exact value. Once a collection is created with a given dimension, every embedding written to it must match.') . '</li>'
        . '<li>' . $this->t('<strong>Similarity Metric</strong> defaults to Cosine Similarity, which is the right choice for most modern embedding models. Only change it if your embedding model documentation explicitly recommends Euclidean Distance or Inner Product.') . '</li>'
        . '</ul>',
      ],
    ];

    $form['database_name']['#default_value'] = $configuration['database_settings']['database_name'] ?? $config->get(key: 'postgres_default_database');
    $form['database_name']['#description'] = $this->t('The PostgreSQL database name. Pre-filled from your amazee.ai allocation; usually leave this as-is.');

    $form['collection']['#default_value'] = $configuration['database_settings']['collection'] ?? 'amazee_ai';
    $form['collection']['#description'] = $this->t('The collection (table) to use inside the database. Created on save if it does not exist, and cannot be renamed afterwards. Default: <code>amazee_ai</code>. Use a unique name per Search API server if you have more than one.');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewIndexSettings(array $database_settings): array {
    $results = [];
    $results['ping'] = [
      'label' => $this->t('Ping'),
      'info' => $this->t('Able to reach Postgres via the Amazee.io client.'),
      'status' => $this->ping($database_settings['database_name']) ? 'success' : 'error',
    ];

    $config = $this->getConnectionData();
    $results['host'] = [
      'label' => $this->t('Postgres Host'),
      'info' => $config['host'],
    ];

    return $results;
  }

  /**
   * Get connection data.
   *
   * @return array
   *   The connection data.
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   */
  #[\Override]
  public function getConnectionData() {
    $config = $this->getConfig();
    $output = [];
    $output['host'] = $this->configuration['host'] ?? $config->get(key: 'postgres_host');
    // Fail if host is not set.
    if (!$output['host']) {
      throw new DatabaseNotConfiguredException(message: 'Postgres host is not configured');
    }
    $output['username'] = $this->configuration['username'] ?? $config->get(key: 'postgres_username');
    if (!$output['username']) {
      throw new DatabaseNotConfiguredException(message: 'Postgres username is not configured');
    }
    $token = $config->get(key: 'postgres_password');
    $output['password'] = '';
    if ($token) {
      $key = $this->keyRepository->getKey(key_id: $token);
      if ($key) {
        $output['password'] = $key->getKeyValue();
      }
    }
    if (!empty($this->configuration['password'])) {
      $output['password'] = $this->configuration['password'];
    }
    if (!$output['password']) {
      throw new DatabaseNotConfiguredException(message: 'Postgres password is not configured');
    }

    $output['port'] = $this->configuration['port'] ?? $config->get(key: 'postgres_port');
    if (!$output['port']) {
      $output['port'] = 5432;
    }
    $output['default_database'] = $this->configuration['default_database'] ?? $config->get(key: 'postgres_default_database');
    if (!$output['default_database']) {
      throw new DatabaseNotConfiguredException(message: 'Postgres default_database is not configured');
    }
    return $output;
  }

  /**
   * {@inheritDoc}
   */
  #[\Override]
  public function getClient(): PostgresPgvectorClient {
    return \Drupal::service('ai_provider_amazeeio.postgres_client');
  }

}
