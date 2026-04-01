<?php

namespace Drupal\acquia_trials_checklist\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides the Acquia Trials Checklist block.
 */
#[Block(
  id: 'acquia_trials_checklist',
  admin_label: new TranslatableMarkup('Acquia Trials Checklist'),
  category: new TranslatableMarkup('Acquia Trials'),
)]
class TrialsChecklistBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $items_def = acquia_trials_checklist_get_items();
    $state_key = 'checklistapi.progress.acquia_trials_checklist';
    $progress = \Drupal::state()->get($state_key, []);

    $items = [];
    foreach ($items_def as $key => $item) {
      $completed = !empty($progress['getting_started'][$key]['#completed']);
      $items[] = [
        'key' => $key,
        'title' => $item['title'],
        'description' => $item['description'],
        'url' => Url::fromUserInput($item['url'])->toString(),
        'completed' => $completed,
      ];
    }

    $completed_count = count(array_filter($items, fn($i) => $i['completed']));
    $total = count($items);
    $percentage = $total > 0 ? round(($completed_count / $total) * 100) : 0;

    return [
      '#theme' => 'acquia_trials_checklist',
      '#items' => $items,
      '#progress' => $percentage,
      '#attached' => [
        'library' => ['acquia_trials_checklist/checklist'],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
