<?php

namespace Drupal\entity_reference_revisions\Hook;

use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for entity_reference_revisions.
 */
class EntityReferenceRevisionsHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.entity_reference_revisions':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Entity Reference Revisions module allows you to create fields that contain links to other entities (such as content items, taxonomy terms, etc.) within the site. This allows you, for example, to include a link to a user within a content item. For more information, see <a href=":er_do">the online documentation for the Entity Reference Revisions module</a> and the <a href=":field_help">Field module help page</a>.', [
          ':field_help' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
          ':er_do' => 'https://drupal.org/documentation/modules/entity_reference_revisions',
        ]) . '</p>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Managing and displaying entity reference fields') . '</dt>';
        $output .= '<dd>' . $this->t('The <em>settings</em> and the <em>display</em> of the entity reference field can be configured separately. See the <a href=":field_ui">Field UI help</a> for more information on how to manage fields and their display.', [
          ':field_ui' => Url::fromRoute('help.page', [
            'name' => 'field_ui',
          ])->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Selecting reference type') . '</dt>';
        $output .= '<dd>' . $this->t('In the field settings you can select which entity type you want to create a reference to.') . '</dd>';
        $output .= '<dt>' . $this->t('Filtering and sorting reference fields') . '</dt>';
        $output .= '<dd>' . $this->t('Depending on the chosen entity type, additional filtering and sorting options are available for the list of entities that can be referred to, in the field settings. For example, the list of users can be filtered by role and sorted by name or ID.') . '</dd>';
        $output .= '<dt>' . $this->t('Displaying a reference') . '</dt>';
        $output .= '<dd>' . $this->t('An entity reference can be displayed as a simple label with or without a link to the entity. Alternatively, the referenced entity can be displayed as a teaser (or any other available view mode) inside the referencing entity.') . '</dd>';
        $output .= '</dl>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_field_widget_info_alter().
   */
  #[Hook('field_widget_info_alter')]
  public function fieldWidgetInfoAlter(array &$info): void {
    if (isset($info['options_select'])) {
      $info['options_select']['field_types'][] = 'entity_reference_revisions';
    }
    if (isset($info['options_buttons'])) {
      $info['options_buttons']['field_types'][] = 'entity_reference_revisions';
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'field_ui_field_storage_add_form'.
   */
  #[Hook('form_field_ui_field_storage_add_form_alter')]
  public function formFieldUiFieldStorageAddFormAlter(array &$form): void {
    // Changes the entity reference revisions option on Drupal 11.2+.
    if (isset($form['field_options_wrapper']['fields']['entity_reference_revisions']['#title'])) {
      $form['field_options_wrapper']['fields']['entity_reference_revisions']['#title'] = $this->t('Other (revisions)');
      $form['field_options_wrapper']['fields']['entity_reference_revisions']['#weight'] = 99;
    }
    // Changes the entity reference revisions option on older core versions.
    if (isset($form["group_field_options_wrapper"]["fields"]["entity_reference_revisions"]["#title"])) {
      $form["group_field_options_wrapper"]["fields"]["entity_reference_revisions"]["#title"] = $this->t('Other (revisions)');
      $form["group_field_options_wrapper"]["fields"]["entity_reference_revisions"]["#weight"] = 99;
    }
  }

}
