<?php

namespace Drupal\eca_cache\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action to invalidate raw cache.
 */
#[Action(
  id: 'eca_raw_cache_invalidate',
  label: new TranslatableMarkup('Cache Raw: invalidate'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Invalidates a part or the whole raw cache.'),
  version_introduced: '2.0.0',
)]
class RawCacheInvalidate extends CacheInvalidate {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $tags = $this->getCacheTags();

    if (empty($tags)) {
      foreach (Cache::getBins() as $bin) {
        $bin->deleteAll();
      }
    }
    else {
      Cache::invalidateTags($tags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    unset($config['backend'], $config['key']);
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    unset($form['backend'], $form['key']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    unset($this->configuration['backend'], $this->configuration['key']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheTags(): array {
    $tags = $this->tokenService->replaceClear($this->configuration['tags']);
    if ($tags !== '') {
      $tags = explode(',', $tags);
      array_walk($tags, static function (&$item) {
        $item = trim((string) $item);
      });
      return $tags;
    }
    return [];
  }

}
