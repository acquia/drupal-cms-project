<?php

namespace Drupal\eca_access\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\FormFieldYamlTrait;
use Drupal\eca\Service\YamlParser;
use Drupal\eca_file\Event\FileDownload;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Action to set a file download access result.
 */
#[Action(
  id: 'eca_access_set_file_download_result',
  label: new TranslatableMarkup('Set file download access result'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Only works when reacting upon <em>ECA File</em> download event.'),
  version_introduced: '3.1.3',
)]
class SetFileDownloadAccessResult extends SetAccessResult {

  use FormFieldYamlTrait;

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
    $instance->setYamlParser($container->get('eca.service.yaml_parser'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->event instanceof FileDownload ? AccessResult::allowed() : AccessResult::forbidden('Event is not compatible with this action.');
    if ($result->isAllowed() && $this->configuration['use_yaml'] && $this->configuration['validate_yaml']) {
      try {
        $this->yamlParser->parse($this->configuration['headers']);
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
    if (!($this->event instanceof FileDownload)) {
      return;
    }

    // Resolve the configured access result, exactly as the parent does, so we
    // can decide whether headers should be applied. Headers must only be set
    // when access is explicitly allowed: the form hides the headers field for
    // 'forbidden', but a stale value can persist, and 'neutral' must not carry
    // headers either.
    $accessResult = $this->configuration['access_result'];
    if ($accessResult === '_eca_token') {
      $accessResult = $this->getTokenValue('access_result', 'forbidden');
    }

    if ($accessResult === 'allowed') {
      $headers = $this->parseConfiguredHeaders();
      if (!empty($headers)) {
        $this->event->setHeaders($headers);
      }
    }

    parent::execute($object);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'headers' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['headers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Headers'),
      '#description' => $this->t('List of HTTP headers.<br />Enter one per line in the format Header-Name:value, or enable YAML format below.<br />Example:<br />Content-Disposition: attachment; filename="config.tar.gz"<br />Origin: https://ecaguide.org/<br />'),
      '#default_value' => $this->configuration['headers'],
      '#states' => [
        'invisible' => [
          'select[name="access_result"]' => ['value' => 'forbidden'],
        ],
      ],
      '#weight' => 20,
      '#eca_token_replacement' => TRUE,
    ];
    $this->buildYamlFormFields(
      $form,
      $this->t('Interpret above value as YAML format'),
      $this->t('Nested data can be set using YAML format, for example <em>Content-Type: "text/html; charset=UTF-8"</em>. When using this format, this option needs to be enabled. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>Content-Type: "[content_type]"</em>'),
      30,
    );

    $form = parent::buildConfigurationForm($form, $form_state);
    $form['access_result']['#description'] = $this->t('Please note: This action only works when reacting upon <em>ECA File download</em> events.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['headers'] = $form_state->getValue('headers', '');
    $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
    $this->configuration['validate_yaml'] = !empty($form_state->getValue('validate_yaml'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Set the YAML parser.
   *
   * @param \Drupal\eca\Service\YamlParser $yaml_parser
   *   The YAML parser.
   */
  public function setYamlParser(YamlParser $yaml_parser): void {
    $this->yamlParser = $yaml_parser;
  }

  /**
   * Parses the configured headers into an associative array.
   *
   * When YAML mode is enabled, the value is parsed by the ECA YAML parser
   * service (which also resolves tokens). Otherwise the legacy plain format
   * "Header-Name: value" (one per line) is parsed, after token replacement.
   *
   * @return array<string, string>
   *   An associative array of headers, keyed by header name. Empty when the
   *   configuration is empty or YAML parsing fails.
   */
  private function parseConfiguredHeaders(): array {
    $headers = $this->configuration['headers'];

    if ($this->configuration['use_yaml']) {
      try {
        $parsed = $this->yamlParser->parse($headers);
      }
      catch (ParseException) {
        $this->logger->error('Tried parsing the headers in action "eca_access_set_file_download_result" as YAML format, but parsing failed.');
        return [];
      }
      return is_array($parsed) ? $parsed : [];
    }

    // Legacy plain format: one "Header-Name: value" per line.
    return $this->parsePlainHeaders(trim($this->tokenService->replace($headers)));
  }

  /**
   * Parses raw header lines into an associative array of headers.
   *
   * Each header line must follow the format "Header-Name: value". Lines
   * without a colon or with an empty key are ignored. Header values may be
   * empty.
   *
   * @param string $raw
   *   The raw headers string, with newlines separating each header.
   *
   * @return array<string, string>
   *   An associative array of headers, keyed by header name.
   */
  private function parsePlainHeaders(string $raw): array {
    $headers = [];

    if ($raw === '') {
      return $headers;
    }

    foreach (explode("\n", $raw) as $line) {
      $parts = explode(':', $line, 2);

      if (count($parts) < 2) {
        continue;
      }

      [$key, $value] = array_map('trim', $parts);

      if ($key !== '') {
        $headers[$key] = $value;
      }
    }

    return $headers;
  }

}
