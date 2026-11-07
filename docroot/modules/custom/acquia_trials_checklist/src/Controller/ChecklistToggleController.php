<?php

namespace Drupal\acquia_trials_checklist\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Handles AJAX toggling of checklist items.
 */
class ChecklistToggleController extends ControllerBase {

  /**
   * Toggles a checklist item's completed state.
   *
   * @param string $item_id
   *   The checklist item key.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with updated progress.
   */
  public function toggle(string $item_id): JsonResponse {
    $items = acquia_trials_checklist_get_items();
    if (!isset($items[$item_id])) {
      return new JsonResponse(['error' => 'Invalid item'], 400);
    }

    $state_key = 'checklistapi.progress.acquia_trials_checklist';
    $progress = \Drupal::state()->get($state_key, []);

    if (!isset($progress['getting_started'])) {
      $progress['getting_started'] = [];
    }

    $completed = !empty($progress['getting_started'][$item_id]['#completed']);
    if ($completed) {
      unset($progress['getting_started'][$item_id]);
    }
    else {
      $progress['getting_started'][$item_id] = [
        '#completed' => time(),
        '#uid' => $this->currentUser()->id(),
      ];
    }

    $progress['#changed'] = time();
    $progress['#changed_by'] = $this->currentUser()->id();

    \Drupal::state()->set($state_key, $progress);

    // Build response with updated state.
    $completed_items = [];
    $completed_count = 0;
    foreach ($items as $key => $item) {
      $is_done = !empty($progress['getting_started'][$key]['#completed']);
      $completed_items[$key] = $is_done;
      if ($is_done) {
        $completed_count++;
      }
    }

    $total = count($items);
    $percentage = $total > 0 ? round(($completed_count / $total) * 100) : 0;

    return new JsonResponse([
      'item_id' => $item_id,
      'completed' => $completed_items,
      'percentage' => $percentage,
    ]);
  }

}
