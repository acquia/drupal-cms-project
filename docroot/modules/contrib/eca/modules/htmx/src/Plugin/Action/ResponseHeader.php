<?php

declare(strict_types=1);

namespace Drupal\eca_htmx\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxLocationResponseData;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Service\YamlParser;
use Drupal\eca_endpoint\Plugin\Action\ResponseActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Sets one of the HTMX HX-* response headers on the current response.
 *
 * All headers are produced through the core \Drupal\Core\Htmx\Htmx builder;
 * the resulting header is then copied onto the current response's HeaderBag.
 *
 * Response-attachment mechanism: this action extends the eca_endpoint
 * ResponseActionBase, which resolves the active response from either the
 * eca_endpoint EndpointResponseEvent or the kernel ResponseEvent (eca_misc
 * "kernel:response" event). This mirrors how the existing eca_endpoint
 * "Response: set headers" action shapes responses, so HTMX headers integrate
 * with the same event-driven response flow rather than relying on a render
 * array that may not exist in a header-only model.
 */
#[Action(
  id: 'eca_htmx_response_header',
  label: new TranslatableMarkup('HTMX: set response header'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Set one of the HTMX HX-* response headers (e.g. redirect, location, push URL, refresh or trigger) on the current response. Reacts to a response event, for example the "Response created" event of the ECA Miscellaneous module or an ECA Endpoint response.'),
  version_introduced: '3.1.3',
)]
class ResponseHeader extends ResponseActionBase {

  use PluginFormTrait;
  use HtmxConfigTrait;

  /**
   * Header keys whose value is interpreted as a URL.
   */
  protected const array URL_HEADERS = [
    'redirect',
    'location',
    'push_url',
    'replace_url',
  ];

  /**
   * Header keys whose value is interpreted as a boolean.
   */
  protected const array BOOLEAN_HEADERS = [
    'refresh',
  ];

