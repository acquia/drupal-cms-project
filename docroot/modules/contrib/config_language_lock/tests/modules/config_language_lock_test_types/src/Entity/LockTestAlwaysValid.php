<?php

declare(strict_types=1);

namespace Drupal\config_language_lock_test_types\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the lock test always valid config entity type.
 *
 * Has FullyValidatable in its schema — used to test that the language lock
 * normalizes langcodes before validation runs.
 */
#[ConfigEntityType(
  id: 'lock_test_always_valid',
  label: new TranslatableMarkup('Lock test always valid'),
  config_prefix: 'always_valid',
  entity_keys: ['id' => 'id', 'label' => 'label'],
  admin_permission: 'administer site configuration',
  config_export: ['id', 'label'],
)]
class LockTestAlwaysValid extends ConfigEntityBase {}
