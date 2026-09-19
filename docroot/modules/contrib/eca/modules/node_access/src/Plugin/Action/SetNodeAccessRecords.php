<?php

namespace Drupal\eca_node_access\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_node_access\Event\NodeAccessRecords;

/**
 * Plugin implementation of the set node access records action.
 */
#[Action(
  id: 'eca_node_access_set_node_access_records',
  label: new TranslatableMarkup('Set node access records'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Sets the node access grant records for the node. Only works when reacting upon the <em>Node Access: record access grants for a node</em> event.'),
  version_introduced: '3.1.3',
)]
class SetNodeAccessRecords extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->event instanceof NodeAccessRecords ? AccessResult::allowed() : AccessResult::forbidden("Event is not compatible with this action.");
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!($this->event instanceof NodeAccessRecords)) {
      return;
    }

    $realm = $this->tokenService->replace($this->configuration['realm']);
    $grants = [];
    $grantID = $this->tokenService->replace($this->configuration['grant_id']);

    // A single action invocation may produce multiple records sharing the same
    // realm and flags, one per comma-separated grant ID.
    $grant_ids = explode(',', $grantID);

    // Filter out any duplicates.
    $grant_ids = array_values(array_unique($grant_ids));
    foreach ($grant_ids as $grant_id) {
      $grant_id = trim($grant_id);
      // Grants have to be integers.
      if (is_numeric($grant_id)) {
        $grant_id = (int) $grant_id;
        $grants[] = $grant_id;
      }
    }

    // Filter out any duplicates.
    $grants = array_values(array_unique($grants));

    // Read the grant flags from configuration once; they are shared by every
    // record this invocation contributes. They are booleans here; the event's
    // addRecord() converts them to the integer 0/1 the hook contract expects.
    $grantView = (bool) $this->configuration['grant_view'];
    $grantUpdate = (bool) $this->configuration['grant_update'];
    $grantDelete = (bool) $this->configuration['grant_delete'];

    // Append one complete record per grant ID. The event accumulates records,
    // so multiple actions on the same event can each contribute records with
    // different realms and flags.
    foreach ($grants as $grant_id) {
      $this->event->addRecord($realm, $grant_id, $grantView, $grantUpdate, $grantDelete);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'realm' => 'eca_node_access',
      'grant_id' => '',
      'grant_view' => TRUE,
      'grant_update' => FALSE,
      'grant_delete' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['realm'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Realm'),
      '#description' => $this->t('An arbitrary string that must match the realm used in the set node access records action.') . ' ' . $this->t('<em>Please note, this action only works when reacting upon the Node Access: record access grants for a node event.</em>'),
      '#default_value' => $this->configuration['realm'],
      '#weight' => 10,
      '#eca_token_replacement' => TRUE,
    ];

    $form['grant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Grant ID'),
      '#description' => $this->t('The grant identifier must be an integer. Note that the node ID being acted on in the "Node Access: record access grants for a node" event will by default be available as [entity] or [node].'),
      '#default_value' => $this->configuration['grant_id'],
      '#weight' => 20,
      '#eca_token_replacement' => TRUE,
    ];

    $form['grant_view'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Grant view'),
      '#default_value' => $this->configuration['grant_view'],
      '#weight' => 30,
    ];

    $form['grant_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Grant update'),
      '#default_value' => $this->configuration['grant_update'],
      '#weight' => 30,
    ];

    $form['grant_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Grant delete'),
      '#default_value' => $this->configuration['grant_delete'],
      '#weight' => 30,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['grant_id'] = trim($form_state->getValue('grant_id'));
    $this->configuration['realm'] = trim($form_state->getValue('realm'));
    $this->configuration['grant_view'] = (bool) $form_state->getValue('grant_view');
    $this->configuration['grant_update'] = (bool) $form_state->getValue('grant_update');
    $this->configuration['grant_delete'] = (bool) $form_state->getValue('grant_delete');
    parent::submitConfigurationForm($form, $form_state);
  }

}
