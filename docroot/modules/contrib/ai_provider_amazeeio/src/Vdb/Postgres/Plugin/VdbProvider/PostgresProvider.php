<?php

namespace Drupal\ai_provider_amazeeio\Vdb\Postgres\Plugin\VdbProvider;

use Drupal\Component\Plugin\DependentPluginInterface;
use PgSql\Connection;
use Drupal\ai\Base\AiVdbProviderClientBase;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\ai_search\EmbeddingStrategyInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\CreateCollectionException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DeleteFromCollectionException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DropCollectionException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\PostgresPgvectorClient;

/**
 * Base Plugin implementation of the 'Postgres amazee.ai vector DB' provider.
 */
class PostgresProvider extends AiVdbProviderClientBase implements ContainerFactoryPluginInterface, DependentPluginInterface {

  use StringTranslationTrait;
  // Use the LoggerChannelTrait instead of dependency injection because parent
  // __construct is marked as final.
  use LoggerChannelTrait;

  protected const LOGGER_CHANNEL = 'ai_provider_amazeeio';

  protected const AI_SEARCH_NATIVE_FIELDS = [
    'drupal_entity_id',
    'drupal_long_id',
    'content',
    'vector',
    'server_id',
    'index_id',
  ];

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
  public function getConnection(?string $database = NULL): Connection|false {
    $config = $this->getConnectionData();
    return $this->getClient()->getConnection(
      host: $config['postgres_host'],
      port: $config['postgres_port'],
      username: $config['postgres_username'],
      password: $config['postgres_password'],
      default_database: $config['postgres_default_database'],
      database: $database
    );
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
    $output['postgres_host'] = $this->configuration['postgres_host'] ?? $config->get(key: 'postgres_host');
    // Fail if host is not set.
    if (!$output['postgres_host']) {
      throw new DatabaseNotConfiguredException(message: 'Postgres host is not configured');
    }
    $output['postgres_username'] = $this->configuration['postgres_username'] ?? $config->get(key: 'postgres_username');
    if (!$output['postgres_username']) {
      throw new DatabaseNotConfiguredException(message: 'Postgres username is not configured');
    }
    $token = $config->get(key: 'postgres_password');
    $output['postgres_password'] = '';
    if ($token) {
      $key = $this->keyRepository->getKey(key_id: $token);
      if ($key) {
        $output['postgres_password'] = $key->getKeyValue();
      }
    }
    if (!empty($this->configuration['postgres_password'])) {
      $output['postgres_password'] = $this->configuration['postgres_password'];
    }
    if (!$output['postgres_password']) {
      throw new DatabaseNotConfiguredException(message: 'Postgres password is not configured');
    }

    $output['postgres_port'] = $this->configuration['postgres_port'] ?? $config->get(key: 'postgres_port');
    if (!$output['postgres_port']) {
      $output['postgres_port'] = 5432;
    }
    $output['postgres_default_database'] = $this->configuration['postgres_default_database'] ?? $config->get(key: 'postgres_default_database');
    if (!$output['postgres_default_database']) {
      throw new DatabaseNotConfiguredException(message: 'Postgres default_database is not configured');
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   */
  public function ping(?string $database = NULL): bool {
    if ($connection = $this->getConnection(database: $database)) {
      return $this->getClient()->ping(connection: $connection);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isSetup(): bool {
    if ($this->getConfig()->get(key: 'postgres_host')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\GetCollectionsException
   */
  public function getCollections(?string $database = NULL): array {
    return $this->getClient()->getCollections(
      connection: $this->getConnection(database: $database)
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\CreateCollectionException
   */
  public function createCollection(
    string $collection_name,
    int $dimension,
    VdbSimilarityMetrics $metric_type = VdbSimilarityMetrics::CosineSimilarity,
    ?string $database = NULL,
  ): void {
    try {
      $this->getClient()->createCollection(
        collection_name: $collection_name,
        dimension: $dimension,
        connection: $this->getConnection(database: $database)
      );
    }
    catch (CreateCollectionException $e) {
      // Do not throw error as this can happen in valid scenarios.
      // For example, if an index is cleared, an attempt is made to delete the
      // collection, even if the collection has not yet been created.
      $this->getLogger(self::LOGGER_CHANNEL)->warning(
        message: 'Create collection error: ' . $e->getMessage(),
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   */
  public function dropCollection(
    string $collection_name,
    ?string $database = NULL,
  ): void {
    try {
      $this->getClient()->dropCollection(
        collection_name: $collection_name,
        connection: $this->getConnection(database: $database)
      );
    }
    catch (DropCollectionException $e) {
      // Do not throw error as this can happen in valid scenarios.
      // For example, if an index is cleared, an attempt is made to delete the
      // collection, even if the collection has not yet been created.
      $this->getLogger(self::LOGGER_CHANNEL)->warning(
        message: 'Drop collection error: ' . $e->getMessage(),
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\InsertIntoCollectionException
   */
  public function insertIntoCollection(
    string $collection_name,
    array $data,
    ?string $database = NULL,
  ): void {
    $nativeFieldValues = array_intersect_key($data, array_flip(self::AI_SEARCH_NATIVE_FIELDS));
    $extraFields = array_diff_key($data, array_flip(self::AI_SEARCH_NATIVE_FIELDS));
    $this->getClient()->insertIntoCollection(
      collection_name: $collection_name,
      drupal_entity_id: $nativeFieldValues['drupal_entity_id'],
      drupal_long_id: $nativeFieldValues['drupal_long_id'],
      content: $nativeFieldValues['content'],
      vector: $nativeFieldValues['vector'],
      server_id: $nativeFieldValues['server_id'],
      index_id: $nativeFieldValues['index_id'],
      extra_fields: $extraFields,
      connection: $this->getConnection($database),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   */
  public function deleteFromCollection(
    string $collection_name,
    array $ids,
    ?string $database = NULL,
  ): void {
    if (empty($ids)) {
      return;
    }
    try {
      $this->getClient()->deleteFromCollection(
        collection_name: $collection_name,
        ids: $ids,
        connection: $this->getConnection($database)
      );
    }
    catch (DeleteFromCollectionException $e) {
      // Do not throw error as this can happen in valid scenarios.
      // For example, if a node is saved, it is deleted from the index before
      // being re-added. Even if the node does not exist in the index.
      $this->getLogger(self::LOGGER_CHANNEL)->warning(
        message: 'Delete from collection error: ' . $e->getMessage(),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(array $configuration, array $item_ids): void {
    $vdbIds = $this->getVdbIds(
      collection_name: $configuration['database_settings']['collection'],
      drupalIds: $item_ids,
      database: $configuration['database_settings']['database_name'],
    );
    if ($vdbIds) {
      $this->deleteFromCollection(
        collection_name: $configuration['database_settings']['collection'],
        ids: $vdbIds,
        database: $configuration['database_settings']['database_name'],
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\QuerySearchException
   */
  public function querySearch(
    string $collection_name,
    array $output_fields,
    string $filters = '',
    int $limit = 10,
    int $offset = 0,
    ?string $database = NULL,
  ): array {
    return $this->getClient()->querySearch(
      collection_name: $collection_name,
      output_fields: $output_fields,
      filters: $filters,
      limit: $limit,
      offset: $offset,
      connection: $this->getConnection($database)
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\VectorSearchException
   */
  public function vectorSearch(
    string $collection_name,
    array $vector_input,
    array $output_fields,
    QueryInterface $query,
    string $filters = '',
    int $limit = 10,
    int $offset = 0,
    ?string $database = NULL,
  ): array {
    $metric_type = VdbSimilarityMetrics::from(
      $query->getIndex()->getServerInstance()->getBackendConfig()['database_settings']['metric']
    );
    return $this->getClient()->vectorSearch(
      collection_name: $collection_name,
      vector_input: $vector_input,
      output_fields: $output_fields,
      filters: $filters,
      limit: $limit,
      offset: $offset,
      metric_type: $metric_type,
      connection: $this->getConnection($database)
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseNotConfiguredException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\QuerySearchException
   */
  public function getVdbIds(
    string $collection_name,
    array $drupalIds,
    ?string $database = NULL,
  ): array {
    if (empty($drupalIds)) {
      return [];
    }
    $prepared_drupal_ids = $this->getClient()->prepareStringArrayForSql(
      items: $drupalIds,
      connection: $this->getConnection($database)
    );
    $data = $this->querySearch(
      collection_name: $collection_name,
      output_fields: ['id'],
      filters: "WHERE drupal_entity_id IN $prepared_drupal_ids",
      database: $database
    );
    $ids = [];
    if (!empty($data)) {
      foreach ($data as $item) {
        $ids[] = $item['id'];
      }
    }
    return $ids;
  }

  /**
   * {@inheritDoc}
   */
  public function getClient(): PostgresPgvectorClient {
    return \Drupal::service('ai_provider_amazeeio.postgres_client');
  }

  /**
   * {@inheritDoc}
   */
  public function prepareFilters(QueryInterface $query): string {
    $index = $query->getIndex();
    $condition_group = $query->getConditionGroup();
    [$filters, $joins] = $this->processConditionGroup($index, $condition_group);
    if ($filters) {
      return implode(' ', $joins) . ' WHERE ' . implode(' AND ', $filters);
    }
    return '';
  }

  /**
   * Processes a condition group, including handling nested condition groups.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API Index.
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The condition group.
   *
   * @return array
   *   The updated build of the filters.
   */
  protected function processConditionGroup(IndexInterface $index, ConditionGroupInterface $condition_group): array {
    $postgresServerConfig = $index->getServerInstance()->getBackendConfig();
    $collection = $postgresServerConfig['database_settings']['collection'];
    $connection = $this->getConnection($postgresServerConfig['database_settings']['database_name']);
    $filters = [];
    $joins = [];
    foreach ($condition_group->getConditions() as $condition) {
      // Check if the current condition is actually a nested ConditionGroup.
      if ($condition instanceof ConditionGroupInterface) {
        // Recursively process the nested ConditionGroup.
        [$outputFilter, $outputJoins] = $this->processConditionGroup($index, $condition, $collection);
        $filters = array_merge($filters, $outputFilter);
        $joins = array_merge($joins, $outputJoins);
        continue;
      }

      $fieldData = $index->getField($condition->getField());
      if ($fieldData) {
        $fieldType = $fieldData->getType();
        $isMultiple = FALSE;
      }
      else {
        if (in_array($condition->getField(), self::AI_SEARCH_NATIVE_FIELDS)) {
          $fieldType = 'string';
          $isMultiple = FALSE;
        }
        else {
          // If the operator is not supported, log a warning.
          $this->messenger->addWarning('Field @field is not indexed on the @index so cannot be filtered on.', [
            '@field' => $condition->getField(),
            '@index' => $index->id(),
          ]);
          continue;
        }
      }

      $values = is_array($condition->getValue()) ? $condition->getValue() : [$condition->getValue()];
      if (in_array($fieldType, ['string', 'full_text'])) {
        $normalizedValues = $this->getClient()->prepareStringArrayForSql($values, $connection);
      }
      else {
        $normalizedValues = $this->getClient()->prepareArrayForSql($values);
      }
      if ($isMultiple) {
        $fieldIdentifier = $this->getClient()->escapeIdentifierForSql($collection . '__' . $fieldData->getFieldIdentifier(), $connection);
        $escapedCollection = $this->getClient()->escapeIdentifierForSql($collection, $connection);
        $join = "LEFT JOIN $fieldIdentifier ON $escapedCollection.id = $fieldIdentifier.chunk_id";

        if ($condition->getOperator() === '=') {
          $filters[] = "$fieldIdentifier.value @> $normalizedValues";
          $joins[] = $join;
        }
        elseif ($condition->getOperator() === '!=') {
          $filters[] = "$fieldIdentifier.value NOT @> $normalizedValues";
          $joins[] = $join;
        }
        elseif ($condition->getOperator() === 'IN') {
          $filters[] = "$fieldIdentifier.value IN $normalizedValues";
          $joins[] = $join;
        }
        elseif ($condition->getOperator() === 'NOT IN') {
          $filters[] = "$fieldIdentifier.value NOT IN $normalizedValues";
          $joins[] = $join;
        }
        else {
          // If the operator is not supported, log a warning.
          $this->messenger->addWarning('Operator @operator is not supported by this Postgres integration on multiple fields.', [
            '@operator' => $condition->getOperator(),
          ]);
        }
      }
      else {
        $operator = $condition->getOperator();
        $filters[] = '(' . $fieldData->getFieldIdentifier() . ' ' . $operator . ' ' . $normalizedValues . ')';
      }
    }
    return [$filters, array_unique($joins)];
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(
    array $configuration,
    IndexInterface $index,
    array $items,
    EmbeddingStrategyInterface $embedding_strategy,
  ): array {
    $successfulItemIds = [];
    $itemBase = [
      'metadata' => [
        'server_id' => $index->getServerId(),
        'index_id' => $index->id(),
      ],
    ];

    // Check if we need to delete some items first.
    $this->deleteIndexItems($configuration, $index, array_values(array_map(function ($item) {
      return $item->getId();
    }, $items)));

    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      $fields = $item->getFields();
      $embeddings = $embedding_strategy->getEmbedding(
        $configuration['embeddings_engine'],
        $configuration['chat_model'],
        $configuration['embedding_strategy_configuration'],
        $fields,
        $item,
        $index,
      );
      foreach ($embeddings as $embedding) {
        // Ensure consistent embedding structure as per
        // EmbeddingStrategyInterface.
        $this->validateRetrievedEmbedding($embedding);

        // Merge the base array structure with the individual chunk array
        // structure and add additional details.
        $embedding = array_merge_recursive($embedding, $itemBase);
        $data['drupal_long_id'] = ['value' => $embedding['id'], 'is_multiple' => FALSE];
        $data['drupal_entity_id'] = ['value' => $item->getId(), 'is_multiple' => FALSE];
        $data['vector'] = ['value' => $embedding['values'], 'is_multiple' => FALSE];
        foreach ($embedding['metadata'] as $key => $value) {
          if (in_array($key, self::AI_SEARCH_NATIVE_FIELDS)) {
            $data[$key] = ['value' => $value, 'is_multiple' => FALSE];
            continue;
          }
          $isMultiple = isset($fields[$key]) ? $this->isMultiple($fields[$key]) : FALSE;
          $data[$key] = ['value' => $value, 'is_multiple' => $isMultiple];
        }
        $this->insertIntoCollection(
          collection_name: $configuration['database_settings']['collection'],
          data: $data,
          database: $configuration['database_settings']['database_name'],
        );
      }

      $successfulItemIds[] = $item->getId();
    }
    return $successfulItemIds;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [
      'config' => [
        'ai_provider_amazeeio.settings',
      ],
    ];
  }

}
