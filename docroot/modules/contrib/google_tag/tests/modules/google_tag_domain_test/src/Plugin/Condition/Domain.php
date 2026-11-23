<?php

declare(strict_types=1);

namespace Drupal\google_tag_domain_test\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Stands in for the Domain module's 'domain' condition plugin.
 */
#[Condition(
  id: 'domain',
  label: new TranslatableMarkup('Domain'),
  context_definitions: [
    'domain' => new ContextDefinition(
      data_type: 'any',
      label: new TranslatableMarkup('Domain'),
    ),
  ],
)]
final class Domain extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['domains' => []] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return '';
  }

}
