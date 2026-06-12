<?php

namespace Drupal\modeler_api\Form;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\modeler_api\Api;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to clone a model with a custom ID and label.
 */
class CloneForm extends FormBase {

  /**
   * The modeler API.
   */
  protected Api $api;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type ID of the model being cloned.
   */
  protected string $entityTypeId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = parent::create($container);
    $form->api = $container->get('modeler_api.service');
    $form->entityTypeManager = $container->get('entity_type.manager');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'modeler_api_clone';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ConfigEntityInterface $model = NULL): array {
    if ($model === NULL) {
      return $form;
    }

    $owner = $this->api->findOwner($model);
    $this->entityTypeId = $model->getEntityTypeId();

    $form['model_entity_id'] = [
      '#type' => 'hidden',
      '#value' => $model->id(),
    ];
    $form['model_entity_type_id'] = [
      '#type' => 'hidden',
      '#value' => $this->entityTypeId,
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $owner->getLabel($model) . ' (' . $this->t('clone') . ')',
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Model ID'),
      '#default_value' => '',
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clone'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Checks whether a model entity with the given ID already exists.
   *
   * @param string $value
   *   The machine name to check.
   *
   * @return bool
   *   TRUE if an entity with the given ID exists, FALSE otherwise.
   */
  public function exists(string $value): bool {
    return (bool) $this->entityTypeManager
      ->getStorage($this->entityTypeId)
      ->load($value);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entityTypeId = $form_state->getValue('model_entity_type_id');
    $modelId = $form_state->getValue('model_entity_id');
    $model = $this->entityTypeManager->getStorage($entityTypeId)->load($modelId);

    if ($model instanceof ConfigEntityInterface) {
      $owner = $this->api->findOwner($model);
      if ($owner->isEditable($model)) {
        $owner->clone(
          $model,
          $form_state->getValue('id'),
          $form_state->getValue('label'),
        );
        $this->messenger()->addStatus($this->t('The model %label has been cloned.', [
          '%label' => $form_state->getValue('label'),
        ]));
      }
      $form_state->setRedirect('entity.' . $entityTypeId . '.collection');
    }
  }

}
