<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Plugin implementation of the ECA condition for entity is accessible.
 */
#[EcaCondition(
  id: 'eca_entity_is_accessible',
  label: new TranslatableMarkup('Entity: is accessible'),
  context_definitions: [
    'entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup('Entity'),
    ),
  ],
  description: new TranslatableMarkup('Evaluates whether the current user has operational access on an entity.'),
  version_introduced: '1.0.0',
)]
class EntityIsAccessible extends ConditionBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    if (!($entity instanceof EntityInterface)) {
      return FALSE;
    }
    $operation = $this->configuration['operation'];
    if ($operation === '_eca_token') {
      $operation = $this->getTokenValue('operation', 'view');
    }
    if (!$entity->isNew() && ($operation === 'create')) {
      return FALSE;
    }
    return $this->negationCheck($entity->access($operation));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['operation' => 'view'] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['operation'] = [
      '#type' => 'select',
      '#title' => $this->t('Operation'),
      '#description' => $this->t('The operation, like view, edit or delete to check accessibility.'),
      '#options' => [
        'create' => $this->t('Create (only for new entity)'),
        'view' => $this->t('View'),
        'update' => $this->t('Update'),
        'delete' => $this->t('Delete'),
      ],
      '#default_value' => $this->configuration['operation'] ?? 'view',
      '#required' => TRUE,
      '#weight' => -10,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['operation'] = $form_state->getValue('operation');
    parent::submitConfigurationForm($form, $form_state);
  }

}
