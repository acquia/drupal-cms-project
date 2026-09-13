<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca_content\Plugin\EntityDiffTrait;

/**
 * Plugin implementation of the ECA condition to compare two entities.
 */
#[EcaCondition(
  id: 'eca_entity_diff',
  label: new TranslatableMarkup('Entity: compare'),
  context_definitions: [
    'entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup('Entity'),
    ),
  ],
  description: new TranslatableMarkup('Compares two entities.'),
  version_introduced: '2.0.0',
)]
class EntityDiff extends ConditionBase {

  use EntityDiffTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return $this->commonDefaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $this->doBuildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->doSubmitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    if ($this->access($entity)) {
      return count($this->compare($entity)) > 0;
    }
    return FALSE;
  }

}
