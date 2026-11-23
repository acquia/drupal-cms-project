<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Hook;

use Drupal\ai_agents\AiAgentOverrideInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Form alters for the AI agent administration UI.
 */
final class AiAgentCollectionFormAlter {

  use StringTranslationTrait;

  /**
   * Constructs the form alter helper.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Adds override information to the AI agent collection form.
   */
  #[Hook('form_ai_agent_collection_alter')]
  public function formAiAgentCollectionAlter(array &$form, FormStateInterface $form_state): void {
    if (empty($form['entities']['#header']) || !is_array($form['entities']['#header'])) {
      return;
    }

    $form['entities']['#header'] = $this->injectOverridesHeader($form['entities']['#header']);
    $headerOrder = $this->extractHeaderOrder($form['entities']['#header']);

    $summaries = $this->buildOverrideSummaries();
    foreach ($form['entities'] as $id => &$row) {
      if (!is_array($row) || str_starts_with((string) $id, '#')) {
        continue;
      }
      $summary = $summaries[$id] ?? $this->t('None');
      $row['overrides'] = [
        '#plain_text' => (string) $summary,
        '#wrapper_attributes' => [
          'data-drupal-selector' => 'models-table-filter-text-source',
        ],
      ];

      $row = $this->reorderRow($row, $headerOrder);
    }
  }

  /**
   * Inserts the overrides column into the table header.
   */
  private function injectOverridesHeader(array $header): array {
    $newHeader = [];
    $inserted = FALSE;

    foreach ($header as $key => $value) {
      $newHeader[$key] = $value;
      if ($key === 'main_components') {
        $newHeader['overrides'] = $this->t('Overrides');
        $inserted = TRUE;
      }
    }

    if (!$inserted) {
      if (isset($newHeader['operations'])) {
        $operations = $newHeader['operations'];
        unset($newHeader['operations']);
        $newHeader['overrides'] = $this->t('Overrides');
        $newHeader['operations'] = $operations;
      }
      else {
        $newHeader['overrides'] = $this->t('Overrides');
      }
    }

    return $newHeader;
  }

  /**
   * Returns the header order for later row alignment.
   */
  private function extractHeaderOrder(array $header): array {
    $order = [];
    foreach ($header as $key => $value) {
      if (is_string($key) && !str_starts_with($key, '#')) {
        $order[] = $key;
      }
    }
    return $order;
  }

  /**
   * Reorders a row to match the header sequence.
   */
  private function reorderRow(array $row, array $headerOrder): array {
    if ($headerOrder === []) {
      return $row;
    }

    $reordered = [];
    foreach ($headerOrder as $key) {
      if (array_key_exists($key, $row)) {
        $reordered[$key] = $row[$key];
        unset($row[$key]);
      }
    }

    return $reordered + $row;
  }

  /**
   * Builds override summaries indexed by parent agent.
   */
  private function buildOverrideSummaries(): array {
    $storage = $this->entityTypeManager->getStorage('ai_agent_override');
    /**
     * @var \Drupal\ai_agents\AiAgentOverrideInterface[] $overrides
     */
    $overrides = $storage->loadMultiple();

    $grouped = [];
    foreach ($overrides as $override) {
      if (!$override instanceof AiAgentOverrideInterface) {
        continue;
      }
      $parent = $override->getParentAgent();
      if ($parent === '') {
        continue;
      }
      $bucket = $override->status() ? 'enabled' : 'disabled';
      $grouped[$parent][$bucket][] = $override->label();
    }

    $summaries = [];
    foreach ($grouped as $parent => $buckets) {
      $enabled = $buckets['enabled'] ?? [];
      $disabled = $buckets['disabled'] ?? [];

      if ($enabled !== [] && $disabled !== []) {
        $summaries[$parent] = $this->t(
              'Enabled: @enabled; Disabled: @disabled', [
                '@enabled' => implode(', ', $enabled),
                '@disabled' => implode(', ', $disabled),
              ]
          );
      }
      elseif ($enabled !== []) {
        $summaries[$parent] = implode(', ', $enabled);
      }
      elseif ($disabled !== []) {
        $summaries[$parent] = $this->t(
              'Disabled: @overrides', [
                '@overrides' => implode(', ', $disabled),
              ]
          );
      }
    }

    return $summaries;
  }

}
