<?php

namespace Drupal\eca_htmx\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca_render\Plugin\Action\RenderElementActionBase;

/**
 * Renders a region that auto-polls an endpoint via HTMX.
 */
#[Action(
  id: 'eca_htmx_poll',
  label: new TranslatableMarkup('HTMX: poll region'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Build a region that periodically polls a URL via HTMX and swaps in the returned markup. The polled fragment can be served by an ECA endpoint (see the ECA Endpoint module).'),
  version_introduced: '3.1.3',
)]
class Poll extends RenderElementActionBase {

  use PluginFormTrait;
  use HtmxConfigTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'url' => '',
      'interval' => '10',
      'swap' => 'innerHTML',
      'target' => '',
      'content' => '',
      'wrapper_tag' => 'div',
      'htmx_only' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Poll URL'),
      '#description' => $this->t('The URL that HTMX requests on every poll. The response markup replaces the polled region. An <a href=":url" target="_blank" rel="nofollow noreferrer">ECA endpoint</a> can serve this fragment.', [
        ':url' => 'https://www.drupal.org/docs/contributed-modules/eca-event-condition-action/eca-endpoint',
      ]),
      '#default_value' => $this->configuration['url'],
      '#required' => TRUE,
      '#weight' => 100,
      '#eca_token_replacement' => TRUE,
    ];
    $form['interval'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Poll interval (seconds)'),
      '#description' => $this->t('The number of seconds between two polls.'),
      '#default_value' => $this->configuration['interval'],
      '#required' => TRUE,
      '#weight' => 110,
      '#eca_token_replacement' => TRUE,
    ];
    $form['swap'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Swap strategy'),
      '#description' => $this->t('The HTMX swap strategy, for example <em>innerHTML</em>, <em>outerHTML</em> or <em>beforeend</em>.'),
      '#default_value' => $this->configuration['swap'],
      '#required' => TRUE,
      '#weight' => 120,
      '#eca_token_replacement' => TRUE,
    ];
    $form['target'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Swap target'),
      '#description' => $this->t('Optional CSS selector identifying the element that receives the swapped content. When left empty, the polling region itself is the target.'),
      '#default_value' => $this->configuration['target'],
      '#required' => FALSE,
      '#weight' => 130,
      '#eca_token_replacement' => TRUE,
    ];
    $form['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Initial content'),
      '#description' => $this->t('The markup shown inside the region before the first poll completes.'),
      '#default_value' => $this->configuration['content'],
      '#required' => FALSE,
      '#weight' => 140,
      '#eca_token_replacement' => TRUE,
    ];
    $form['wrapper_tag'] = [
      '#type' => 'select',
      '#title' => $this->t('Wrapper tag'),
      '#description' => $this->t('The HTML tag of the polling region, for example <em>div</em> or <em>span</em>.'),
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
      '#weight' => 150,
      '#eca_token_select_option' => TRUE,
    ];
    $form['htmx_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Request only the main content'),
      '#description' => $this->t('When enabled, the polled URL is requested with the Drupal "only main content" hint, so the response is the minimal HTMX render rather than a full HTML page.'),
      '#default_value' => $this->configuration['htmx_only'],
      '#weight' => 160,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['url'] = $form_state->getValue('url');
    $this->configuration['interval'] = $form_state->getValue('interval');
    $this->configuration['swap'] = $form_state->getValue('swap');
    $this->configuration['target'] = $form_state->getValue('target');
    $this->configuration['content'] = $form_state->getValue('content');
    $this->configuration['wrapper_tag'] = $form_state->getValue('wrapper_tag');
    $this->configuration['htmx_only'] = !empty($form_state->getValue('htmx_only'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $url = trim((string) $this->tokenService->replaceClear($this->configuration['url']));
    $interval = trim((string) $this->tokenService->replaceClear($this->configuration['interval']));
    $swap = trim((string) $this->tokenService->replaceClear($this->configuration['swap']));
    $target = trim((string) $this->tokenService->replaceClear($this->configuration['target']));
    $content = $this->tokenService->replaceClear($this->configuration['content']);
    $wrapper_tag = $this->configuration['wrapper_tag'];
    if ($wrapper_tag === '_eca_token') {
      $wrapper_tag = $this->getTokenValue('wrapper_tag', 'div');
    }
    else {
      $wrapper_tag = trim((string) $this->tokenService->replaceClear($wrapper_tag));
    }

    if ($interval === '' || !is_numeric($interval)) {
      $interval = '10';
    }
    if ($swap === '') {
      $swap = 'innerHTML';
    }
    if ($wrapper_tag === '') {
      $wrapper_tag = 'div';
    }

    $build = [
      '#type' => 'html_tag',
      '#tag' => $wrapper_tag,
      '#value' => Markup::create((string) $content),
    ];

    // Build all data-hx-* attributes through the core Htmx builder.
    $htmx = new Htmx();
    $this->htmxApplyRequestMethod($htmx, 'get', $url);
    // The Htmx::swap() helper appends an "ignoreTitle:true" modifier; for the
    // simple polling region we keep the raw swap strategy that this action has
    // always emitted, so set it directly without the modifier.
    $htmx->getAttributes()->setAttribute('data-hx-swap', $swap);
    $htmx->trigger('every ' . $interval . 's');
    if ($target !== '') {
      $htmx->target($target);
    }
    if (!empty($this->configuration['htmx_only'])) {
      $htmx->onlyMainContent(TRUE);
    }
    $htmx->applyTo($build);
  }

}
