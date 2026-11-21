<?php

namespace Drupal\entity_reference_revisions\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'entity_reference_autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_revisions_autocomplete",
 *   label = @Translation("Autocomplete"),
 *   description = @Translation("An autocomplete text field."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
#[FieldWidget(
  id: 'entity_reference_revisions_autocomplete',
  label: new TranslatableMarkup('Autocomplete'),
  description: new TranslatableMarkup('An autocomplete text field.'),
  field_types: ['entity_reference_revisions'],
)]
class EntityReferenceRevisionsAutocompleteWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $entity_type = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type');
    foreach ($values as $key => $value) {
      // The entity_autocomplete form element returns an array when an entity
      // was "autocreated", so we need to move it up a level.
      if (is_array($value['target_id'])) {
        unset($values[$key]['target_id']);
        $values[$key] += $value['target_id'];
      }
      elseif ($value['target_id']) {
        // Add the 'target_revision_id' key since it is not provided by the
        // parent widget.
        /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
        $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($value['target_id']);
        if (!is_null($entity)) {
          $values[$key]['target_revision_id'] = $entity->getRevisionId();
        }
      }
    }
    return $values;
  }

}
