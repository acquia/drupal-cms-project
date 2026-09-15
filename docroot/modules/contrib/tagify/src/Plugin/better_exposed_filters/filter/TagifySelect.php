<?php

declare(strict_types=1);

namespace Drupal\tagify\Plugin\better_exposed_filters\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;

/**
 * Tagify Select widget for options-based and dropdown exposed filters.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "bef_tagify_select",
 *   label = @Translation("Tagify Select"),
 * )
 */
class TagifySelect extends Tagify {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $filter = NULL, array $filter_options = []): bool {
    if (is_null($filter)) {
      return FALSE;
    }
    // For taxonomy filters, only the dropdown (select) type.
    // Autocomplete taxonomy filters are handled by Tagify (bef_tagify).
    if (is_a($filter, 'Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid')) {
      return $filter_options['type'] === 'select';
    }
    // For all other options-based filters (list fields, InOperator, etc.)
    // defer to the base class logic.
    return FilterWidgetBase::isApplicable($filter, $filter_options);
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    // Run base BEF processing (collapsible, secondary, rewrites, etc.)
    // Tagify::exposedFormAlter() handles entity_autocomplete_tagify but
    // gracefully does nothing for options-based elements.
    parent::exposedFormAlter($form, $form_state);

    $field_id = $this->getExposedFilterFieldId();
    if (!isset($form[$field_id])) {
      return;
    }

    // Plain options field (e.g. taxonomy dropdown, list fields): convert to
    // select_tagify. Values are submitted as option keys — no custom validate
    // callback needed.
    if (isset($form[$field_id]['#options'])) {
      $existing = $form[$field_id];
      $is_multiple = $existing['#multiple'] ?? FALSE;

      // BEF's parent processing may strip the Views 'All' sentinel from
      // #options, but BEF still submits 'All' for unfiltered fields. Ensure
      // it is present so Drupal's Select validation does not reject it.
      $options = $existing['#options'];
      if (!isset($options['All'])) {
        $options = ['All' => $this->t('- Any -')] + $options;
      }

      $form[$field_id] = [
        '#type' => 'select_tagify',
        '#options' => $options,
        '#multiple' => $is_multiple,
        '#mode' => $is_multiple ? NULL : 'select',
        '#identifier' => $is_multiple ? NULL : $field_id,
        '#cardinality' => $is_multiple ? 0 : 1,
        '#placeholder' => $this->configuration['advanced']['placeholder'],
        '#match_operator' => $this->configuration['advanced']['match_operator'],
        '#match_limit' => (int) $this->configuration['advanced']['max_items'],
        '#attributes' => [
          'class' => [$field_id],
        ],
      ];
      foreach (['#title', '#required', '#title_display', '#size', '#default_value'] as $prop) {
        if (isset($existing[$prop])) {
          $form[$field_id][$prop] = $existing[$prop];
        }
      }
    }
  }

}
