<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\FormFieldYamlTrait;
use Drupal\eca\Service\YamlParser;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Action to send an HTTP request and store the response in a token.
 */
#[Action(
  id: 'eca_http_request',
  label: new TranslatableMarkup('HTTP: send request'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Send an HTTP request and store the response in a token.'),
  version_introduced: '3.1.10',
)]
class HttpRequest extends ConfigurableActionBase {

  use FormFieldYamlTrait;

  /**
   * The supported request methods.
   */
  protected const array METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head'];

  /**
   * The methods for which a retry cannot process a request twice.
   */
  protected const array IDEMPOTENT_METHODS = ['get', 'head', 'put', 'delete'];

  /**
   * The response status codes that are retried for idempotent methods.
   */
  protected const array RETRY_STATUS_CODES = [429, 502, 503, 504];

  /**
   * The supported body formats.
   */
  protected const array BODY_FORMATS = ['raw', 'json', 'form'];

  /**
   * The supported authentication types.
   */
  protected const array AUTH_TYPES = ['none', 'basic', 'digest'];

  /**
   * The maximum number of retries.
   */
  protected const int MAX_RETRIES = 3;

  /**
   * The maximum delay between two attempts, in seconds.
   */
  protected const int MAX_RETRY_DELAY = 5;

  /**
   * The number of bytes read from the response body at once.
   */
  protected const int READ_CHUNK_SIZE = 8192;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected ClientFactory $httpClientFactory;