  /**
   * Header keys whose value is interpreted as a trigger definition.
   */
  protected const array TRIGGER_HEADERS = [
    'trigger',
  ];

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
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'header' => 'redirect',
      'value' => '',
      'location_source' => '',
      'location_event' => '',
      'location_handler' => '',
      'location_target' => '',
      'location_swap' => '',
      'location_select' => '',
      'location_values' => '',
      'location_headers' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['header'] = [
      '#type' => 'select',
      '#title' => $this->t('Response header'),
      '#description' => $this->t('The HTMX response header to set.'),
      '#options' => [
        'redirect' => $this->t('Redirect (HX-Redirect)'),
        'location' => $this->t('Location (HX-Location)'),
        'push_url' => $this->t('Push URL (HX-Push-Url)'),
        'replace_url' => $this->t('Replace URL (HX-Replace-Url)'),
        'refresh' => $this->t('Refresh (HX-Refresh)'),
        'retarget' => $this->t('Retarget (HX-Retarget)'),
        'reswap' => $this->t('Reswap (HX-Reswap)'),
        'reselect' => $this->t('Reselect (HX-Reselect)'),
        'trigger' => $this->t('Trigger (HX-Trigger)'),
      ],
      '#default_value' => $this->configuration['header'],
      '#weight' => -20,
      '#eca_token_select_option' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#description' => $this->t('The header value. For URL headers provide a URL (internal paths start with a slash); for the refresh header use <em>true</em> or <em>false</em>; for trigger headers provide the event name; for retarget/reswap/reselect provide the CSS selector or swap strategy. For the location header this is the path (URL).'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];

    // The following fields populate the rich HX-Location response data. They
    // are only relevant when the "location" header is selected, so they are
    // shown conditionally via #states keyed on the header select - matching the
    // convention used by other ECA action forms (e.g. the content entity
    // loader). All other headers ignore these values.
    $location_visible = [
      'visible' => [
        ':input[name="header"]' => ['value' => 'location'],
      ],
    ];
    $form['location_source'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location: source'),
      '#description' => $this->t('The source element of the request.'),
      '#default_value' => $this->configuration['location_source'],
      '#weight' => -9,
      '#eca_token_replacement' => TRUE,
      '#states' => $location_visible,
    ];
    $form['location_event'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location: event'),
      '#description' => $this->t('An event that "triggered" the request.'),
      '#default_value' => $this->configuration['location_event'],
      '#weight' => -8,
      '#eca_token_replacement' => TRUE,
      '#states' => $location_visible,
    ];
    $form['location_handler'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location: handler'),
      '#description' => $this->t('A callback that will handle the response HTML.'),
      '#default_value' => $this->configuration['location_handler'],
      '#weight' => -7,
      '#eca_token_replacement' => TRUE,
      '#states' => $location_visible,
    ];
    $form['location_target'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location: target'),
      '#description' => $this->t('The CSS selector of the target for the swap.'),
      '#default_value' => $this->configuration['location_target'],
      '#weight' => -6,
      '#eca_token_replacement' => TRUE,
      '#states' => $location_visible,
    ];
    $form['location_swap'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location: swap'),
      '#description' => $this->t('The swap strategy, for example <em>innerHTML</em> or <em>outerHTML</em>.'),
      '#default_value' => $this->configuration['location_swap'],
      '#weight' => -5,
      '#eca_token_replacement' => TRUE,
      '#states' => $location_visible,
    ];
    $form['location_select'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location: select'),
      '#description' => $this->t('A CSS selector for the content to swap into the target.'),
      '#default_value' => $this->configuration['location_select'],
      '#weight' => -4,
      '#eca_token_replacement' => TRUE,
      '#states' => $location_visible,
    ];
    $form['location_values'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Location: values'),
      '#description' => $this->t('A set of values to submit with the request, as a key-value list in YAML format. Example:<em><br />key: value</em>. When using tokens, wrap them as a string. Example: <em>id: "[node:nid]"</em>.'),
      '#default_value' => $this->configuration['location_values'],
      '#weight' => -3,
      '#eca_token_replacement' => TRUE,
      '#states' => $location_visible,
    ];
    $form['location_headers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Location: headers'),
      '#description' => $this->t('Headers to submit with the request, as a key-value list in YAML format. Example:<em><br />X-Custom: value</em>. When using tokens, wrap them as a string. Example: <em>X-Custom: "[token]"</em>.'),
      '#default_value' => $this->configuration['location_headers'],
      '#weight' => -2,
      '#eca_token_replacement' => TRUE,
      '#states' => $location_visible,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['header'] = $form_state->getValue('header');
    $this->configuration['value'] = $form_state->getValue('value');
    $this->configuration['location_source'] = $form_state->getValue('location_source');
    $this->configuration['location_event'] = $form_state->getValue('location_event');
    $this->configuration['location_handler'] = $form_state->getValue('location_handler');
    $this->configuration['location_target'] = $form_state->getValue('location_target');
    $this->configuration['location_swap'] = $form_state->getValue('location_swap');
    $this->configuration['location_select'] = $form_state->getValue('location_select');
    $this->configuration['location_values'] = $form_state->getValue('location_values');
    $this->configuration['location_headers'] = $form_state->getValue('location_headers');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    $header = $this->configuration['header'];
    if ($header === '_eca_token') {
      $header = $this->getTokenValue('header', 'redirect');
    }
    $value = trim((string) $this->tokenService->replaceClear($this->configuration['value']));

    $htmx = new Htmx();
    if (!$this->applyHeader($htmx, $header, $value)) {
      return;
    }

    // Copy the header(s) produced by the Htmx builder onto the response.
    $response_headers = $this->getResponse()->headers;
    foreach ($htmx->getHeaders()->all() as $name => $values) {
      foreach ($values as $header_value) {
        $response_headers->set($name, $header_value, TRUE);
      }
    }
  }

