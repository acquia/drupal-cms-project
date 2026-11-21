<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;
use Drupal\node\Entity\Node;

/**
 * Plugin implementation of the 'entity_reference' field type test.
 */
#[TestFieldType(
  id: 'entity_reference',
  label: new TranslatableMarkup('Entity reference'),
)]
class TestEntityReference extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'entity_reference_autocomplete',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\EntityReferenceAutocompleteWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'entity_reference_label',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\EntityReferenceLabelFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'article')
      ->accessCheck(FALSE);
    $nids = $query->execute();
    $nodes = [];
    if (!empty($nids)) {
      foreach ($nids as $nid) {
        $node = Node::load($nid);
        $nodes[] = $node->id();
      }
    }
    return [
      $this->buildTestCase($name, $nodes[0]),
      $this->buildTestCase($name, $nodes[1]),
    ];
  }

}
