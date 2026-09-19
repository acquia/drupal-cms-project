<?php

namespace Drupal\eca_node_access\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_node_access\Event\NodeGrants;

/**
 * Plugin implementation of the set node grants action.
 */
#[Action(
  id: 'eca_node_access_set_node_grants',
  label: new TranslatableMarkup('Set node grants'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Sets the node grants for the account. Only works when reacting upon the <em>Node Access: node grants for an account</em> event.'),
  version_introduced: '3.1.3',
)]
class SetNodeGrants extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->event instanceof NodeGrants ? AccessResult::allowed() : AccessResult::forbidden("Event is not compatible with this action.");
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!($this->event instanceof NodeGrants)) {
      return;
    }

    $realm = $this->tokenService->replace($this->configuration['realm']);
    $grantIDs = $this->tokenService->replace($this->configuration['grant_ids']);
    $grant_ids = explode(',', $grantIDs);

    // Filter out any duplicates.
    $grant_ids = array_values(array_unique($grant_ids));
    foreach ($grant_ids as $grant_id) {
      $grant_id = trim($grant_id);
      // Grants have to be integers.
      if (is_numeric($grant_id)) {
        $this->event->addGrant($realm, (int) $grant_id);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'realm' => 'eca_node_access',
      'grant_ids' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['realm'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Realm'),
      '#description' => $this->t('An arbitrary string that must match the realm used in the set node access records action.') . ' ' . $this->t('<em>Please note, this action only works when reacting upon the Node Access: node grants for an account event.</em>'),
      '#default_value' => $this->configuration['realm'],
      '#weight' => 10,
      '#eca_token_replacement' => TRUE,
    ];

    $form['grant_ids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Grant IDs'),
      '#description' => $this->t('Grant identifiers must be integers.  To pass in multiple, separate with a comma.'),
      '#default_value' => $this->configuration['grant_ids'],
      '#weight' => 20,
      '#eca_token_replacement' => TRUE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['grant_ids'] = trim($form_state->getValue('grant_ids'));
    $this->configuration['realm'] = trim($form_state->getValue('realm'));
    parent::submitConfigurationForm($form, $form_state);
  }

}
