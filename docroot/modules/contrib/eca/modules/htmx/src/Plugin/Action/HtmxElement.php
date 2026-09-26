<?php

declare(strict_types=1);

namespace Drupal\eca_htmx\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Service\YamlParser;
use Drupal\eca_render\Plugin\Action\RenderElementActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Builds a configurable HTMX-enabled render element.
 *
 * All data-hx-* attributes are produced through the core
 * \Drupal\Core\Htmx\Htmx fluent builder; none are hand-assembled.
 */
#[Action(
  id: 'eca_htmx_element',
  label: new TranslatableMarkup('HTMX: render element'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Build an HTMX-enabled element with a configurable request method, trigger, swap, target and other common HTMX attributes.'),
  version_introduced: '3.1.3',
)]
class HtmxElement extends RenderElementActionBase {

  use PluginFormTrait;
  use HtmxConfigTrait;

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
      'method' => 'get',
      'url' => '',
      'trigger' => '',
      'swap' => '',
      'target' => '',
      'select' => '',
      'push_url' => '',
      'confirm' => '',
      'indicator' => '',
      'vals' => '',
      'boost' => FALSE,
      'htmx_only' => FALSE,
      'content' => '',
      'wrapper_tag' => 'div',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Request method'),
      '#description' => $this->t('The HTTP method HTMX uses for the request.'),
      '#options' => [
        'get' => $this->t('GET'),
        'post' => $this->t('POST'),
        'put' => $this->t('PUT'),
        'patch' => $this->t('PATCH'),
        'delete' => $this->t('DELETE'),
      ],
      '#default_value' => $this->configuration['method'],
      '#weight' => 100,
      '#eca_token_select_option' => TRUE,
    ];
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request URL'),
      '#description' => $this->t('The URL HTMX requests. Leave empty to request the current URL. Internal paths start with a slash, for example <em>/node/1</em>.'),
      '#default_value' => $this->configuration['url'],
      '#weight' => 110,
      '#eca_token_replacement' => TRUE,
    ];
    $form['trigger'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Trigger'),
      '#description' => $this->t('The HTMX trigger definition, for example <em>click</em>, <em>change</em> or <em>every 5s</em>.'),
      '#default_value' => $this->configuration['trigger'],
      '#weight' => 120,
      '#eca_token_replacement' => TRUE,
    ];
    $form['swap'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Swap strategy'),
      '#description' => $this->t('The HTMX swap strategy, for example <em>innerHTML</em>, <em>outerHTML</em> or <em>beforeend</em>.'),
      '#default_value' => $this->configuration['swap'],
      '#weight' => 130,
      '#eca_token_replacement' => TRUE,
    ];
    $form['target'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target'),
      '#description' => $this->t('Optional CSS selector identifying the element that receives the swapped content.'),
      '#default_value' => $this->configuration['target'],
      '#weight' => 140,
      '#eca_token_replacement' => TRUE,
    ];
    $form['select'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Select'),
      '#description' => $this->t('Optional CSS selector for the content to select from the response.'),
      '#default_value' => $this->configuration['select'],
      '#weight' => 150,
      '#eca_token_replacement' => TRUE,
    ];
    $form['push_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Push URL'),
      '#description' => $this->t('Optional URL to push to the browser history, or <em>true</em>/<em>false</em> to enable or disable pushing the fetched URL.'),
      '#default_value' => $this->configuration['push_url'],
      '#weight' => 160,
      '#eca_token_replacement' => TRUE,
    ];
    $form['confirm'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirm message'),
      '#description' => $this->t('Optional message to show in a confirmation dialog before the request is issued.'),
      '#default_value' => $this->configuration['confirm'],
      '#weight' => 170,
      '#eca_token_replacement' => TRUE,
    ];
    $form['indicator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Indicator'),
      '#description' => $this->t('Optional CSS selector of the element that receives the htmx-request class during the request.'),
      '#default_value' => $this->configuration['indicator'],
      '#weight' => 180,
      '#eca_token_replacement' => TRUE,
    ];
    $form['vals'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Values'),
      '#description' => $this->t('Optional values to add to the request parameters, in YAML format, for example <em>key: "[token]"</em>.'),
      '#default_value' => $this->configuration['vals'],
      '#weight' => 190,
      '#eca_token_replacement' => TRUE,
    ];
    $form['boost'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Boost'),
      '#description' => $this->t('When enabled, anchors and forms inside the element are progressively enhanced to use AJAX.'),
      '#default_value' => $this->configuration['boost'],
      '#weight' => 200,
    ];
    $form['htmx_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Request only the main content'),
      '#description' => $this->t('When enabled, the request is sent with the Drupal "only main content" hint, so the response is the minimal HTMX render rather than a full HTML page.'),
      '#default_value' => $this->configuration['htmx_only'],
      '#weight' => 210,
    ];
    $form['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Initial content'),
      '#description' => $this->t('The markup shown inside the element before any swap occurs.'),
      '#default_value' => $this->configuration['content'],
      '#weight' => 220,
      '#eca_token_replacement' => TRUE,
    ];
    $form['wrapper_tag'] = [
      '#type' => 'select',
      '#title' => $this->t('Wrapper tag'),
      '#description' => $this->t('The HTML tag of the element, for example <em>div</em>, <em>button</em> or <em>span</em>.'),
      '#options' => [
        'div' => 'div',
        'span' => 'span',
        'section' => 'section',
        'article' => 'article',
        'aside' => 'aside',
        'nav' => 'nav',
        'p' => 'p',
        'button' => 'button',
        'a' => 'a',
        'ul' => 'ul',
        'ol' => 'ol',
        'li' => 'li',
        'details' => 'details',
      ],
      '#default_value' => $this->configuration['wrapper_tag'],
      '#required' => TRUE,
      '#weight' => 230,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['method'] = $form_state->getValue('method');
    $this->configuration['url'] = $form_state->getValue('url');
    $this->configuration['trigger'] = $form_state->getValue('trigger');
    $this->configuration['swap'] = $form_state->getValue('swap');
    $this->configuration['target'] = $form_state->getValue('target');
    $this->configuration['select'] = $form_state->getValue('select');
    $this->configuration['push_url'] = $form_state->getValue('push_url');
    $this->configuration['confirm'] = $form_state->getValue('confirm');
    $this->configuration['indicator'] = $form_state->getValue('indicator');
    $this->configuration['vals'] = $form_state->getValue('vals');
    $this->configuration['boost'] = !empty($form_state->getValue('boost'));
    $this->configuration['htmx_only'] = !empty($form_state->getValue('htmx_only'));
    $this->configuration['content'] = $form_state->getValue('content');
    $this->configuration['wrapper_tag'] = $form_state->getValue('wrapper_tag');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $method = $this->configuration['method'];
    if ($method === '_eca_token') {
      $method = $this->getTokenValue('method', 'get');
    }
    $url = trim((string) $this->tokenService->replaceClear($this->configuration['url']));
    $trigger = trim((string) $this->tokenService->replaceClear($this->configuration['trigger']));
    $swap = trim((string) $this->tokenService->replaceClear($this->configuration['swap']));
    $target = trim((string) $this->tokenService->replaceClear($this->configuration['target']));
    $select = trim((string) $this->tokenService->replaceClear($this->configuration['select']));
    $push_url = trim((string) $this->tokenService->replaceClear($this->configuration['push_url']));
    $confirm = trim((string) $this->tokenService->replaceClear($this->configuration['confirm']));
    $indicator = trim((string) $this->tokenService->replaceClear($this->configuration['indicator']));
    $content = $this->tokenService->replaceClear($this->configuration['content']);
    $wrapper_tag = $this->configuration['wrapper_tag'];
    if ($wrapper_tag === '_eca_token') {
      $wrapper_tag = $this->getTokenValue('wrapper_tag', 'div');
    }
    else {
      $wrapper_tag = trim((string) $this->tokenService->replaceClear($wrapper_tag));
    }

    if ($wrapper_tag === '') {
      $wrapper_tag = 'div';
    }

    $build = [
      '#type' => 'html_tag',
      '#tag' => $wrapper_tag,
      '#value' => Markup::create((string) $content),
    ];

    $htmx = new Htmx();
    $this->htmxApplyRequestMethod($htmx, $method, $url);
    if ($trigger !== '') {
      $htmx->trigger($trigger);
    }
    if ($swap !== '') {
      // Set the raw swap strategy without the extra ignoreTitle modifier that
      // the Htmx::swap() helper would append, matching the configured value.
      $htmx->getAttributes()->setAttribute('data-hx-swap', $swap);
    }
    if ($target !== '') {
      $htmx->target($target);
    }
    if ($select !== '') {
      $htmx->select($select);
    }
    if ($push_url !== '') {
      if ($push_url === 'true') {
        $htmx->pushUrl(TRUE);
      }
      elseif ($push_url === 'false') {
        $htmx->pushUrl(FALSE);
      }
      elseif ($push_url_object = $this->htmxUrl($push_url)) {
        $htmx->pushUrl($push_url_object);
      }
    }
    if ($confirm !== '') {
      $htmx->confirm($confirm);
    }
    if ($indicator !== '') {
      $htmx->indicator($indicator);
    }
    $vals = $this->parseVals();
    if ($vals !== []) {
      $htmx->vals($vals);
    }
    if (!empty($this->configuration['boost'])) {
      $htmx->boost(TRUE);
    }
    if (!empty($this->configuration['htmx_only'])) {
      $htmx->onlyMainContent(TRUE);
    }
    $htmx->applyTo($build);
  }

  /**
   * Parses the configured values into a key-value array.
   *
   * @return array<string, string>
   *   The parsed values, or an empty array when none are configured or the
   *   value cannot be parsed as YAML.
   */
  protected function parseVals(): array {
    $vals = trim((string) $this->tokenService->replaceClear($this->configuration['vals']));
    if ($vals === '') {
      return [];
    }
    try {
      $parsed = $this->yamlParser->parse($vals);
    }
    catch (ParseException) {
      $this->logger->error('Tried parsing the values of action "eca_htmx_element" as YAML format, but parsing failed.');
      return [];
    }
    return is_array($parsed) ? $parsed : [];
  }

}
