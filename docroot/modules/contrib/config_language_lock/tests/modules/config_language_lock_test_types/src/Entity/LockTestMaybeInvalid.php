<?php

declare(strict_types=1);

namespace Drupal\config_language_lock_test_types\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the lock test maybe invalid config entity type.
 *
 * Does not have FullyValidatable — used to test that the language lock
 * normalizes langcodes even when no schema validation would fire.
 */
#[ConfigEntityType(
  id: 'lock_test_maybe_invalid',
  label: new TranslatableMarkup('Lock test maybe invalid'),
  config_prefix: 'maybe_invalid',
  entity_keys: ['id' => 'id', 'label' => 'label'],
  admin_permission: 'administer site configuration',
  config_export: ['id', 'label'],
)]
class LockTestMaybeInvalid extends ConfigEntityBase {}
