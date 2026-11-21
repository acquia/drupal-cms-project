<?php

declare(strict_types=1);

namespace Drupal\config_language_lock\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\config_language_lock\CanvasIntegrationChecker;

/**
 * Runtime requirements checks for config_language_lock.
 */
class ConfigLanguageLockRequirementsHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly CanvasIntegrationChecker $canvasChecker,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = [];

    if (!$this->canvasChecker->isCanvasInstalled()) {
      return $requirements;
    }

    $settings = $this->configFactory->get('config_language_lock.settings');
    $locked_langcode = $settings->get('locked_langcode');
    $follow_site_default = $settings->get('follow_site_default');
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();

    $is_valid = $follow_site_default && $locked_langcode === $default_langcode;
    $has_mismatch = !$is_valid;

    if ($has_mismatch) {
      $url = Url::fromRoute('config_language_lock.settings')->toString();
      $requirements['config_language_lock_canvas_mismatch'] = [
        'title' => $this->t('Configuration language lock'),
        'value' => $this->t('Misconfigured for Drupal Canvas'),
        'description' => $this->t('Drupal Canvas requires the configuration language to be locked to the site default language. The current configuration does not match. <a href=":url">Fix this on the configuration language lock settings page.</a>', [
          ':url' => $url,
        ]),
        'severity' => RequirementSeverity::Error,
      ];
    }

    return $requirements;
  }

}
