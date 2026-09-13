<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plugin implementation of the 'timestamp_ago' formatter.
 */
#[FieldFormatter(
  id: 'timestamp_ago',
  label: new TranslatableMarkup('Time ago'),
  field_types: [
    'timestamp',
  ],
)]
class TimestampAgoFormatter extends CustomFieldFormatterBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The current Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    $instance->dateFormatter = $container->get('date.formatter');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'future_format' => '@interval hence',
      'past_format' => '@interval ago',
      'granularity' => 2,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {

    $elements['future_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Future format'),
      '#default_value' => $this->getSetting('future_format'),
      '#required' => TRUE,
      '#description' => $this->t('Use <em>@interval</em> where you want the formatted interval text to appear.'),
    ];

    $elements['past_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Past format'),
      '#default_value' => $this->getSetting('past_format'),
      '#required' => TRUE,
      '#description' => $this->t('Use <em>@interval</em> where you want the formatted interval text to appear.'),
    ];

    $elements['granularity'] = [
      '#type' => 'number',
      '#title' => $this->t('Granularity'),
      '#description' => $this->t('How many time interval units should be shown in the formatted output.'),
      '#default_value' => $this->getSetting('granularity') ?: 2,
      '#min' => 1,
      '#max' => 7,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    return $this->formatTimestamp($value);
  }

  /**
   * Formats a timestamp.
   *
   * @param int $timestamp
   *   A UNIX timestamp to format.
   *
   * @return array
   *   The formatted timestamp string using the past or future format setting.
   */
  protected function formatTimestamp(int $timestamp): array {
    $options = [
      'granularity' => $this->getSetting('granularity'),
      'return_as_object' => TRUE,
    ];

    if ($this->request->server->get('REQUEST_TIME') > $timestamp) {
      /** @var \Drupal\Core\Datetime\FormattedDateDiff $result */
      $result = $this->dateFormatter->formatTimeDiffSince($timestamp, $options);
      $build = [
        '#markup' => new FormattableMarkup($this->getSetting('past_format'), ['@interval' => $result->getString()]),
      ];
    }
    else {
      /** @var \Drupal\Core\Datetime\FormattedDateDiff $result */
      $result = $this->dateFormatter->formatTimeDiffUntil($timestamp, $options);
      $build = [
        '#markup' => new FormattableMarkup($this->getSetting('future_format'), ['@interval' => $result->getString()]),
      ];
    }
    CacheableMetadata::createFromObject($result)->applyTo($build);
    return $build;
  }

}
