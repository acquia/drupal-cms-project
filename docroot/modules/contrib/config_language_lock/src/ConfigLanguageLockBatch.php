<?php

namespace Drupal\config_language_lock;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Provides configuration-language-lock batch update services.
 */
class ConfigLanguageLockBatch {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ConfigLanguageLockConfigManager $configManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly MessengerInterface $messenger,
    /**
     * Logger factory closure.
     *
     * @var \Closure(): \Psr\Log\LoggerInterface
     */
    #[AutowireServiceClosure('logger.channel.config_language_lock')]
    protected readonly \Closure $logger,
  ) {}

  /**
   * Builds a batch to refresh configuration language data.
   *
   * @param bool $finishFeedback
   *   Whether to show a status message when the batch completes.
   */
  public function buildBatch(bool $finishFeedback = TRUE): ?array {
    if ($this->configManager->getLockedLangcode() === NULL) {
      return NULL;
    }

    return $this->buildBatchFromNames($this->configManager->getComponentNames(), $finishFeedback);
  }

  /**
   * Builds a batch for a list of configuration names.
   */
  protected function buildBatchFromNames(array $names, bool $finishFeedback = TRUE): ?array {
    if (empty($names)) {
      return NULL;
    }

    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Updating configuration language'))
      ->setInitMessage($this->t('Starting configuration language update'))
      ->setErrorMessage($this->t('Error updating configuration language'));

    foreach (array_chunk($names, 20) as $chunk) {
      $batch_builder->addOperation(self::class . ':batchUpdateConfigChunk', [$chunk]);
    }

    if ($finishFeedback) {
      $batch_builder->setFinishCallback(self::class . ':batchFinished');
    }

    return $batch_builder->toArray();
  }

  /**
   * Batch operation callback for combined per-config update.
   */
  public function batchUpdateConfigChunk(array $names, array|\ArrayAccess &$context): void {
    $langcode = $this->configManager->getLockedLangcode();
    if ($langcode === NULL) {
      $context['finished'] = 1;
      return;
    }

    if (!isset($context['results']['stats']['config_items_changed'])) {
      $context['results']['stats']['config_items_changed'] = 0;
    }
    if (!isset($context['results']['stats']['translations_updated'])) {
      $context['results']['stats']['translations_updated'] = 0;
    }
    if (!isset($context['results']['stats']['translations_removed'])) {
      $context['results']['stats']['translations_removed'] = 0;
    }

    $chunk_stats = $this->configManager->updateConfigForLockedLanguageSwitch($names);
    $context['results']['stats']['config_items_changed'] += $chunk_stats['config_items_changed'] ?? 0;
    $context['results']['stats']['translations_updated'] += $chunk_stats['translations_updated'] ?? 0;
    $context['results']['stats']['translations_removed'] += $chunk_stats['translations_removed'] ?? 0;

    foreach ($names as $name) {
      $context['results']['names'][] = $name;
    }
    $context['finished'] = 1;

    $language = $this->languageManager->getLanguage($langcode);
    $language_name = $language ? $language->getName() : $langcode;
    $context['message'] = $this->t('Updated configuration language to %language.', ['%language' => $language_name]);
  }

  /**
   * Batch finish callback.
   */
  public function batchFinished(bool $success, array $results): void {
    if ($success) {
      $config_items_changed = $results['stats']['config_items_changed'] ?? 0;
      $translations_updated = $results['stats']['translations_updated'] ?? 0;
      $translations_removed = $results['stats']['translations_removed'] ?? 0;
      $locale_enabled = $this->moduleHandler->moduleExists('locale');

      $langcode = $this->configManager->getLockedLangcode();
      if ($langcode === NULL) {
        return;
      }

      $language = $this->languageManager->getLanguage($langcode);
      $language_name = $language ? $language->getName() : $langcode;

      $total_updates = $config_items_changed + $translations_updated + $translations_removed;
      if ($total_updates === 0) {
        $this->messenger->addStatus($this->t('Site configuration language updated to %language. No configuration item language needed updating.', [
          '%language' => $language_name,
        ]));
        return;
      }

      if ($locale_enabled) {
        $this->messenger->addStatus($this->t('Site configuration language updated to %language. %config_items_changed items changed, %translations_updated translations updated, and %translations_removed translations removed.', [
          '%language' => $language_name,
          '%config_items_changed' => $config_items_changed,
          '%translations_updated' => $translations_updated,
          '%translations_removed' => $translations_removed,
        ]));
        ($this->logger)()->notice('Site configuration language updated to %langcode. %config_items_changed items changed, %translations_updated translations updated, and %translations_removed translations removed.', [
          '%langcode' => $langcode,
          '%config_items_changed' => $config_items_changed,
          '%translations_updated' => $translations_updated,
          '%translations_removed' => $translations_removed,
        ]);
      }
      else {
        $this->messenger->addStatus($this->t('Site configuration language updated to %language. %config_items_changed items changed.', [
          '%language' => $language_name,
          '%config_items_changed' => $config_items_changed,
        ]));
        ($this->logger)()->notice('Site configuration language updated to %langcode. %config_items_changed items changed.', [
          '%langcode' => $langcode,
          '%config_items_changed' => $config_items_changed,
        ]);
      }
    }
  }

}
