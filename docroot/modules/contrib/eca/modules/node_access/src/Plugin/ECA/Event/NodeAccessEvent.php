<?php

namespace Drupal\eca_node_access\Plugin\ECA\Event;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Attribute\EcaEvent;
use Drupal\eca\Attribute\Token;
use Drupal\eca\Entity\Objects\EcaEvent as EcaEventObject;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_node_access\Event\NodeAccessRecords;
use Drupal\eca_node_access\Event\NodeGrants;
use Drupal\eca_node_access\NodeAccessEvents;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of the ECA node access events.
 */
#[EcaEvent(
  id: 'node_access',
  deriver: 'Drupal\eca_node_access\Plugin\ECA\Event\NodeAccessEventDeriver',
  version_introduced: '3.1.3',
)]
class NodeAccessEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    return [
      'node_access_grants' => [
        'label' => 'Node Access: node grants for an account',
        'event_name' => NodeAccessEvents::GRANTS,
        'event_class' => NodeGrants::class,
        'tags' => Tag::CONTENT | Tag::RUNTIME,
      ],
      'node_access_records' => [
        'label' => 'Node Access: record access grants for a node',
        'event_name' => NodeAccessEvents::NODE_ACCESS_RECORDS,
        'event_class' => NodeAccessRecords::class,
        'tags' => Tag::CONTENT | Tag::PERSISTENT,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    if ($this->eventClass() === NodeGrants::class) {
      // Empty operation means the event applies for any node operation.
      $values = [
        'operation' => '',
      ];
    }
    else {
      $values = [];
    }
    return $values + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if ($this->eventClass() === NodeGrants::class) {
      $form['operation'] = [
        '#type' => 'select',
        '#title' => $this->t('Operation'),
        '#description' => $this->t('Optionally restrict these grants to a single node operation. When set, the event only applies when Drupal requests grants for that operation; leave as "Any operation" to apply for view, update and delete.'),
        '#default_value' => $this->configuration['operation'],
        '#options' => [
          '' => $this->t('Any operation'),
          'view' => $this->t('View'),
          'update' => $this->t('Update'),
          'delete' => $this->t('Delete'),
        ],
        '#required' => TRUE,
        '#weight' => 10,
      ];
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    if ($this->eventClass() === NodeGrants::class) {
      $this->configuration['operation'] = $form_state->getValue('operation');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEventObject $ecaEvent): string {
    if ($this->getDerivativeId() === 'node_access_grants') {
      // The only filter dimension is the node operation. An empty operation
      // means "any operation", encoded with the '*' sentinel.
      $operation = $ecaEvent->getConfiguration()['operation'] ?? '';
      return in_array($operation, ['', '*'], TRUE) ? '*' : $operation;
    }
    // The records derivative fires unconditionally for every node bundle, so
    // it has no filter and falls through to the default '*' wildcard.
    return parent::generateWildcard($eca_config_id, $ecaEvent);
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    if ($event instanceof NodeGrants) {
      // The wildcard is the configured operation, or the '*' sentinel for
      // "any operation". When a concrete operation is configured, the event
      // only applies when Drupal requests grants for that exact operation.
      if (($wildcard !== '*') && ($event->getOperation() !== $wildcard)) {
        return FALSE;
      }
      return TRUE;
    }
    if ($event instanceof NodeAccessRecords) {
      // Records are written for every node bundle, so the event always applies.
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'event',
    description: 'The event.',
    classes: [
      NodeGrants::class,
    ],
    properties: [
      new Token(name: 'operation', description: 'The node operation being requested: view, update or delete.'),
    ],
  )]
  protected function buildEventData(): array {
    $event = $this->event;
    $data = [];
    if ($event instanceof NodeGrants) {
      $data['operation'] = $event->getOperation();
    }
    $data += parent::buildEventData();
    return $data;
  }

}
