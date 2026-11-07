<?php

namespace Drupal\dashboard\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Dashboard form.
 *
 * @property \Drupal\dashboard\DashboardInterface $entity
 */
class DashboardLayoutBuilderForm extends EntityForm {

  use AutowireTrait;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * Constructs a new DashboardLayoutBuilderForm.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layoutTempstoreRepository
   *   The layout tempstore repository.
   */
  public function __construct(
    protected LayoutTempstoreRepositoryInterface $layoutTempstoreRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL) {
    // These classes are needed to ensure previews look like the dashboard
    // itself. We attach the library with the styling too.
    $dashboard_id = $this->entity->id();
    $classes = [
      'dashboard',
      $dashboard_id ? Html::getClass('dashboard--' . $dashboard_id) : NULL,
    ];
    $classes = implode(' ', $classes);
    $form['layout_builder'] = [
      '#type' => 'layout_builder',
      '#section_storage' => $section_storage,
      '#prefix' => "<div class='$classes'>",
      '#suffix' => '</div>',
      '#attached' => [
        'library' => ['dashboard/dashboard'],
      ],
    ];
    $this->sectionStorage = $section_storage;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save dashboard layout');
    $actions['delete']['#access'] = FALSE;
    $actions['#weight'] = -1000;

    $actions['discard_changes'] = [
      '#type' => 'link',
      '#title' => $this->t('Discard changes'),
      '#attributes' => ['class' => ['button']],
      '#url' => $this->sectionStorage->getLayoutBuilderUrl('discard_changes'),
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = $this->sectionStorage->save();
    $this->layoutTempstoreRepository->delete($this->sectionStorage);

    $message_args = ['%label' => $this->entity->label()];
    $message = $return == SAVED_NEW
      ? $this->t('Created new dashboard %label layout.', $message_args)
      : $this->t('Updated dashboard %label layout.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->sectionStorage->getRedirectUrl());

    return $return;
  }

}
