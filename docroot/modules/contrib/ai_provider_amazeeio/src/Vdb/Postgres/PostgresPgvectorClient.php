<?php

namespace Drupal\ai_provider_amazeeio\Vdb\Postgres;

use Drupal\ai\Enum\EmbeddingStrategyIndexingOptions;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\AddFieldIfNotExistsException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\CreateCollectionException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DeleteFromCollectionException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DropCollectionException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\GetCollectionsException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\InsertIntoCollectionException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\QuerySearchException;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\VectorSearchException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Utility\Utility;
use PgSql\Connection;

/**
 * Provides abstracted Postgres client to interface with pgvector.
 */
class PostgresPgvectorClient {

  /**
   * Constructs a new object.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface|null $fieldHelper
   *   Search API's field helper. Nullable, since this class is only in use
   *   when Search API is enabled.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory, used to read ai_search indexing options.
   */
  public function __construct(
    private readonly ?FieldsHelperInterface $fieldHelper,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  protected const DATA_TYPE_MAPPING = [
    'integer' => 'INTEGER',
    'text' => 'TEXT',
    'full_text' => 'TEXT',
    // Use BIGINT instead of TIMESTAMP because at index time, the provider
    // does not know whether the field value is a date or number.
    'date' => 'BIGINT',
    'decimal' => 'DECIMAL',
    'string' => 'VARCHAR',
    'boolean' => 'BOOLEAN',
  ];

  /**
   * Get the Postgres database connection.
   *
   * @return \PgSql\Connection|false
   *   A connection to the Postgres database.
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DatabaseConnectionException
   */
  public function getConnection(
    string $host,
    int $port,
    string $username,
    string $password,
    string $default_database,
    ?string $database = NULL,
  ): Connection|FALSE {
    if (!isset($database) || $database === 'default') {
      $database = $default_database;
    }
    $connection = pg_connect(
      connection_string: "host=" . addcslashes($host, "'\\") . " dbname=" . addcslashes($database, "'\\") . " port=" . (int) $port . " user=" . addcslashes($username, "'\\") . " password=" . addcslashes($password, "'\\")
    );
    if (!$connection) {
      throw new DatabaseConnectionException(
        message: 'Cannot connect to Postgres database using provided connection details',
      );
    }
    return $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function ping(Connection $connection): bool {
    return pg_ping(connection: $connection);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\GetCollectionsException
   */
  public function getCollections(Connection $connection): array {
    $result = pg_query_params(
      connection: $connection,
      query: 'SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = $1',
      params: ['BASE TABLE'],
    );
    if (!$result) {
      throw new GetCollectionsException(message: pg_last_error(connection: $connection));
    }
    return pg_fetch_all_columns($result);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\CreateCollectionException
   */
  public function createCollection(
    string $collection_name,
    int $dimension,
    Connection $connection,
  ): void {
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $result = pg_query(
      connection: $connection,
      query: "CREATE TABLE {$escaped_collection_name} (id bigserial PRIMARY KEY, content VARCHAR, drupal_entity_id VARCHAR, drupal_long_id VARCHAR, server_id VARCHAR, index_id VARCHAR, embedding vector(" . (int) $dimension . "));"
    );
    if (!$result) {
      throw new CreateCollectionException(message: pg_last_error(connection: $connection));
    }
    // Attempt to update the additional fields from the search api indexes.
    foreach ($this->getSearchApiServers($collection_name, $connection) as $search_api_server) {
      foreach ($search_api_server->getIndexes() as $index) {
        // Create the necessary index fields.
        $this->updateFields($index->getFields(), $collection_name, $connection);
      }
    }
  }

  /**
   * Returns the search api servers for the current connection.
   *
   * @param string $collection_name
   *   The collection name of the connection.
   * @param \PgSql\Connection $connection
   *   The current connection.
   *
   * @return \Drupal\search_api\Entity\Server[]
   *   The Search API servers.
   */
  protected function getSearchApiServers(string $collection_name, Connection $connection): array {
    if (!$this->entityTypeManager->hasDefinition('search_api_server')) {
      return [];
    }

    $result = pg_query(
      $connection,
      'SELECT current_database()'
    );
    $current_database = pg_fetch_result($result, 0, 0);

    $search_api_server_storage = $this->entityTypeManager->getStorage('search_api_server');
    $query = $search_api_server_storage->getQuery();
    $query->condition('status', TRUE)
      ->condition('backend', 'search_api_ai_search')
      ->condition('backend_config.database', 'amazeeio_vector_db')
      ->condition('backend_config.database_settings.database_name', $current_database)
      ->condition('backend_config.database_settings.collection', $collection_name);
    $ai_servers = $query
      ->accessCheck(FALSE)
      ->execute();

    return $search_api_server_storage->loadMultiple($ai_servers);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DropCollectionException
   */
  public function dropCollection(
    string $collection_name,
    Connection $connection,
  ): void {
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $result = pg_query(
      connection: $connection,
      query: "DROP TABLE IF EXISTS {$escaped_collection_name} CASCADE;"
    );
    if (!$result) {
      throw new DropCollectionException(message: pg_last_error(connection: $connection));
    }

    $relation_tables = $this->getRelationTables($collection_name, $connection);
    foreach ($relation_tables as $relation_table) {
      $escaped_relation_table = $this->escapeIdentifierForSql(
        $relation_table,
        $connection,
      );
      $result = pg_query(
        $connection,
        "DROP TABLE IF EXISTS {$escaped_relation_table} CASCADE;"
      );
      if (!$result) {
        throw new DropCollectionException(message: pg_last_error(connection: $connection));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\InsertIntoCollectionException
   */
  public function insertIntoCollection(
    string $collection_name,
    array $drupal_entity_id,
    array $drupal_long_id,
    array $content,
    array $vector,
    array $server_id,
    array $index_id,
    array $extra_fields,
    Connection $connection,
  ): void {
    $vector_string = $this->prepareVectorArrayForSql(
      vector: $vector['value'],
      connection: $connection,
    );
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    // Prepare columns and values for extra fields.
    $extra_fields_columns = '';
    $extra_fields_values = '';
    $extra_fields_params = [];

    $relation_queries = [];

    $param_index = 6;
    foreach ($extra_fields as $field_name => $field_data) {
      if ($field_data['is_multiple']) {
        if ($relation_query = $this->prepareRelationQuery($collection_name, $field_name, $field_data, $connection)) {
          $relation_queries[] = $relation_query;
        }
      }
      else {
        $escaped_field_name = $this->escapeIdentifierForSql($field_name, $connection);
        $extra_fields_columns .= ", {$escaped_field_name}";
        $extra_fields_values .= ", \${$param_index}";
        $extra_fields_params[] = $field_data['value'];
        $param_index++;
      }
    }
    $main_query = "INSERT INTO {$escaped_collection_name} (content, drupal_entity_id, drupal_long_id, server_id, index_id, embedding{$extra_fields_columns}) VALUES ($1, $2, $3, $4, $5, {$vector_string}{$extra_fields_values});";

    $params = array_merge([
      $content['value'],
      $drupal_entity_id['value'],
      $drupal_long_id['value'],
      $server_id['value'],
      $index_id['value'],
    ], $extra_fields_params);

    $result = pg_query_params(
      connection: $connection,
      query: $main_query,
      params: $params,
    );
    if (!$result) {
      throw new InsertIntoCollectionException(message: pg_last_error(connection: $connection));
    }
    foreach ($relation_queries as $relation_query) {
      $result = pg_query(
        connection: $connection,
        query: $relation_query
      );
      if (!$result) {
        throw new InsertIntoCollectionException(message: pg_last_error(connection: $connection));
      }
    }
  }

  /**
   * Delete rows from a collection and its relation tables.
   *
   * Matches against the collection's primary key (`id` column), not the
   * `drupal_entity_id` column. Callers holding Drupal entity IDs must first
   * resolve them to VDB row IDs (see
   * \Drupal\ai_provider_amazeeio\Vdb\Postgres\Plugin\VdbProvider\PostgresProvider::getVdbIds()).
   *
   * Any relation tables associated with the collection (discovered via the
   * `{collection}__{field}` naming convention) have their matching `chunk_id`
   * rows deleted as well.
   *
   * @param string $collection_name
   *   The name of the collection (parent table).
   * @param array $ids
   *   VDB row IDs from the collection's `id` column. NOT Drupal entity IDs.
   * @param \PgSql\Connection $connection
   *   The Postgres connection.
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DeleteFromCollectionException
   *   When the DELETE on the collection or one of its relation tables fails.
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   *   When identifier or value escaping fails.
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\GetCollectionsException
   *   When relation table discovery fails.
   */
  public function deleteFromCollection(
    string $collection_name,
    array $ids,
    Connection $connection,
  ): void {
    if (empty($ids)) {
      return;
    }
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $prepared_ids = $this->prepareStringArrayForSql(items: $ids, connection: $connection);
    $result = pg_query(
      connection: $connection,
      query: "DELETE FROM {$escaped_collection_name} WHERE id IN {$prepared_ids}"
    );
    if (!$result) {
      throw new DeleteFromCollectionException(message: pg_last_error(connection: $connection));
    }

    $relation_tables = $this->getRelationTables($collection_name, $connection);
    foreach ($relation_tables as $relation_table) {
      $escaped_relation_table = $this->escapeIdentifierForSql(
        $relation_table,
        $connection,
      );
      $result = pg_query(
        $connection,
        "DELETE FROM {$escaped_relation_table} WHERE chunk_id IN {$prepared_ids};"
      );
      if (!$result) {
        throw new DeleteFromCollectionException(message: pg_last_error(connection: $connection));
      }
    }
  }

  /**
   * Delete all rows from a collection that belong to a specific index.
   *
   * @param string $collection_name
   *   The collection name.
   * @param string $index_id
   *   The Search API index ID.
   * @param \PgSql\Connection $connection
   *   The Postgres connection.
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\DeleteFromCollectionException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   */
  public function deleteByIndexId(
    string $collection_name,
    string $index_id,
    Connection $connection,
  ): void {
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $result = pg_query_params(
      connection: $connection,
      query: "DELETE FROM {$escaped_collection_name} WHERE index_id = $1;",
      params: [$index_id],
    );
    if (!$result) {
      throw new DeleteFromCollectionException(message: pg_last_error(connection: $connection));
    }
  }

  /**
   * Returns a list of relational tables for the collection.
   *
   * It works under the assumption that relation tables use the "__" prefix for
   * additional fields.
   *
   * @param string $collection_name
   *   The collection name.
   * @param \PgSql\Connection $connection
   *   The database connection object.
   *
   * @return array
   *   The list of relational tables.
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\GetCollectionsException
   *
   * @see \Drupal\ai_provider_amazeeio\Vdb\Postgres\PostgresPgvectorClient::getRelationTableName()
   */
  protected function getRelationTables(string $collection_name, Connection $connection): array {
    $like_safe_name = str_replace(['%', '_'], ['\%', '\_'], $collection_name);
    $result = pg_query_params(
      $connection,
      'SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = $1 AND table_name LIKE $2',
      ['BASE TABLE', "{$like_safe_name}\\_\\_%"],
    );
    if (!$result) {
      throw new GetCollectionsException(message: pg_last_error(connection: $connection));
    }
    return pg_fetch_all_columns($result);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\QuerySearchException
   */
  public function querySearch(
    string $collection_name,
    array $output_fields,
    string $filters,
    int $limit,
    int $offset,
    Connection $connection,
  ): array {
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $prepared_output_fields = $this->prepareFieldArrayForSql(fields: $output_fields, connection: $connection, collection_name: $collection_name);
    $limit = (int) $limit;
    $offset = (int) $offset;
    if (empty($filters)) {
      $query = "SELECT {$prepared_output_fields} FROM {$escaped_collection_name} LIMIT {$limit} OFFSET {$offset};";
    }
    else {
      $query = "SELECT {$prepared_output_fields} FROM {$escaped_collection_name} {$filters} LIMIT {$limit} OFFSET {$offset};";
    }
    $result = pg_query(connection: $connection, query: $query);
    if (!$result) {
      throw new QuerySearchException(message: pg_last_error(connection: $connection));
    }
    return pg_fetch_all(result: $result);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\VectorSearchException
   */
  public function vectorSearch(
    string $collection_name,
    array $vector_input,
    array $output_fields,
    string $filters,
    int $limit,
    int $offset,
    VdbSimilarityMetrics $metric_type,
    Connection $connection,
  ): array {
    $metric_name = match ($metric_type) {
      VdbSimilarityMetrics::EuclideanDistance => '<->',
      VdbSimilarityMetrics::CosineSimilarity => '<=>',
      VdbSimilarityMetrics::InnerProduct => '<#>',
    };
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $prepared_output_fields = $this->prepareFieldArrayForSql(fields: $output_fields, connection: $connection, collection_name: $collection_name);
    $vectors = $this->prepareVectorArrayForSql(vector: $vector_input, connection: $connection);
    $limit = (int) $limit;
    $offset = (int) $offset;
    // Escape the output fields.
    $escaped_outfield_fields = array_map(
      callback: fn($field) => $this->escapeIdentifierForSql(identifier_to_escape: $field, connection: $connection),
      array: $output_fields
    );
    $outfield_fields = implode(',', $escaped_outfield_fields);
    $alias = 'subquery';
    if (empty($filters)) {
      // CosineSimilarity requires a special query.
      if ($metric_type === VdbSimilarityMetrics::CosineSimilarity) {
        $query = "SELECT (1-{$alias}.real_distance) as distance, {$outfield_fields} FROM (SELECT embedding {$metric_name} {$vectors} as real_distance, {$prepared_output_fields} FROM {$escaped_collection_name}) as {$alias} ORDER BY distance DESC LIMIT {$limit} OFFSET {$offset};";
      }
      else {
        $query = "SELECT embedding {$metric_name} {$vectors} as distance, {$prepared_output_fields} FROM {$escaped_collection_name} ORDER BY distance LIMIT {$limit} OFFSET {$offset};";
      }
    }
    else {
      if ($metric_type === VdbSimilarityMetrics::CosineSimilarity) {
        $query = "SELECT (1-{$alias}.real_distance) as distance, {$outfield_fields} FROM (SELECT embedding {$metric_name} {$vectors} as real_distance, {$prepared_output_fields} FROM {$escaped_collection_name} {$filters}) as {$alias} ORDER BY distance DESC LIMIT {$limit} OFFSET {$offset};";
      }
      else {
        $query = "SELECT embedding {$metric_name} {$vectors} as distance, {$prepared_output_fields} FROM {$escaped_collection_name} {$filters} ORDER BY distance LIMIT {$limit} OFFSET {$offset};";
      }
    }
    $result = pg_query(connection: $connection, query: $query);
    if (!$result) {
      throw new VectorSearchException(message: pg_last_error(connection: $connection));
    }
    return pg_fetch_all(result: $result);
  }

  /**
   * Transform an array of field identifier strings for use in a SQL statement.
   *
   * @param array $fields
   *   Field array.
   * @param \PgSql\Connection $connection
   *   The Postgres connection.
   * @param string $collection_name
   *   The name of the collection.
   *
   * @return string
   *   Array formatted as a field string.
   *   Eg: 'id,drupal_entity_id,drupal_long_id'
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   */
  public function prepareFieldArrayForSql(array $fields, Connection $connection, $collection_name = NULL): string {
    if (empty($fields)) {
      return '';
    }
    $array_formatted_as_string = '';
    $last_element = end(array: $fields);
    foreach ($fields as $field) {
      if ($collection_name) {
        $array_formatted_as_string .= $this->escapeIdentifierForSql(identifier_to_escape: $collection_name, connection: $connection) . '.';
      }
      if ($field === $last_element) {
        $array_formatted_as_string .=
          $this->escapeIdentifierForSql(identifier_to_escape: $field, connection: $connection) . '';
        break;
      }
      $array_formatted_as_string .=
        $this->escapeIdentifierForSql(identifier_to_escape: $field, connection: $connection) . ',';
    }
    return $array_formatted_as_string;
  }

  /**
   * Transform an array of vectors to string for use in a SQL statement.
   *
   * @param array $vector
   *   Vector array.
   *   Normally an array of floats.
   * @param \PgSql\Connection $connection
   *   The Postgres connection.
   *
   * @return string
   *   Array formatted as a string.
   *   Eg: '[1.22424,-2.12312,-1.34654]'
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   */
  public function prepareVectorArrayForSql(array $vector, Connection $connection): string {
    $array_formatted_as_string = '[' . implode(separator: ',', array: $vector) . ']';
    return $this->escapeStringForSql(string_to_escape: $array_formatted_as_string, connection: $connection);
  }

  /**
   * Transform an array of non-string data to string for use in a SQL statement.
   *
   * @param array $items
   *   An array of string items.
   *
   * @return string
   *   Array formatted as a string for SQL.
   *   Eg: "('first item', 'second item', 'third item')"
   */
  public function prepareArrayForSql(array $items): string {
    return '(' . implode(separator: ',', array: $items) . ')';
  }

  /**
   * Transform an array of strings to string for use in a SQL statement.
   *
   * @param array $items
   *   An array of string items.
   * @param \PgSql\Connection $connection
   *   The Postgres connection.
   *
   * @return string
   *   Array of strings formatted as a string for SQL.
   *   Eg: "('first item', 'second item', 'third item')"
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   */
  public function prepareStringArrayForSql(array $items, Connection $connection): string {
    $escaped_strings = [];
    foreach ($items as $item) {
      $escaped_strings[] = $this->escapeStringForSql(string_to_escape: $item, connection: $connection);
    }
    return '(' . implode(separator: ',', array: $escaped_strings) . ')';
  }

  /**
   * Escape a string for use in a Postgres SQL statement.
   *
   * @param string $string_to_escape
   *   The string to escape.
   * @param \PgSql\Connection $connection
   *   The Postgres connection.
   *
   * @return string
   *   A string containing the escaped data.
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   */
  private function escapeStringForSql(string $string_to_escape, Connection $connection): string {
    $result = pg_escape_literal(connection: $connection, string: $string_to_escape);
    if (!$result) {
      throw new EscapeStringException(message: pg_last_error(connection: $connection));
    }
    return $result;
  }

  /**
   * Escape a string identifier for use in a postgres SQL statement.
   *
   * @param string $identifier_to_escape
   *   The string identifier to escape.
   * @param \PgSql\Connection $connection
   *   The Postgres connection.
   *
   * @return string
   *   A string containing the escaped data.
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   */
  public function escapeIdentifierForSql(string $identifier_to_escape, Connection $connection): string {
    $result = pg_escape_identifier(connection: $connection, string: $identifier_to_escape);
    if (!$result) {
      throw new EscapeStringException(message: pg_last_error(connection: $connection));
    }
    return $result;
  }

  /**
   * Determine whether a Search API field should have its own DB column.
   *
   * Only fields configured as "Filterable attributes" in the ai_search index
   * configuration receive a per-record value at index time (see
   * \Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase::buildBaseMetadata()).
   * Fields configured as "Main content" or "Contextual content" are folded
   * into the chunked `content` text, and fields configured as "Ignore" (or
   * with no indexing option set) are not indexed at all. Creating dedicated
   * columns for the latter groups leaves permanently-NULL columns behind.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The Search API field.
   *
   * @return bool
   *   TRUE if the field is configured as a filterable attribute on its index.
   */
  public function shouldHaveColumn(FieldInterface $field): bool {
    $index = $field->getIndex();
    if (!$index) {
      return FALSE;
    }
    $config = $this->configFactory->get('ai_search.index.' . $index->id())->getRawData();
    $indexing_options = $config['indexing_options'] ?? [];
    $option = $indexing_options[$field->getFieldIdentifier()]['indexing_option'] ?? NULL;
    return $option === EmbeddingStrategyIndexingOptions::Attributes->getKey();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\AddFieldIfNotExistsException
   */
  public function updateFields($fields, string $collection_name, Connection $connection): void {
    /** @var \Drupal\search_api\Item\FieldInterface $field */
    foreach ($fields as $field) {
      if (!$this->shouldHaveColumn($field)) {
        continue;
      }
      $field_data_definition = $field->getDataDefinition();
      if ($field_data_definition instanceof FieldItemDataDefinitionInterface) {
        $isMultiple = TRUE;

        $field_definition = $field_data_definition->getFieldDefinition();
        // Set a default cardinality of 1 in case we can't get more info
        // about it.
        $field_cardinality = 1;
        if ($field_definition instanceof BaseFieldDefinition) {
          $field_cardinality = $field_definition->getCardinality();
        }
        else {
          $field_storage_definition = $field_definition->get('fieldStorage');
          if ($field_storage_definition instanceof FieldStorageDefinitionInterface) {
            $field_cardinality = $field_storage_definition->getCardinality();
          }
        }
        if ($field_cardinality === 1) {
          $isMultiple = FALSE;
        }
        $this->addFieldIfNotExists($isMultiple, $field->getType(), $field->getFieldIdentifier(), $collection_name, $connection);
      }
      else {
        [$main_property_name] = Utility::splitPropertyPath($field->getPropertyPath(), FALSE);
        $main_property = $field->getIndex()->getPropertyDefinitions($field->getDatasourceId())[$main_property_name];
        // If the main property is a list, its direct data type (e.g., "list")
        // isn't what we need for the database column. Instead, we need the
        // data type of the items within that list.
        if ($main_property->isList()) {
          $data_type = $this->fieldHelper->retrieveNestedProperty($field->getIndex()->getPropertyDefinitions($field->getDatasourceId()), $field->getPropertyPath())->getDataType();
        }
        else {
          // If it's not a list, then the main property's data type is
          // sufficient.
          $data_type = $main_property->getDataType();
        }
        // The 'search_api_text' type is a Search API internal type, which
        // for a PostgreSQL database usually corresponds to a 'TEXT' type.
        if ($data_type === 'search_api_text') {
          $data_type = 'text';
        }
        $this->addFieldIfNotExists($main_property->isList(), $data_type, $field->getFieldIdentifier(), $collection_name, $connection);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\EscapeStringException
   * @throws \Drupal\ai_provider_amazeeio\Vdb\Postgres\Exception\AddFieldIfNotExistsException
   */
  protected function addFieldIfNotExists(bool $isMultiple, string $data_type, string $name, string $collection_name, Connection $connection): void {
    $escaped_collection_name = $this->escapeIdentifierForSql(
      identifier_to_escape: $collection_name,
      connection: $connection,
    );
    $postgres_type = self::DATA_TYPE_MAPPING[$data_type] ?? 'TEXT';
    $escaped_field_name = $this->escapeIdentifierForSql($name, $connection);

    // If isMultiple is true, create a new relationship table.
    if ($isMultiple) {
      $relation_table = $this->getRelationTableName($collection_name, $name, $connection);
      $create_relation_table = "CREATE TABLE IF NOT EXISTS {$relation_table} (id SERIAL PRIMARY KEY, value {$postgres_type} NOT NULL, chunk_id INT NOT NULL, FOREIGN KEY(chunk_id) REFERENCES {$escaped_collection_name}(id) ON DELETE CASCADE);";
      $result = pg_query(connection: $connection, query: $create_relation_table);
      if (!$result) {
        throw new AddFieldIfNotExistsException(message: pg_last_error(connection: $connection));
      }
    }
    else {
      $query = "ALTER TABLE {$escaped_collection_name} ADD COLUMN IF NOT EXISTS {$escaped_field_name} {$postgres_type};";
      $result = pg_query(connection: $connection, query: $query);
      if (!$result) {
        throw new AddFieldIfNotExistsException(message: pg_last_error(connection: $connection));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareRelationQuery($collection_name, $field_name, $field_data, $connection) {
    $query = '';
    $escaped_collection_name_id_sequence = pg_escape_literal(
      $connection,
      "{$collection_name}_id_seq",
    );
    // Prepare entries for relation table.
    $escaped_relation_table_name = $this->getRelationTableName($collection_name, $field_name, $connection);

    $field_values_to_insert = [];
    if (!is_array($field_data['value'])) {
      $field_data['value'] = [$field_data['value']];
    }
    foreach ($field_data['value'] as $value) {
      if (empty($value)) {
        continue;
      }
      $escaped_field_value = $this->escapeStringForSql(string_to_escape: (string) $value, connection: $connection);
      $field_values_to_insert[] = "({$escaped_field_value}, currval({$escaped_collection_name_id_sequence}))";
    }

    if (!empty($field_values_to_insert)) {
      $query = "INSERT INTO {$escaped_relation_table_name} (value, chunk_id) values " . implode(', ', $field_values_to_insert) . ';';
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationTableName($collection_name, $field_name, $connection): string {
    return $this->escapeIdentifierForSql(
      identifier_to_escape: "{$collection_name}__{$field_name}",
      connection: $connection,
    );
  }

}
