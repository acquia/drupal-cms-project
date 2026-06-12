<?php

namespace Drupal\linkit\Plugin\Linkit\Substitution;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileInterface;
use Drupal\linkit\SubstitutionInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A substitution plugin for the URL to a file.
 *
 * @Substitution(
 *   id = "media",
 *   label = @Translation("Direct URL to media file entity"),
 * )
 */
class Media extends PluginBase implements SubstitutionInterface, ContainerFactoryPluginInterface {

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(EntityInterface $entity) {
    if (!($entity instanceof MediaInterface)) {
      return NULL;
    }

    $source_field = $entity->getSource()->getSourceFieldDefinition($entity->get('bundle')->entity);
    if ($source_field && $entity->hasField($source_field->getName()) && $entity->get($source_field->getName())->entity instanceof FileInterface) {
      /** @var \Drupal\file\FileInterface $file */
      $file = $entity->get($source_field->getName())->entity;
      return $this->fileUrlGenerator->generate($file->getFileUri());
    }

    // If available, fall back to the canonical URL if the bundle doesn't have
    // a file source field.
    if ($entity->getEntityType()->getLinkTemplate('canonical') != $entity->getEntityType()->getLinkTemplate('edit-form')) {
      return $entity->toUrl('canonical');
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(EntityTypeInterface $entity_type) {
    return $entity_type->entityClassImplements('Drupal\media\MediaInterface');
  }

}
