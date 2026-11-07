<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'hierarchical_term_formatter' formatter.
 */
#[FieldFormatter(
  id: 'hierarchical_term_formatter',
  label: new TranslatableMarkup('Hierarchical term'),
  description: new TranslatableMarkup('Display the term hierarchy.'),
  field_types: [
    'entity_reference',
  ],
)]
class HierarchicalFormatter extends EntityReferenceFormatterBase {

  /**
   * Returns a list of supported display options.
   *
   * @return array
   *   An array whose keys are display machine names
   *   and whose values are their labels.
   */
  private function displayOptions(): array {
    return [
      'all' => $this->t('The selected term and all of its parents'),
      'parents' => $this->t('Just the parent terms'),
      'root' => $this->t('Just the topmost/root term'),
      'nonroot' => $this->t('Any non-topmost/root terms'),
      'leaf' => $this->t('Just the selected term'),
    ];
  }

  /**
   * Returns a list of supported wrapping options.
   *
   * @return array
   *   An array whose keys are wrapper machine names
   *   and whose values are their labels.
   */
  private function wrapOptions(): array {
    return [
      'none' => $this->t('None'),
      'span' => $this->t('@tag elements', ['@tag' => '<span>']),
      'div' => $this->t('@tag elements', ['@tag' => '<div>']),
      'ul' => $this->t('@tag elements surrounded by a @parent_tag', [
        '@tag' => '<li>',
        '@parent_tag' => '<ul>',
      ]),
      'ol' => $this->t('@tag elements surrounded by a @parent_tag', [
        '@tag' => '<li>',
        '@parent_tag' => '<ol>',
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'hierarchy_display' => 'all',
      'hierarchy_link' => FALSE,
      'hierarchy_wrap' => 'none',
      'hierarchy_separator' => ' » ',
      'hierarchy_reverse' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['hierarchy_display'] = [
      '#title' => $this->t('Terms to display'),
      '#description' => $this->t('Choose what terms to display.'),
      '#type' => 'select',
      '#options' => $this->displayOptions(),
      '#default_value' => $this->getSetting('hierarchy_display'),
    ];
    $element['hierarchy_link'] = [
      '#title' => $this->t('Link each term'),
      '#description' => $this->t('If checked, the terms will link to their corresponding term pages.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('hierarchy_link'),
    ];
    $element['hierarchy_reverse'] = [
      '#title' => $this->t('Reverse order'),
      '#description' => $this->t('If checked, children display first, parents last.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('hierarchy_reverse'),
    ];
    $element['hierarchy_wrap'] = [
      '#title' => $this->t('Wrap each term'),
      '#description' => $this->t('Choose what type of html elements you would like to wrap the terms in, if any.'),
      '#type' => 'select',
      '#options' => $this->wrapOptions(),
      '#default_value' => $this->getSetting('hierarchy_wrap'),
    ];
    $element['hierarchy_separator'] = [
      '#title' => $this->t('Separator'),
      '#description' => $this->t('Enter some text or markup that will separate each term in the hierarchy. Leave blank for no separator. Example: <em>»</em>'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => $this->getSetting('hierarchy_separator'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    if (!$value instanceof EntityInterface) {
      return NULL;
    }
    try {
      /** @var \Drupal\taxonomy\TermStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage($value->getEntityTypeId());
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return NULL;
    }
    /** @var \Drupal\Core\Access\AccessResultInterface $access */
    $access = $this->checkAccess($value);
    if (!$access->isAllowed()) {
      return NULL;
    }

    $tid = (int) $value->id();
    $langcode = $value->language()->getId();
    $term_tree = [];

    switch ($this->getSetting('hierarchy_display')) {
      case 'leaf':
        $term_tree = [$value];
        break;

      case 'root':
        $parents = $storage->loadAllParents($tid);
        if (!empty($parents)) {
          $term_tree = [array_pop($parents)];
        }
        break;

      case 'parents':
        $term_tree = array_reverse($storage->loadAllParents($tid));
        array_pop($term_tree);
        break;

      case 'nonroot':
        $parents = $storage->loadAllParents($tid);
        if (count($parents) > 1) {
          $term_tree = array_reverse($parents);
          // This gets rid of the first topmost term.
          array_shift($term_tree);
          // Terms can have multiple parents. Now remove any remaining topmost
          // terms.
          foreach ($term_tree as $key => $term) {
            $has_parents = $storage->loadAllParents((int) $term->id());
            // This has no parents and is topmost.
            if (empty($has_parents)) {
              unset($term_tree[$key]);
            }
          }
        }
        break;

      default:
        $term_tree = array_reverse($storage->loadAllParents($tid));
        break;
    }

    // Change output order if Reverse order is checked.
    if ($this->getSetting('hierarchy_reverse') && count($term_tree)) {
      $term_tree = array_reverse($term_tree);
    }

    // Remove empty elements caused by discarded items.
    /** @var \Drupal\taxonomy\TermInterface[] $term_tree */
    $term_tree = array_filter($term_tree);

    foreach ($term_tree as $index => $term) {
      if ($term->hasTranslation($langcode)) {
        $term_tree[$index] = $term->getTranslation($langcode);
      }
    }

    return [
      '#theme' => 'custom_field_hierarchical_formatter',
      '#terms' => $term_tree,
      '#wrapper' => $this->getSetting('hierarchy_wrap'),
      '#separator' => $this->getSetting('hierarchy_separator'),
      '#link' => $this->getSetting('hierarchy_link'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity): bool|AccessResultInterface {
    return $entity->access('view label', NULL, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(CustomFieldTypeInterface $custom_item): bool {
    return $custom_item->getTargetType() === 'taxonomy_term';
  }

}
