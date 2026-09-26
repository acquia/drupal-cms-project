<?php

declare(strict_types=1);

namespace Drupal\config_language_lock_test_canvas\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Minimal canvas_page entity type for testing Canvas integration.
 */
#[ContentEntityType(
  id: 'canvas_page',
  label: new TranslatableMarkup('Canvas Page'),
  base_table: 'canvas_page',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
    'langcode' => 'langcode',
  ],
  handlers: [
    'view_builder' => EntityViewBuilder::class,
    'route_provider' => ['html' => DefaultHtmlRouteProvider::class],
    'form' => ['delete' => ContentEntityDeleteForm::class],
  ],
  links: [
    'canonical' => '/canvas-test-page/{canvas_page}',
    'delete-form' => '/canvas-test-page/{canvas_page}/delete',
  ],
  admin_permission: 'administer languages',
)]
class CanvasPage extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setRequired(TRUE);

    return $fields;
  }

}
