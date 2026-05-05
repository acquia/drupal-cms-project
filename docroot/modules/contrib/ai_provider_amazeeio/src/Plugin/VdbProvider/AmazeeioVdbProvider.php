<?php

namespace Drupal\ai_provider_amazeeio\Plugin\VdbProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\Attribute\AiVdbProvider;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Plugin\VdbProvider\PostgresProvider;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\PostgresPgvectorClient;
use PgSql\Connection as PgSql;

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
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get(name: 'ai_provider_amazeeio.settings');
  }

  /**
   * Get the Postgres database connection.
   *
   * This connection is used interface with the Postgres client.
   *
   * @return \PgSql\Connection|false
   *   A connection to the Postgres instance.
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   */
  public function getConnection(?string $database = NULL): PgSql|false {
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
    $config = $this->getConfig();
    $form = parent::buildSettingsForm($form, $form_state, $configuration);
    $form['database_name']['#default_value'] = $configuration['database_settings']['database_name'] ?? $config->get(key: 'postgres_default_database');
    $form['collection']['#default_value'] = $configuration['database_settings']['collection'] ?? 'amazee_ai';
    return $form;
  }

  /**
   * Get connection data.
   *
   * @return array
   *   The connection data.
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   */
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
  public function getClient(): PostgresPgvectorClient {
    return \Drupal::service('ai_provider_amazeeio.postgres_client');
  }

}
