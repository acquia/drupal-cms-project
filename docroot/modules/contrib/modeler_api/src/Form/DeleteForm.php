<?php

namespace Drupal\modeler_api\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Creates a form to delete a config entity of a model owner.
 */
class DeleteForm extends EntityDeleteForm {

  // Only use the following trait here to make sure it gets tested by PhpStan.
  use EditFormActionButtonsTrait;

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Deleting model %label', ['%label' => $this->entity->label()]);
  }

}
