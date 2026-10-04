<?php

namespace Drupal\custom_field\Plugin\EntityUsage\Track;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\entity_usage\Attribute\EntityUsageTrack;
use Drupal\entity_usage\EntityUsageTrackBase;

/**
 * Tracks usage of entities related in custom fields with link items.
 */
#[EntityUsageTrack(
  id: 'custom_field_link',
  label: new TranslatableMarkup('Custom Field Links'),
  description: new TranslatableMarkup("Tracks relationships created with 'Custom Field' Link and URI sub-fields."),
  field_types: ['custom'],
  source_entity_class: FieldableEntityInterface::class,
)]
class CustomFieldLink extends EntityUsageTrackBase {

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $item): array {
    $field = $item->getFieldDefinition();
    $columns = $field->getSetting('columns');
    $urls = [];
    foreach ($columns as $name => $column) {
      if ($column['type'] === 'link' || $column['type'] === 'uri') {
        $uri = $item->{$name} ?? '';
        $options = $item->{$name . CustomFieldTypeBase::SEPARATOR . 'options'} ?? [];
        if ($uri) {
          $urls[] = Url::fromUri($uri, $options);
        }
      }
    }
    if (empty($urls)) {
      return [];
    }

    $entity_ids = [];
    foreach ($urls as $url) {
      if ($url->isExternal()) {
        $url = $url->toString();
        if ($entity_info = $this->urlToEntity->findEntityIdByUrl($url)) {
          $entity_ids[$entity_info['type']][] = $entity_info['id'];
        }
      }
      else {
        if ($entity_info = $this->urlToEntity->findEntityIdByRoutedUrl($url)) {
          $entity_ids[$entity_info['type']][] = $entity_info['id'];
        }
      }
    }

    $return = [];
    foreach ($entity_ids as $target_type_id => $entity_id_values) {
      $return = \array_merge($return, $this->checkAndPrepareEntityIds($target_type_id, $entity_id_values, 'id'));
    }
    return $return;
  }

}