  /**
   * The handler stack of the HTTP client.
   *
   * @var \GuzzleHttp\HandlerStack
   */
  protected HandlerStack $handlerStack;

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->httpClient = $container->get('http_client');
    $instance->httpClientFactory = $container->get('http_client_factory');
    // Core declares http_handler_stack private, eca_base exposes it through a
    // public alias.
    $instance->handlerStack = $container->get('eca_base.http_handler_stack');
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'method' => 'get',
      'url' => '',
      'query' => '',
      'headers' => '',
      'body' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
      'body_format' => 'raw',
      'auth_type' => 'none',
      'auth_username' => '',
      'auth_password' => '',
      'timeout' => 30,
      'retries' => 0,
      'max_response_size' => 1048576,
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = parent::access($object, $account, TRUE);
    if ($result->isAllowed() && $this->configuration['use_yaml'] && $this->configuration['validate_yaml']) {
      try {
        $this->yamlParser->parse((string) $this->configuration['body']);
      }
      catch (ParseException) {
        $result = AccessResult::forbidden('YAML data is not valid.');
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $method = (string) $this->configuration['method'];
    $url = trim($this->replaceTokens((string) $this->configuration['url']));
    $context = [
      '@method' => strtoupper($method),
      '@url' => $this->getRedactedUrl($url),
    ];

    if (!in_array($method, self::METHODS, TRUE)) {
      $this->logger->error('HTTP request @method @url not sent: the method is not supported.', $context);
      $this->setResponseToken(2);
      return;
    }
    if (!$this->isValidUrl($url)) {
      $this->logger->error('HTTP request @method @url not sent: the URL must use http or https and have a host.', $context);
      $this->setResponseToken(2);
      return;
    }
    $options = $this->buildRequestOptions($context);
    if ($options === NULL) {
      $this->setResponseToken(2);
      return;
    }

    $retries = max(0, min(self::MAX_RETRIES, (int) $this->configuration['retries']));
    $client = $retries > 0 ? $this->buildRetryClient($retries) : $this->httpClient;

    try {
      $response = $client->request(strtoupper($method), $url, $options);
    }
    catch (\InvalidArgumentException $e) {
      // Guzzle and PSR-7 reject invalid options, for example a header value
      // with a line break. The exception message may contain such values, so
      // only its class is logged.
      $context['@exception'] = get_class($e);
      $this->logger->error('HTTP request @method @url not sent: the request is invalid (@exception).', $context);
      $this->setResponseToken(2);
      return;
    }
    catch (GuzzleException $e) {
      // Transport errors. The exception message contains the full URL, so only
      // its class is logged.
      // Guzzle 7 exposes the response on RequestException, Guzzle 8 only on
      // its ResponseException subclass.
      $response = method_exists($e, 'getResponse') ? $e->getResponse() : NULL;
      if (!$response instanceof ResponseInterface) {
        $response = NULL;
      }
      $context['@exception'] = get_class($e);
      $this->logger->error('HTTP request @method @url failed: @exception.', $context);
      $this->setResponseToken(1, $response?->getStatusCode() ?? 0, $response?->getHeaders() ?? []);
      return;
    }

    $status = $response->getStatusCode();
    $max_size = max(1, (int) $this->configuration['max_response_size']);
    $body = $this->readBody($response, $max_size);
    if ($body === NULL) {
      $context['@max'] = $max_size;
      $this->logger->error('HTTP request @method @url: the response is larger than @max bytes and was not stored.', $context);
      $this->setResponseToken(2, $status, $response->getHeaders());
      return;
    }
    $client_error = $status >= 400 ? 1 : 0;
    if ($client_error) {
      $context['@status'] = $status;
      $this->logger->warning('HTTP request @method @url returned status @status.', $context);
    }
    $this->setResponseToken($client_error, $status, $response->getHeaders(), $body);
  }

  /**
   * Builds the Guzzle request options from the allowlisted settings.
   *
   * @param array $context
   *   The log message context with the method and the redacted URL.
   *
   * @return array|null
   *   The request options, or NULL if the configuration is invalid. In that
   *   case an error has been logged already.
   */
  protected function buildRequestOptions(array $context): ?array {
    $options = [
      'http_errors' => FALSE,
      'timeout' => max(1, (int) $this->configuration['timeout']),
    ];

    $auth_type = (string) $this->configuration['auth_type'];
    if (!in_array($auth_type, self::AUTH_TYPES, TRUE)) {
      $this->logger->error('HTTP request @method @url not sent: the authentication type is not supported.', $context);
      return NULL;
    }
    if ($auth_type === 'digest' && !$this->isDigestSupported()) {
      $this->logger->error('HTTP request @method @url not sent: digest authentication requires the PHP cURL extension.', $context);
      return NULL;
    }
    if ($auth_type !== 'none') {
      $username = $this->replaceTokens((string) $this->configuration['auth_username']);
      $password = $this->replaceTokens((string) $this->configuration['auth_password']);
      $options['auth'] = $auth_type === 'digest' ? [$username, $password, 'digest'] : [$username, $password];
    }

    foreach (['query', 'headers'] as $field) {
      $yaml = (string) $this->configuration[$field];
      if (trim($yaml) === '') {
        continue;
      }
      try {
        $value = $this->replaceTokensInData($this->yamlParser->parse($yaml, FALSE));
      }
      catch (ParseException) {
        $this->logger->error('HTTP request @method @url not sent: the @field field is not valid YAML.', $context + ['@field' => $field]);
        return NULL;
      }
      if ($value === NULL) {
        continue;
      }
      if (!is_array($value)) {
        $this->logger->error('HTTP request @method @url not sent: the @field field must contain a YAML mapping.', $context + ['@field' => $field]);
        return NULL;
      }
      $options[$field] = $field === 'headers' ? $this->normalizeHeaderValues($value) : $value;
    }

    $body_format = (string) $this->configuration['body_format'];
    if (!in_array($body_format, self::BODY_FORMATS, TRUE)) {
      $this->logger->error('HTTP request @method @url not sent: the body format is not supported.', $context);
      return NULL;
    }
    $body = (string) $this->configuration['body'];
    if (trim($body) === '') {
      return $options;
    }
    // Structured bodies are parsed or decoded first and tokens are replaced in
    // the resulting values afterwards, so that token data cannot change the
    // structure of the body.
    if ($this->configuration['use_yaml']) {
      try {
        $data = $this->replaceTokensInData($this->yamlParser->parse($body, FALSE));
      }
      catch (ParseException) {
        $this->logger->error('HTTP request @method @url not sent: the body is not valid YAML.', $context);
        return NULL;
      }
    }
    elseif ($body_format === 'json') {
      if (!json_validate($body)) {
        $this->logger->error('HTTP request @method @url not sent: the body is not valid JSON.', $context);
        return NULL;
      }
      $data = $this->replaceTokensInData(Json::decode($body));
    }
    else {
      $data = $this->replaceTokens($body);
    }
    if ($data === NULL) {
      return $options;
    }

    switch ($body_format) {
      case 'json':
        $options['json'] = $data;
        break;

      case 'form':
        if (!is_array($data)) {
          $this->logger->error('HTTP request @method @url not sent: the form body format requires the body as a YAML mapping.', $context);
          return NULL;
        }
        $options['form_params'] = $data;
        break;

      default:
        if (is_array($data)) {
          $this->logger->error('HTTP request @method @url not sent: structured YAML data requires the JSON or form body format.', $context);
          return NULL;
        }
        $options['body'] = (string) $data;
    }
    return $options;
  }

  /**
   * Casts scalar header values to strings.
   *
   * YAML turns values such as 1 or true into numbers and booleans. Guzzle 7
   * casts them to strings, Guzzle 8 rejects them. Casting them here the way
   * Guzzle 7 does sends the same headers on both versions. A header with an
   * empty list is dropped, because Guzzle 7 sends no value for it and Guzzle 8
   * rejects it. Other values, for example nested arrays, are passed on
   * unchanged, so that Guzzle rejects the request as invalid.
   *
   * @param array $headers
   *   The headers, keyed by header name.
   *
   * @return array
   *   The headers with scalar and NULL values cast to strings.
   */
  protected function normalizeHeaderValues(array $headers): array {
    $cast = static fn (mixed $value): mixed => $value === NULL || is_scalar($value) ? (string) $value : $value;
    $normalized = [];
    foreach ($headers as $name => $value) {
      if ($value === []) {
        continue;
      }
      $normalized[$name] = is_array($value) ? array_values(array_map($cast, $value)) : $cast($value);
    }
    return $normalized;
  }

  /**
   * Replaces tokens in parsed or decoded data, exactly once.
   *
   * Tokens are replaced in string keys and non-empty string values.
   *
   * @param mixed $data
   *   The parsed YAML or decoded JSON data.
   *
   * @return mixed
   *   The data with tokens replaced.
   */
  protected function replaceTokensInData(mixed $data): mixed {
    if (is_string($data)) {
      return $data === '' ? $data : $this->replaceTokens($data);
    }
    if (!is_array($data)) {
      return $data;
    }
    $result = [];
    foreach ($data as $key => $value) {
      if (is_string($key)) {
        $key = $this->replaceTokens($key);
      }
      $result[$key] = $this->replaceTokensInData($value);
    }
    return $result;
  }

  /**
   * Replaces tokens in a string with their unmodified values, exactly once.
   *
   * HTTP data is not HTML, so token values must arrive byte-identical. Core
   * escapes plain-string token values into HtmlEscapedText, while ECA wraps its
   * own token data as Markup, which stays unescaped. Token::replacePlain() is
   * no alternative: it strips tags from and decodes entities in Markup values.
   * Hence the callback, which core runs after escaping, decodes only the
   * escaped plain-string values and leaves Markup values untouched.
   *
   * @param string $text
   *   The text containing tokens.
   *
   * @return string
   *   The text with tokens replaced, unknown tokens removed.
   *
   * @see \Drupal\Core\Utility\Token::doReplace()
   * @see \Drupal\eca\Hook\TokenHooks::tokens()
   */
  protected function replaceTokens(string $text): string {
    $unescape = static function (array &$replacements): void {
      foreach ($replacements as $token => $value) {
        if ($value instanceof HtmlEscapedText) {
          $replacements[$token] = Html::decodeEntities((string) $value);
        }
      }
    };
    return (string) $this->tokenService->replaceClear($text, [], ['callback' => $unescape]);
  }

  /**
   * Builds a client that retries on a clone of the configured handler stack.
   *
   * The shared handler stack stays untouched, and the clone keeps all
   * middleware that is tagged with http_client_middleware.
   *
   * @param int $max_retries
   *   The maximum number of retries.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The HTTP client.
   */
  protected function buildRetryClient(int $max_retries): ClientInterface {
    $decider = static function (int $retries, RequestInterface $request, ?ResponseInterface $response = NULL, mixed $reason = NULL) use ($max_retries): bool {
      if ($retries >= $max_retries) {
        return FALSE;
      }
      // The request never left, so it can be retried for every method.
      if ($reason instanceof ConnectException) {
        return TRUE;
      }
      // Another method may already have been processed by the server.
      return $response !== NULL
        && in_array($response->getStatusCode(), self::RETRY_STATUS_CODES, TRUE)
        && in_array(strtolower($request->getMethod()), self::IDEMPOTENT_METHODS, TRUE);
    };
    $stack = clone $this->handlerStack;
    $stack->push(Middleware::retry($decider, $this->getRetryDelay(...)), 'eca_http_request_retry');
    return $this->httpClientFactory->fromOptions(['handler' => $stack]);
  }

  /**
   * Calculates the delay before the next attempt.
   *
   * @param int $retries
   *   The number of the upcoming retry, starting at 1.
   * @param \Psr\Http\Message\ResponseInterface|null $response
   *   The response of the previous attempt, if any.
   *
   * @return int
   *   The delay in milliseconds: 1, 2 and 4 seconds, or the Retry-After value
   *   of the response, capped at 5 seconds.
   */
  protected function getRetryDelay(int $retries, ?ResponseInterface $response = NULL): int {
    $seconds = 2 ** max(0, $retries - 1);
    if ($response !== NULL && $response->hasHeader('Retry-After')) {
      $retry_after = trim($response->getHeaderLine('Retry-After'));
      if (ctype_digit($retry_after)) {
        $seconds = (int) $retry_after;
      }
      elseif (($timestamp = strtotime($retry_after)) !== FALSE) {
        $seconds = max(0, $timestamp - $this->time->getCurrentTime());
      }
    }
    return min($seconds, self::MAX_RETRY_DELAY) * 1000;
  }

  /**
   * Reads the response body up to the given size.
   *
   * The handler has already received the response into its own buffer, as
   * the "stream" request option is deliberately not used: Guzzle 7 routes
   * streamed requests to the stream handler, which ignores the cURL options
   * its digest authentication needs. The limit therefore caps what is
   * stored, and the timeout bounds the download.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response.
   * @param int $max_size
   *   The maximum number of bytes.
   *
   * @return string|null
   *   The body, or NULL if it is larger than the maximum size.
   */
  protected function readBody(ResponseInterface $response, int $max_size): ?string {
    $stream = $response->getBody();
    if ($stream->isSeekable()) {
      $stream->rewind();
    }
    $body = '';
    $length = 0;
    while (!$stream->eof()) {
      $chunk = $stream->read(self::READ_CHUNK_SIZE);
      if ($chunk === '') {
        break;
      }
      $length += strlen($chunk);
      if ($length > $max_size) {
        $stream->close();
        return NULL;
      }
      $body .= $chunk;
    }
    return $body;
  }

  /**
   * Stores the response token.
   *
   * @param int $client_error
   *   The error level: 0 for a 2xx or 3xx response, 1 for a 4xx or 5xx
   *   response or a transport error, 2 for a configuration or processing error.
   * @param int $status
   *   The status code of the last attempt, 0 when no response was received.
   * @param array $headers
   *   The response headers.
   * @param string $body
   *   The response body.
   */
  protected function setResponseToken(int $client_error, int $status = 0, array $headers = [], string $body = ''): void {
    $token_name = trim((string) $this->configuration['token_name']);
    if ($token_name === '') {
      return;
    }
    $result = ['body' => $body];
    if ($body !== '' && json_validate($body)) {
      $result['json'] = Json::decode($body);
    }
    $result['headers'] = $headers;
    $result['status'] = $status;
    $result['client_error'] = $client_error;
    $this->tokenService->addTokenData($token_name, $result);
  }

  /**
   * Checks whether the URL uses http or https and has a host.
   *
   * @param string $url
   *   The URL.
   *
   * @return bool
   *   TRUE if the URL is valid for a request, FALSE otherwise.
   */
  protected function isValidUrl(string $url): bool {
    $parts = parse_url($url);
    return is_array($parts)
      && isset($parts['scheme'], $parts['host'])
      && in_array(strtolower($parts['scheme']), ['http', 'https'], TRUE)
      && $parts['host'] !== '';
  }

  /**
   * Returns the URL without user info, query string and fragment.
   *
   * @param string $url
   *   The URL.
   *
   * @return string
   *   The URL to be used in log messages.
   */
  protected function getRedactedUrl(string $url): string {
    $parts = parse_url($url);
    if (!is_array($parts)) {
      return '(invalid URL)';
    }
    $redacted = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $redacted .= $parts['host'] ?? '';
    $redacted .= isset($parts['port']) ? ':' . $parts['port'] : '';
    return $redacted . ($parts['path'] ?? '');
  }

  /**
   * Checks whether the PHP cURL extension is available.
   *
   * @return bool
   *   TRUE if cURL is available, FALSE otherwise.
   */
  protected function isCurlAvailable(): bool {
    return function_exists('curl_exec');
  }

  /**
   * Checks whether digest authentication is supported.
   *
   * Guzzle 7 implements digest authentication through cURL options, Guzzle 8
   * in its auth middleware.
   *
   * @return bool
   *   TRUE if digest authentication is supported, FALSE otherwise.
   */
  protected function isDigestSupported(): bool {
    return ClientInterface::MAJOR_VERSION >= 8 || $this->isCurlAvailable();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $credentials_description = $this->t('Supports tokens. Values typed here are stored in the model and exported with the configuration. Use a token instead, for example from the "Config: read" action with "Include overridden" enabled, so that the secret can live in settings.php.');
    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#options' => [
        'get' => 'GET',
        'post' => 'POST',
        'put' => 'PUT',
        'patch' => 'PATCH',
        'delete' => 'DELETE',
        'head' => 'HEAD',
      ],
      '#default_value' => $this->configuration['method'],
      '#required' => TRUE,
      '#weight' => -100,
    ];
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('The URL to send the request to. Supports tokens. After token replacement, the URL must use http or https and have a host, otherwise no request is sent.'),
      '#default_value' => $this->configuration['url'],
      '#maxlength' => 2048,
      '#required' => TRUE,
      '#weight' => -90,
      '#eca_token_replacement' => TRUE,
    ];
    $form['query'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query parameters'),
      '#description' => $this->t('A YAML mapping, for example <em>page: 2</em>. Supports tokens in keys and values, which are replaced after parsing. Wrap tokens in quotes, for example <em>title: "[node:title]"</em>.'),
      '#default_value' => $this->configuration['query'],
      '#weight' => -80,
      '#eca_token_replacement' => TRUE,
    ];
    $form['headers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Headers'),
      '#description' => $this->t('A YAML mapping, for example <em>Accept: application/json</em>. Supports tokens in keys and values, which are replaced after parsing. Wrap tokens in quotes, for example <em>X-Api-Key: "[api_key]"</em>.'),
      '#default_value' => $this->configuration['headers'],
      '#weight' => -70,
      '#eca_token_replacement' => TRUE,
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#description' => $this->t('The request body. Supports tokens. With the JSON format, the body is decoded first and tokens are replaced in its string values afterwards, so tokens must be quoted inside JSON and arrive as strings, for example <em>{"title": "[node:title]"}</em>.'),
      '#default_value' => $this->configuration['body'],
      '#weight' => -60,
      '#eca_token_replacement' => TRUE,
    ];
    $this->buildYamlFormFields(
      $form,
      $this->t('Interpret the body as YAML format'),
      $this->t('When enabled, the body is parsed as YAML first and tokens are replaced in the parsed values. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>title: "[node:title]"</em>'),
      -50,
    );
    $form['body_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Body format'),
      '#description' => $this->t('Raw sends the body as it is. JSON sends the body as JSON. Form sends the body URL-encoded as form fields and requires the body as a YAML mapping, with the option above enabled.'),
      '#options' => [
        'raw' => $this->t('Raw'),
        'json' => $this->t('JSON'),
        'form' => $this->t('Form'),
      ],
      '#default_value' => $this->configuration['body_format'],
      '#required' => TRUE,
      '#weight' => -40,
    ];
    $auth_description = $this->t('Basic sends an Authorization header. Digest answers the authentication challenge of the server.');
    if (!$this->isDigestSupported()) {
      $auth_description = $this->t('Basic sends an Authorization header. <strong>Warning:</strong> digest authentication requires the PHP cURL extension, which is not available on this site, so requests with digest authentication are not sent.');
    }
    $form['auth_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Authentication'),
      '#description' => $auth_description,
      '#options' => [
        'none' => $this->t('None'),
        'basic' => $this->t('Basic'),
        'digest' => $this->t('Digest'),
      ],
      '#default_value' => $this->configuration['auth_type'],
      '#required' => TRUE,
      '#weight' => -30,
    ];
    $form['auth_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $credentials_description,
      '#default_value' => $this->configuration['auth_username'],
      '#weight' => -29,
      '#eca_token_replacement' => TRUE,
    ];
    $form['auth_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#description' => $credentials_description,
      '#default_value' => $this->configuration['auth_password'],
      '#weight' => -28,
      '#eca_token_replacement' => TRUE,
    ];
    $form['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout'),
      '#description' => $this->t('Seconds per attempt. With retries, the worst case is (retries + 1) * timeout, plus up to @delay seconds of delay between attempts. For a longer back-off, use ECA Queue: check <em>[name:client_error]</em> and re-enqueue with "Enqueue a task with a delay" and an attempt counter.', ['@delay' => self::MAX_RETRY_DELAY]),
      '#default_value' => $this->configuration['timeout'],
      '#min' => 1,
      '#required' => TRUE,
      '#weight' => -20,
    ];
    $form['retries'] = [
      '#type' => 'number',
      '#title' => $this->t('Retries'),
      '#description' => $this->t('Between 0 and 3. A connection failure is retried for every method. Status 429, 502, 503 and 504 are retried only for GET, HEAD, PUT and DELETE, because the server may already have processed other methods. The delay is 1, 2 and 4 seconds, or the Retry-After header, capped at @delay seconds.', ['@delay' => self::MAX_RETRY_DELAY]),
      '#default_value' => $this->configuration['retries'],
      '#min' => 0,
      '#max' => self::MAX_RETRIES,
      '#required' => TRUE,
      '#weight' => -19,
    ];
    $form['max_response_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum response size'),
      '#description' => $this->t('In bytes. A larger response is not stored, an error is logged and <em>client_error</em> is 2.'),
      '#default_value' => $this->configuration['max_response_size'],
      '#min' => 1,
      '#required' => TRUE,
      '#weight' => -18,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of response token'),
      '#description' => $this->t('The response is stored in this token: <em>[name:body]</em> is the response body, <em>[name:json]</em> the decoded body when it is valid JSON, <em>[name:headers]</em> the response headers, <em>[name:status]</em> the status code (0 when no response was received) and <em>[name:client_error]</em> is 0 for a 2xx or 3xx response, 1 for a 4xx or 5xx response or a transport error and 2 for a configuration or processing error.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -10,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    foreach (['timeout', 'max_response_size'] as $key) {
      if (filter_var($form_state->getValue($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE) {
        $form_state->setErrorByName($key, $this->t('The value must be a positive integer.'));
      }
    }
    $retries_range = [
      'options' => [
        'min_range' => 0,
        'max_range' => self::MAX_RETRIES,
      ],
    ];
    if (filter_var($form_state->getValue('retries'), FILTER_VALIDATE_INT, $retries_range) === FALSE) {
      $form_state->setErrorByName('retries', $this->t('The number of retries must be an integer between 0 and @max.', ['@max' => self::MAX_RETRIES]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $string_keys = [
      'method',
      'url',
      'query',
      'headers',
      'body',
      'body_format',
      'auth_type',
      'auth_username',
      'auth_password',
      'token_name',
    ];
    foreach ($string_keys as $key) {
      $this->configuration[$key] = (string) $form_state->getValue($key);
    }
    $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
    $this->configuration['validate_yaml'] = !empty($form_state->getValue('validate_yaml'));
    foreach (['timeout', 'retries', 'max_response_size'] as $key) {
      $this->configuration[$key] = (int) $form_state->getValue($key);
    }
    parent::submitConfigurationForm($form, $form_state);
  }

}
