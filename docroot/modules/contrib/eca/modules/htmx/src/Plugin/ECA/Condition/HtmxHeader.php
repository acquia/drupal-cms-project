<?php

declare(strict_types=1);

namespace Drupal\eca_htmx\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca_htmx\HtmxRequestInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ECA condition that compares an HTMX request value against an expected value.
 *
 * The available request values are read exclusively through the core
 * HtmxRequestInfoTrait (via the HtmxRequestInfo wrapper), so this condition
 * never inspects HX-* headers by hand.
 */
#[EcaCondition(
  id: 'eca_htmx_header',
  label: new TranslatableMarkup('HTMX: compare request value'),
  description: new TranslatableMarkup('Compares a value from the current HTMX request (e.g. target, trigger or current URL) against an expected value.'),
  version_introduced: '3.1.3',
)]
class HtmxHeader extends StringComparisonBase {

  /**
   * The HTMX request value to compare: the current request target.
   */
  protected const string VALUE_TARGET = 'target';

  /**
   * The HTMX request value to compare: the triggering element identifier.
   *
   * The format is core-version specific: an element id on Drupal 11, a CSS
   * selector on Drupal 12 and later.
   *
   * @see \Drupal\eca_htmx\HtmxRequestInfo::trigger()
   */
  protected const string VALUE_TRIGGER = 'trigger';

  /**
   * The HTMX request value to compare: the trigger element name attribute.
   */
  protected const string VALUE_TRIGGER_NAME = 'trigger_name';

  /**
   * The HTMX request value to compare: the triggering element CSS selector.
   */
  protected const string VALUE_SOURCE = 'source';

  /**
   * The HTMX request value to compare: full or partial page request.
   */
  protected const string VALUE_REQUEST_TYPE = 'request_type';

  /**
   * The HTMX request value to compare: the current URL.
   */
  protected const string VALUE_CURRENT_URL = 'current_url';

  /**
   * The HTMX request value to compare: whether this is an HTMX request.
   */
  protected const string VALUE_IS_REQUEST = 'is_request';

  /**
   * The HTMX request value to compare: whether the request is boosted.
   */
  protected const string VALUE_BOOSTED = 'boosted';

  /**
   * The HTMX request value to compare: whether this is a history restore.
   */
  protected const string VALUE_HISTORY_RESTORE = 'history_restore';

  /**
   * The HTMX request info service.
   *
   * @var \Drupal\eca_htmx\HtmxRequestInfo
   */
  protected HtmxRequestInfo $htmxRequestInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->htmxRequestInfo = $container->get('eca_htmx.request_info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLeftValue(): string {
    $value = $this->configuration['value'] ?? self::VALUE_TARGET;
    if ($value === '_eca_token') {
      $value = $this->getTokenValue('value', self::VALUE_TARGET);
    }
    return match ($value) {
      self::VALUE_TRIGGER => $this->htmxRequestInfo->trigger(),
      self::VALUE_TRIGGER_NAME => $this->htmxRequestInfo->triggerName(),
      self::VALUE_SOURCE => $this->htmxRequestInfo->source(),
      self::VALUE_REQUEST_TYPE => $this->htmxRequestInfo->requestType(),
      self::VALUE_CURRENT_URL => $this->htmxRequestInfo->currentUrl(),
      self::VALUE_IS_REQUEST => $this->htmxRequestInfo->isRequest() ? '1' : '0',
      self::VALUE_BOOSTED => $this->htmxRequestInfo->isBoosted() ? '1' : '0',
      self::VALUE_HISTORY_RESTORE => $this->htmxRequestInfo->isHistoryRestore() ? '1' : '0',
      default => $this->htmxRequestInfo->target(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->configuration['expected'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'value' => self::VALUE_TARGET,
      'expected' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('HTMX request value'),
      '#description' => $this->t('The value from the current HTMX request that should be compared. The boolean values evaluate to "1" or "0".'),
      '#options' => [
        self::VALUE_TARGET => $this->t('Target: the element the response is aimed at'),
        self::VALUE_TRIGGER => $this->t('Trigger: the triggering element identifier (element id on Drupal 11, CSS selector on Drupal 12 and later)'),
        self::VALUE_TRIGGER_NAME => $this->t('Trigger name: the name attribute of the triggering element (same on all Drupal versions)'),
        self::VALUE_SOURCE => $this->t('Trigger source: the CSS selector of the triggering element (Drupal 12 and later only)'),
        self::VALUE_REQUEST_TYPE => $this->t('Request type: "full" or "partial" (Drupal 12 and later only)'),
        self::VALUE_CURRENT_URL => $this->t('Current URL: the page the request was sent from'),
        self::VALUE_IS_REQUEST => $this->t('Is HTMX request'),
        self::VALUE_BOOSTED => $this->t('Is boosted'),
        self::VALUE_HISTORY_RESTORE => $this->t('Is history restore'),
      ],
      '#default_value' => $this->configuration['value'],
      '#weight' => -100,
      '#eca_token_select_option' => TRUE,
    ];
    $form['expected'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Expected value'),
      '#description' => $this->t('The value to compare the selected HTMX request value against. For boolean values use "1" or "0".'),
      '#default_value' => $this->configuration['expected'],
      '#weight' => -90,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['value'] = $form_state->getValue('value');
    $this->configuration['expected'] = $form_state->getValue('expected');
    parent::submitConfigurationForm($form, $form_state);
  }

}
