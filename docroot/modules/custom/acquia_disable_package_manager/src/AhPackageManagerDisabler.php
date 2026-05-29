<?php

declare(strict_types=1);

namespace Drupal\acquia_disable_package_manager;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\SandboxValidationEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\Validator\BaseRequirementsFulfilledValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Disables Project Browser and unattended automatic updates on Acquia hosting.
 */
final class AhPackageManagerDisabler implements ConfigFactoryOverrideInterface, EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];

    // If Package Manager is disabled, this class won't exist.
    if (class_exists(SandboxValidationEvent::class)) {
      $events[StatusCheckEvent::class] = $events[PreCreateEvent::class] = [
        'disable',
        // Run before any other validation.
        BaseRequirementsFulfilledValidator::PRIORITY + 100,
      ];
    }
    return $events;
  }

  /**
   * Warns (or errs) because Package Manager is disabled on Acquia hosting.
   */
  public function disable(SandboxValidationEvent $event): void {
    if (AcquiaDrupalEnvironmentDetector::isAhEnv()) {
      $event->addError([
        $this->t('Package Manager is disabled on Acquia hosting.'),
      ]);
      // Stop all further validation.
      $event->stopPropagation();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names): array {
    $overrides = [];

    if (AcquiaDrupalEnvironmentDetector::isAhEnv()) {
      if (in_array('project_browser.admin_settings', $names, TRUE)) {
        $overrides['project_browser.admin_settings']['allow_ui_install'] = FALSE;
      }
      if (in_array('automatic_updates.settings', $names, TRUE)) {
        $overrides['automatic_updates.settings']['unattended']['level'] = 'disable';
        $overrides['automatic_updates.settings']['allow_core_minor_updates'] = FALSE;
      }
    }
    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix(): string {
    return 'acquia';
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION): null {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name): CacheableMetadata {
    return new CacheableMetadata();
  }

}