  /**
   * Calls the matching Htmx builder method for the chosen header.
   *
   * @param \Drupal\Core\Htmx\Htmx $htmx
   *   The Htmx builder.
   * @param string $header
   *   The configured header key.
   * @param string $value
   *   The resolved header value.
   *
   * @return bool
   *   TRUE when a header was set, FALSE otherwise (e.g. an invalid URL).
   */
  protected function applyHeader(Htmx $htmx, string $header, string $value): bool {
    if (in_array($header, self::URL_HEADERS, TRUE)) {
      if ($header === 'push_url' && $value === 'false') {
        $htmx->pushUrlHeader(FALSE);
        return TRUE;
      }
      if ($header === 'replace_url' && $value === 'false') {
        $htmx->replaceUrlHeader(FALSE);
        return TRUE;
      }
      $url = $this->htmxUrl($value);
      if ($url === NULL) {
        $this->logger->error('The action "eca_htmx_response_header" received an invalid URL for the %header header.', [
          '%header' => $header,
        ]);
        return FALSE;
      }
      match ($header) {
        'redirect' => $htmx->redirectHeader($url),
        'location' => $htmx->locationHeader($this->buildLocationData($url)),
        'push_url' => $htmx->pushUrlHeader($url),
        'replace_url' => $htmx->replaceUrlHeader($url),
      };
      return TRUE;
    }
    if (in_array($header, self::BOOLEAN_HEADERS, TRUE)) {
      $htmx->refreshHeader($value === 'true' || $value === '1');
      return TRUE;
    }
    if (in_array($header, self::TRIGGER_HEADERS, TRUE)) {
      $htmx->triggerHeader($value);
      return TRUE;
    }
    match ($header) {
      'retarget' => $htmx->retargetHeader($value),
      'reswap' => $htmx->reswapHeader($value),
      'reselect' => $htmx->reselectHeader($value),
      default => NULL,
    };
    return TRUE;
  }

  /**
   * Builds the rich HX-Location data object from the configured fields.
   *
   * Only non-empty values are passed to HtmxLocationResponseData so that the
   * resulting JSON only contains the data the model actually configured.
   *
   * @param \Drupal\Core\Url $path
   *   The resolved path URL (the existing "value" field).
   *
   * @return \Drupal\Core\Htmx\HtmxLocationResponseData
   *   The location response data object.
   */
  protected function buildLocationData(Url $path): HtmxLocationResponseData {
    return new HtmxLocationResponseData(
      path: $path,
      source: trim((string) $this->tokenService->replaceClear($this->configuration['location_source'])),
      event: trim((string) $this->tokenService->replaceClear($this->configuration['location_event'])),
      handler: trim((string) $this->tokenService->replaceClear($this->configuration['location_handler'])),
      target: trim((string) $this->tokenService->replaceClear($this->configuration['location_target'])),
      swap: trim((string) $this->tokenService->replaceClear($this->configuration['location_swap'])),
      values: $this->parseKeyValue($this->configuration['location_values']),
      headers: $this->parseKeyValue($this->configuration['location_headers']),
      select: trim((string) $this->tokenService->replaceClear($this->configuration['location_select'])),
    );
  }

  /**
   * Parses a YAML key-value map from a configuration value.
   *
   * @param string $config
   *   The raw configuration value (YAML key-value list).
   *
   * @return array<string, string>
   *   The parsed key-value pairs, or an empty array when none are configured
   *   or the value cannot be parsed as YAML.
   */
  protected function parseKeyValue(string $config): array {
    $value = trim((string) $this->tokenService->replaceClear($config));
    if ($value === '') {
      return [];
    }
    try {
      $parsed = $this->yamlParser->parse($value);
    }
    catch (ParseException) {
      $this->logger->error('Tried parsing a key-value list in action "eca_htmx_response_header" as YAML format, but parsing failed.');
      return [];
    }
    return is_array($parsed) ? $parsed : [];
  }

}
